<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class AccountApiMiddleware implements MiddlewareInterface
{
    private const PREFIX = '/api/piplio/';
    private const PROFILE_TABLE = 'tx_pipliobackend_childprofile';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, self::PREFIX . 'auth/') && !str_starts_with($path, self::PREFIX . 'profiles')) {
            return $handler->handle($request);
        }
        if ($request->getMethod() === 'OPTIONS') {
            return $this->json(['ok' => true]);
        }

        try {
            $method = strtoupper($request->getMethod());
            if ($path === self::PREFIX . 'auth/request-code' && $method === 'POST') {
                return $this->requestCode($request);
            }
            if ($path === self::PREFIX . 'auth/verify-code' && $method === 'POST') {
                return $this->verifyCode($request);
            }
            $parentId = $this->authenticate($request);
            if ($parentId === null) {
                return $this->json(['error' => 'Unauthorized.'], 401);
            }
            if ($path === self::PREFIX . 'profiles' && $method === 'GET') {
                return $this->listProfiles($parentId);
            }
            if ($path === self::PREFIX . 'profiles' && $method === 'POST') {
                return $this->createProfile($request, $parentId);
            }
            if (preg_match('#^' . preg_quote(self::PREFIX, '#') . 'profiles/(\d+)$#', $path, $m) === 1) {
                $profileId = (int)$m[1];
                if (!$this->ownsProfile($parentId, $profileId)) {
                    return $this->json(['error' => 'Profile not found.'], 404);
                }
                if ($method === 'PATCH') return $this->updateProfile($request, $profileId);
                if ($method === 'DELETE') return $this->deleteProfile($profileId);
            }
            if (preg_match('#^' . preg_quote(self::PREFIX, '#') . 'profiles/(\d+)/progress$#', $path, $m) === 1) {
                $profileId = (int)$m[1];
                if (!$this->ownsProfile($parentId, $profileId)) return $this->json(['error' => 'Profile not found.'], 404);
                if ($method === 'GET') return $this->getProgress($profileId);
                if ($method === 'PUT') return $this->putProgress($request, $profileId);
            }
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Server error.'], 500);
        }
        return $this->json(['error' => 'Not found.'], 404);
    }

    private function requestCode(ServerRequestInterface $request): JsonResponse
    {
        $email = strtolower(trim((string)($this->body($request)['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->json(['error' => 'Invalid email.'], 400);
        $now = time();
        $connection = $this->connection('tx_pipliobackend_parent');
        $row = $this->findParentByEmail($email);
        if ($row !== null && (int)$row['login_code_sent_at'] > $now - 60) {
            return $this->json(['ok' => true, 'retryAfterSeconds' => 60], 202);
        }
        $code = (string)random_int(100000, 999999);
        $fields = [
            'tstamp' => $now, 'email' => $email,
            'login_code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'login_code_expires' => $now + 300, 'login_code_attempts' => 0, 'login_code_sent_at' => $now,
        ];
        if ($row === null) {
            $connection->insert('tx_pipliobackend_parent', $fields + ['crdate' => $now]);
        } else {
            $fields['deleted'] = 0;
            if ((int)($row['crdate'] ?? 0) === 0) $fields['crdate'] = $now;
            $connection->update('tx_pipliobackend_parent', $fields, ['uid' => (int)$row['uid']]);
        }
        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail
            ->setTo($email)
            ->setSubject('Dein Piplio-Anmeldecode')
            ->text("Dein Piplio-Anmeldecode lautet: {$code}\n\nDer Code ist fünf Minuten gültig. Wenn du diese Anmeldung nicht angefordert hast, kannst du diese E-Mail ignorieren.")
            ->html($this->loginCodeHtml($code));
        GeneralUtility::makeInstance(MailerInterface::class)->send($mail);
        return $this->json(['ok' => true, 'retryAfterSeconds' => 60], 202);
    }

    private function verifyCode(ServerRequestInterface $request): JsonResponse
    {
        $body = $this->body($request);
        $email = strtolower(trim((string)($body['email'] ?? '')));
        $code = trim((string)($body['code'] ?? ''));
        $row = $this->findParentByEmail($email);
        $now = time();
        if ($row === null || (int)$row['login_code_expires'] < $now || (int)$row['login_code_attempts'] >= 5 || !password_verify($code, (string)$row['login_code_hash'])) {
            if ($row !== null) $this->connection('tx_pipliobackend_parent')->update('tx_pipliobackend_parent', ['login_code_attempts' => (int)$row['login_code_attempts'] + 1], ['uid' => (int)$row['uid']]);
            return $this->json(['error' => 'Invalid code.'], 401);
        }
        $token = $this->issueToken((int)$row['uid'], $now + 2592000);
        $this->connection('tx_pipliobackend_parent')->update('tx_pipliobackend_parent', [
            'tstamp' => $now, 'session_token_hash' => hash('sha512', $token), 'session_expires' => $now + 2592000,
            'login_code_hash' => '', 'login_code_expires' => 0, 'login_code_attempts' => 0,
        ], ['uid' => (int)$row['uid']]);
        return $this->json(['accessToken' => $token, 'expiresIn' => 2592000, 'parent' => ['id' => 'parent_' . $row['uid'], 'email' => $email]]);
    }

    private function listProfiles(int $parentId): JsonResponse
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::PROFILE_TABLE);
        $rows = $qb->select('uid', 'display_name', 'avatar', 'crdate', 'tstamp')->from(self::PROFILE_TABLE)
            ->where($qb->expr()->eq('parent', $qb->createNamedParameter($parentId, Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('uid')->executeQuery()->fetchAllAssociative();
        return $this->json(['profiles' => array_map(fn(array $r): array => $this->profilePayload($r), $rows)]);
    }

    private function createProfile(ServerRequestInterface $request, int $parentId): JsonResponse
    {
        $body = $this->body($request); $name = trim((string)($body['displayName'] ?? '')); $avatar = trim((string)($body['avatar'] ?? 'rocket'));
        if ($name === '' || mb_strlen($name) > 80) return $this->json(['error' => 'Invalid displayName.'], 400);
        $now = time(); $this->connection(self::PROFILE_TABLE)->insert(self::PROFILE_TABLE, ['pid' => 0, 'tstamp' => $now, 'crdate' => $now, 'parent' => $parentId, 'display_name' => $name, 'avatar' => $avatar]);
        return $this->json(['profile' => $this->profilePayload(['uid' => $this->connection(self::PROFILE_TABLE)->lastInsertId(), 'display_name' => $name, 'avatar' => $avatar, 'crdate' => $now, 'tstamp' => $now])], 201);
    }

    private function updateProfile(ServerRequestInterface $request, int $profileId): JsonResponse
    {
        $body = $this->body($request); $fields = [];
        if (isset($body['displayName'])) { $name = trim((string)$body['displayName']); if ($name === '' || mb_strlen($name) > 80) return $this->json(['error' => 'Invalid displayName.'], 400); $fields['display_name'] = $name; }
        if (isset($body['avatar'])) $fields['avatar'] = trim((string)$body['avatar']);
        if ($fields !== []) { $fields['tstamp'] = time(); $this->connection(self::PROFILE_TABLE)->update(self::PROFILE_TABLE, $fields, ['uid' => $profileId]); }
        return $this->json(['ok' => true]);
    }

    private function deleteProfile(int $profileId): JsonResponse
    {
        $this->connection(self::PROFILE_TABLE)->update(self::PROFILE_TABLE, ['deleted' => 1, 'tstamp' => time()], ['uid' => $profileId]);
        return $this->json(['ok' => true]);
    }

    private function getProgress(int $profileId): JsonResponse
    {
        $row = $this->progressRow($profileId); return $this->json(['profileId' => 'child_' . $profileId, 'revision' => (int)($row['revision'] ?? 0), 'updatedAt' => isset($row['tstamp']) ? date(DATE_ATOM, (int)$row['tstamp']) : null, 'data' => $row ? (json_decode((string)$row['progress_data'], true) ?: []) : []]);
    }

    private function putProgress(ServerRequestInterface $request, int $profileId): JsonResponse
    {
        $body = $this->body($request); $data = $body['data'] ?? null; if (!is_array($data)) return $this->json(['error' => 'Invalid data.'], 400);
        $row = $this->progressRow($profileId); $base = (int)($body['baseRevision'] ?? 0); if ($row !== null && $base !== (int)$row['revision']) return $this->json(['error' => 'revision_conflict', 'current' => ['revision' => (int)$row['revision'], 'data' => json_decode((string)$row['progress_data'], true) ?: []]], 409);
        $revision = (int)($row['revision'] ?? 0) + 1; $now = time(); $fields = ['tstamp' => $now, 'revision' => $revision, 'client_mutation_id' => trim((string)($body['clientMutationId'] ?? '')), 'progress_data' => json_encode($data, JSON_THROW_ON_ERROR)];
        $connection = $this->connection('tx_pipliobackend_progress'); if ($row === null) $connection->insert('tx_pipliobackend_progress', ['pid' => 0, 'crdate' => $now, 'profile' => $profileId] + $fields); else $connection->update('tx_pipliobackend_progress', $fields, ['profile' => $profileId]);
        return $this->getProgress($profileId);
    }

    private function authenticate(ServerRequestInterface $request): ?int
    {
        $token = preg_match('/^\s*Bearer\s+(.+)\s*$/i', $request->getHeaderLine('Authorization'), $m) ? trim($m[1]) : '';
        if ($token === '') return null;
        $signedParentId = $this->verifyToken($token);
        if ($signedParentId !== null) return $signedParentId;
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_pipliobackend_parent');
        $rows = $qb->select('uid', 'session_token_hash')->from('tx_pipliobackend_parent')
            ->where($qb->expr()->gt('session_expires', $qb->createNamedParameter(time(), Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery()->fetchAllAssociative();
        $hash = hash('sha512', $token);
        foreach ($rows as $row) {
            $storedHash = trim((string)($row['session_token_hash'] ?? ''));
            if ($storedHash !== '' && hash_equals($storedHash, $hash)) return (int)$row['uid'];
        }
        return null;
    }

    private function issueToken(int $parentId, int $expires): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['parentId' => $parentId, 'expires' => $expires], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payload, $this->encryptionKey());
        return $payload . '.' . $signature;
    }

    private function verifyToken(string $token): ?int
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;
        [$payload, $signature] = $parts;
        if (!hash_equals(hash_hmac('sha256', $payload, $this->encryptionKey()), $signature)) return null;
        $decoded = json_decode((string)base64_decode(strtr($payload, '-_', '+/')), true);
        if (!is_array($decoded) || (int)($decoded['expires'] ?? 0) < time() || (int)($decoded['parentId'] ?? 0) < 1) return null;
        return $this->parentExists((int)$decoded['parentId']) ? (int)$decoded['parentId'] : null;
    }

    private function parentExists(int $parentId): bool
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_pipliobackend_parent');
        return (bool)$qb->select('uid')->from('tx_pipliobackend_parent')->where($qb->expr()->eq('uid', $qb->createNamedParameter($parentId, Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))->executeQuery()->fetchOne();
    }

    private function encryptionKey(): string
    {
        return (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? 'piplio-account-key');
    }

    private function loginCodeHtml(string $code): string
    {
        $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!doctype html><html lang="de"><body style="margin:0;background:#f5f3ff;font-family:Arial,sans-serif;color:#202033">'
            . '<div style="max-width:560px;margin:32px auto;padding:0 18px"><div style="background:#6C47FF;border-radius:22px 22px 0 0;padding:26px 30px;color:#fff">'
            . '<div style="font-size:28px;font-weight:800;letter-spacing:.5px">Piplio</div><div style="margin-top:5px;opacity:.9;font-size:14px">Lernen mit Freude</div></div>'
            . '<div style="background:#fff;border-radius:0 0 22px 22px;padding:34px 30px;box-shadow:0 8px 30px rgba(38,25,94,.12)">'
            . '<h1 style="margin:0 0 12px;font-size:24px;color:#26213f">Dein Anmeldecode</h1>'
            . '<p style="font-size:16px;line-height:1.6;margin:0 0 24px">Mit diesem Code meldest du dich sicher bei Piplio an:</p>'
            . '<div style="background:#f0edff;border:2px solid #d9d1ff;border-radius:16px;text-align:center;padding:18px;margin:0 0 24px"><span style="font-size:34px;letter-spacing:8px;font-weight:800;color:#6C47FF">' . $safeCode . '</span></div>'
            . '<p style="font-size:14px;line-height:1.6;color:#625d75;margin:0">Der Code ist fünf Minuten gültig und kann nur einmal verwendet werden. Wenn du diese Anmeldung nicht angefordert hast, kannst du diese E-Mail ignorieren.</p>'
            . '<p style="font-size:13px;color:#938da8;margin:28px 0 0">Dein Piplio-Team</p></div></div></body></html>';
    }
    private function ownsProfile(int $parentId, int $profileId): bool { $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::PROFILE_TABLE); return (bool)$qb->select('uid')->from(self::PROFILE_TABLE)->where($qb->expr()->eq('uid', $qb->createNamedParameter($profileId, Connection::PARAM_INT)), $qb->expr()->eq('parent', $qb->createNamedParameter($parentId, Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))->executeQuery()->fetchOne(); }
    private function profilePayload(array $r): array { return ['id' => 'child_' . (int)$r['uid'], 'displayName' => (string)$r['display_name'], 'avatar' => (string)$r['avatar'], 'createdAt' => date(DATE_ATOM, (int)$r['crdate']), 'updatedAt' => date(DATE_ATOM, (int)$r['tstamp']), 'revision' => 0]; }
    private function progressRow(int $profileId): ?array { $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_pipliobackend_progress'); return $qb->select('*')->from('tx_pipliobackend_progress')->where($qb->expr()->eq('profile', $qb->createNamedParameter($profileId, Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))->executeQuery()->fetchAssociative() ?: null; }
    private function findParentByEmail(string $email): ?array { $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_pipliobackend_parent'); return $qb->select('*')->from('tx_pipliobackend_parent')->where($qb->expr()->eq('email', $qb->createNamedParameter($email)))->orderBy('deleted')->setMaxResults(1)->executeQuery()->fetchAssociative() ?: null; }
    private function connection(string $table): Connection { return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table); }
    private function body(ServerRequestInterface $request): array { $decoded = json_decode((string)$request->getBody(), true); return is_array($decoded) ? $decoded : []; }
    private function json(array $data, int $status = 200): JsonResponse { return (new JsonResponse($data, $status))->withHeader('Cache-Control', 'no-store, private')->withHeader('Pragma', 'no-cache')->withHeader('Expires', '0')->withHeader('Access-Control-Allow-Origin', '*')->withHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS')->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Piplio-Api-Key')->withHeader('Access-Control-Max-Age', '86400'); }
}
