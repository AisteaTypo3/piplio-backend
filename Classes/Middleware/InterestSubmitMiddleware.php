<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Middleware;

use Aistea\PiplioBackend\Service\InterestLeadService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class InterestSubmitMiddleware implements MiddlewareInterface
{
    private const API_PREFIXES = ['/api/piplio/interest', '/api/piplio/v1/interest'];

    public function __construct(
        private readonly InterestLeadService $interestLeadService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!$this->matchesPath($path)) {
            return $handler->handle($request);
        }

        $method = strtoupper($request->getMethod());
        if ($method === 'OPTIONS') {
            return $this->withCorsHeaders(new JsonResponse(['ok' => true]));
        }

        if ($method !== 'POST') {
            return $this->withCorsHeaders(new JsonResponse([
                'ok' => false,
                'message' => (string)(LocalizationUtility::translate('message.methodNotAllowed', 'PiplioBackend') ?? 'Method not allowed.'),
            ], 405));
        }

        $input = $this->extractInput($request);
        $result = $this->interestLeadService->processSubmission(
            $input,
            (string)($request->getServerParams()['REMOTE_ADDR'] ?? ''),
            $request->getHeaderLine('User-Agent')
        );

        return $this->withCorsHeaders(new JsonResponse([
            'ok' => (bool)$result['ok'],
            'message' => (string)$result['message'],
        ], (int)$result['status']));
    }

    private function matchesPath(string $path): bool
    {
        foreach (self::API_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function withCorsHeaders(JsonResponse $response): JsonResponse
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With')
            ->withHeader('Access-Control-Max-Age', '86400');
    }

    private function extractInput(ServerRequestInterface $request): array
    {
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && $parsedBody !== []) {
            return $this->normalizeInput($parsedBody);
        }

        $rawBody = (string)$request->getBody();
        if ($rawBody === '') {
            return [];
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            return is_array($decoded) ? $this->normalizeInput($decoded) : [];
        }

        $input = [];
        parse_str($rawBody, $input);

        return is_array($input) ? $this->normalizeInput($input) : [];
    }

    private function normalizeInput(array $input): array
    {
        if (isset($input['name']) || isset($input['email'])) {
            return $input;
        }

        foreach ($input as $value) {
            if (is_array($value) && (isset($value['name']) || isset($value['email']))) {
                return $value;
            }
        }

        return $input;
    }
}
