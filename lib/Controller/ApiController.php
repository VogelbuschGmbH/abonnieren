<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Controller;

use OCA\Abonnieren\Service\SubscriptionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * @psalm-suppress UnusedClass
 */
class ApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private IManager $shareManager,
		private IUserSession $userSession,
		private SubscriptionService $subscriptionService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Enable email notifications for downloads through one public link share.
	 *
	 * The authenticated user is derived from the app-password session. The
	 * supplied share ID is accepted only when it belongs to that user and is a
	 * public link share.
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/download-notifications')]
	public function enableDownloadNotification(string $shareId): DataResponse {
		return $this->enableShareNotifications($shareId, (string)SubscriptionService::EVENT_DOWNLOAD);
	}

	/**
	 * Enable selected email notifications for one public link share.
	 *
	 * Event mask: upload=1, modification=2, deletion=4, download=8.
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/share-notifications')]
	public function enableShareNotifications(string $shareId, string $eventMask): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['enabled' => false, 'message' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		}

		if (!ctype_digit($shareId) || (int)$shareId < 1) {
			return new DataResponse(['enabled' => false, 'message' => 'Invalid share ID'], Http::STATUS_BAD_REQUEST);
		}
		if (!ctype_digit($eventMask) || (int)$eventMask < 1 || (((int)$eventMask & ~SubscriptionService::ALLOWED_EVENTS) !== 0)) {
			return new DataResponse(['enabled' => false, 'message' => 'Invalid notification event mask'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$share = $this->shareManager->getShareById('ocinternal:' . $shareId, $user->getUID());
		} catch (ShareNotFound $e) {
			return new DataResponse(['enabled' => false, 'message' => 'Share not found'], Http::STATUS_NOT_FOUND);
		}

		if ($share->getShareType() !== IShare::TYPE_LINK) {
			return new DataResponse(['enabled' => false, 'message' => 'Only public link shares are supported'], Http::STATUS_BAD_REQUEST);
		}

		if ($share->getSharedBy() !== $user->getUID()) {
			return new DataResponse(['enabled' => false, 'message' => 'Share does not belong to the authenticated user'], Http::STATUS_FORBIDDEN);
		}

		try {
			$node = $share->getNode();
			$isFolder = $node instanceof \OCP\Files\Folder;
			$normalizedMask = (int)$eventMask;
			if (!$isFolder) {
				$normalizedMask &= ~SubscriptionService::EVENT_UPLOAD;
			}
			if ($normalizedMask < 1) {
				throw new \InvalidArgumentException('No supported event selected');
			}
			$this->subscriptionService->upsertRule(
				$user->getUID(),
				(int)$node->getId(),
				$normalizedMask,
				$isFolder,
			);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['enabled' => false, 'message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([
			'enabled' => true,
			'shareId' => (int)$share->getId(),
			'nodeId' => (int)$node->getId(),
			'eventMask' => $normalizedMask,
		]);
	}
}
