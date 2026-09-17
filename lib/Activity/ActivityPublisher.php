<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Activity;

use OCA\Abonnieren\AppInfo\Application;
use OCP\Activity\IManager;
use OCP\Files\Node;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/** Publishes a file-object activity so downloads appear in the Files sidebar. */
class ActivityPublisher {
	public function __construct(
		private IManager $activityManager,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
	}

	public function publishDownload(?IShare $share, Node $ownerNode): void {
		if ($share !== null && in_array($share->getShareType(), [IShare::TYPE_LINK, IShare::TYPE_EMAIL], true)) {
			return;
		}

		$actor = $this->userSession->getUser();
		if ($actor === null) {
			return;
		}

		$fileId = (int)$ownerNode->getId();
		if ($fileId < 1) {
			return;
		}

		$path = $this->getUserRelativePath($ownerNode);
		$affected = [];
		$ownerId = $ownerNode->getOwner()?->getUID();
		if (is_string($ownerId) && $ownerId !== '') {
			$affected[$ownerId] = true;
		}
		if ($share !== null) {
			foreach ([$share->getSharedBy(), $share->getShareOwner()] as $userId) {
				if (is_string($userId) && $userId !== '') {
					$affected[$userId] = true;
				}
			}
		}
		foreach ($this->getAdminUserIds() as $userId) {
			$affected[$userId] = true;
		}

		unset($affected[$actor->getUID()]);
		if ($affected === []) {
			return;
		}

		foreach (array_keys($affected) as $userId) {
			try {
				$event = $this->activityManager->generateEvent();
				$event->setApp(Application::APP_ID)
					->setType(Setting::IDENTIFIER)
					->setAuthor($actor->getUID())
					->setAffectedUser($userId)
					->setSubject(Provider::SUBJECT_DOWNLOADED, [
						'user' => $actor->getUID(),
						'file' => [
							'id' => $fileId,
							'path' => $path,
						],
					])
					->setObject('files', $fileId, $path);
				$this->activityManager->publish($event);
			} catch (\Throwable $e) {
				$this->logger->debug('Could not publish download activity', [
					'app' => Application::APP_ID,
					'nodeId' => $fileId,
					'exception' => $e,
				]);
			}
		}
	}

	/** @return list<string> */
	private function getAdminUserIds(): array {
		$group = $this->groupManager->get('admin');
		if ($group === null) {
			return [];
		}

		$ids = [];
		foreach ($group->getUsers() as $admin) {
			$ids[] = $admin->getUID();
		}

		return $ids;
	}

	private function getUserRelativePath(Node $node): string {
		$parts = explode('/', trim($node->getPath(), '/'));
		return '/' . implode('/', array_slice($parts, 2));
	}
}
