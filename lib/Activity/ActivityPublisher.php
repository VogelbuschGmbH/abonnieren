<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Activity;

use OCA\Abonnieren\AppInfo\Application;
use OCP\Activity\IManager;
use OCP\Files\Node;
use Psr\Log\LoggerInterface;

/** Publishes file-object activity when a subscription email is sent. */
class ActivityPublisher {
	public function __construct(
		private IManager $activityManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<string> $recipientUserIds User ids that received the email
	 */
	public function publishForRecipients(
		string $subject,
		Node $ownerNode,
		?string $actorUserId,
		array $recipientUserIds,
	): void {
		$fileId = (int)$ownerNode->getId();
		if ($fileId < 1 || $recipientUserIds === []) {
			return;
		}

		$path = $this->getUserRelativePath($ownerNode);
		$author = $actorUserId ?? '';

		foreach (array_unique($recipientUserIds) as $userId) {
			if ($userId === '' || $userId === $author) {
				continue;
			}

			try {
				$event = $this->activityManager->generateEvent();
				$event->setApp(Application::APP_ID)
					->setType(Setting::IDENTIFIER)
					->setAuthor($author)
					->setAffectedUser($userId)
					->setSubject($subject, [
						'user' => $author,
						'file' => [
							'id' => $fileId,
							'path' => $path,
						],
					])
					->setObject('files', $fileId, $path);
				$this->activityManager->publish($event);
			} catch (\Throwable $e) {
				$this->logger->debug('Could not publish subscription activity', [
					'app' => Application::APP_ID,
					'nodeId' => $fileId,
					'subject' => $subject,
					'exception' => $e,
				]);
			}
		}
	}

	private function getUserRelativePath(Node $node): string {
		$parts = explode('/', trim($node->getPath(), '/'));
		return '/' . implode('/', array_slice($parts, 2));
	}
}
