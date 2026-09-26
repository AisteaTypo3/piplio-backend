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
        if ($row === null) $connection->insert('tx_pipliobackend_parent', $fields);
        else $connection->update('tx_pipliobackend_parent', $fields, ['uid' => (int)$row['uid']]);
        $mail = GeneralUtility::makeInstance(MailMessage::class);
        $mail->setTo($email)->setSubject('Dein Piplio-Anmeldecode')->text("Dein Piplio-Code lautet: {$code}\n\nDer Code ist fünf Minuten gültig.");
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
        $token = bin2hex(random_bytes(32));
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

    private function authenticate(ServerRequestInterface $request): ?int { $token = preg_match('/^\s*Bearer\s+(.+)\s*$/i', $request->getHeaderLine('Authorization'), $m) ? trim($m[1]) : ''; if ($token === '') return null; $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_pipliobackend_parent'); $row = $qb->select('uid')->from('tx_pipliobackend_parent')->where($qb->expr()->eq('session_token_hash', $qb->createNamedParameter(hash('sha512', $token))), $qb->expr()->gt('session_expires', $qb->createNamedParameter(time(), Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))->executeQuery()->fetchAssociative(); return $row ? (int)$row['uid'] : null; }
    private function ownsProfile(int $parentId, int $profileId): bool { $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::PROFILE_TABLE); return (bool)$qb->select('uid')->from(self::PROFILE_TABLE)->where($qb->expr()->eq('uid', $qb->createNamedParameter($profileId, Connection::PARAM_INT)), $qb->expr()->eq('parent', $qb->createNamedParameter($parentId, Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))->executeQuery()->fetchOne(); }
    private function profilePayload(array $r): array { return ['id' => 'child_' . (int)$r['uid'], 'displayName' => (string)$r['display_name'], 'avatar' => (string)$r['avatar'], 'createdAt' => date(DATE_ATOM, (int)$r['crdate']), 'updatedAt' => date(DATE_ATOM, (int)$r['tstamp']), 'revision' => 0]; }
    private function progressRow(int $profileId): ?array { $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_pipliobackend_progress'); return $qb->select('*')->from('tx_pipliobackend_progress')->where($qb->expr()->eq('profile', $qb->createNamedParameter($profileId, Connection::PARAM_INT)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))->executeQuery()->fetchAssociative() ?: null; }
    private function findParentByEmail(string $email): ?array { $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_pipliobackend_parent'); return $qb->select('*')->from('tx_pipliobackend_parent')->where($qb->expr()->eq('email', $qb->createNamedParameter($email)), $qb->expr()->eq('deleted', $qb->createNamedParameter(0, Connection::PARAM_INT)))->executeQuery()->fetchAssociative() ?: null; }
    private function connection(string $table): Connection { return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table); }
    private function body(ServerRequestInterface $request): array { $decoded = json_decode((string)$request->getBody(), true); return is_array($decoded) ? $decoded : []; }
    private function json(array $data, int $status = 200): JsonResponse { return (new JsonResponse($data, $status))->withHeader('Cache-Control', 'no-store, private')->withHeader('Pragma', 'no-cache')->withHeader('Expires', '0')->withHeader('Access-Control-Allow-Origin', '*')->withHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS')->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Piplio-Api-Key')->withHeader('Access-Control-Max-Age', '86400'); }
}
