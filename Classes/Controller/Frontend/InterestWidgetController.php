<?php

declare(strict_types=1);

namespace Aistea\PiplioBackend\Controller\Frontend;

use Aistea\PiplioBackend\Service\InterestLeadService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class InterestWidgetController extends ActionController
{
    public function __construct(
        private readonly InterestLeadService $interestLeadService,
    ) {}

    public function showAction(string $status = ''): ResponseInterface
    {
        return $this->renderShowResponse($status);
    }

    public function submitAction(
        string $name = '',
        string $email = '',
        string $pageTitle = '',
        string $pageUrl = '',
        int $pageId = 0,
        bool $privacyAccepted = false,
        string $website = '',
        int $formTimestamp = 0,
        string $privacyVersion = ''
    ): ResponseInterface {
        $isAjaxRequest = $this->isAjaxRequest();
        $result = $this->interestLeadService->processSubmission(
            [
                'name' => $name,
                'email' => $email,
                'pageTitle' => $pageTitle,
                'pageUrl' => $pageUrl,
                'pageId' => $pageId,
                'privacyAccepted' => $privacyAccepted,
                'website' => $website,
                'formTimestamp' => $formTimestamp,
                'privacyVersion' => $privacyVersion,
            ],
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            $this->resolveSourcePageId()
        );

        if ((bool)$result['ok']) {
            return $this->respondSuccess((string)$result['message'], $isAjaxRequest);
        }

        return $this->respondError((string)$result['message'], $isAjaxRequest, (int)$result['status']);
    }

    private function renderShowResponse(string $status = ''): ResponseInterface
    {
        $pageId = $this->resolveSourcePageId();

        $this->view->assignMultiple([
            'pageId' => $pageId,
            'pageTitle' => (string)($GLOBALS['TSFE']->page['title'] ?? ''),
            'pageUrl' => (string)$this->request->getUri(),
            'widgetId' => 'piplio-interest-widget-' . $pageId,
            'formTimestamp' => time(),
            'openPanel' => $status !== '',
            'privacyVersion' => $this->interestLeadService->resolvePrivacyVersion(),
        ]);

        return $this->htmlResponse();
    }

    private function respondSuccess(string $message, bool $isAjaxRequest): ResponseInterface
    {
        if ($isAjaxRequest) {
            return new JsonResponse([
                'ok' => true,
                'message' => $message,
            ]);
        }

        $this->addFlashMessage($message, '', AbstractMessage::OK);
        return $this->renderShowResponse('success');
    }

    private function respondError(string $message, bool $isAjaxRequest, int $statusCode = 422): ResponseInterface
    {
        if ($isAjaxRequest) {
            return new JsonResponse([
                'ok' => false,
                'message' => $message,
            ], $statusCode);
        }

        $this->addFlashMessage($message, '', AbstractMessage::ERROR);
        return $this->renderShowResponse('error');
    }

    private function isAjaxRequest(): bool
    {
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function resolveSourcePageId(): int
    {
        return (int)($GLOBALS['TSFE']->id ?? 0);
    }
}
