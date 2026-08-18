<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Listeners;

use OCA\Abonnieren\Service\SubscriptionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\ISharedStorage;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<NodeCreatedEvent|NodeDeletedEvent|NodeWrittenEvent|Event> */
class FileUploadListener implements IEventListener {
	private ICache $cache;

	public function __construct(
		private IMailer $mailer,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private SubscriptionService $subscriptionService,
		private IURLGenerator $urlGenerator,
		ICacheFactory $cacheFactory,
		private IUserSession $userSession,
		private IRequest $request,
		private IManager $shareManager,
	) {
		$this->cache = $cacheFactory->createLocal('abonnieren_file_event_debounce');
	}

	public function handle(Event $event): void {
		[$eventName, $eventBit] = match (true) {
			$event instanceof NodeCreatedEvent => ['created', SubscriptionService::EVENT_UPLOAD],
			$event instanceof NodeDeletedEvent => ['deleted', SubscriptionService::EVENT_DELETION],
			$event instanceof NodeWrittenEvent => ['modified', SubscriptionService::EVENT_MODIFICATION],
			default => [null, 0],
		};
		if ($eventName === null) {
			return;
		}

		$node = $event->getNode();
		if (!$node instanceof File && !$node instanceof Folder) {
			return;
		}

		// Folder write events are implementation details and would create noisy
		// modification emails. Folder creation and deletion remain meaningful.
		if ($event instanceof NodeWrittenEvent && $node instanceof Folder) {
			return;
		}

		$user = $this->userSession->getUser();
		$publicShare = $this->getPublicLinkShare($node);
		if ($user === null && $publicShare === null) {
			return;
		}

		$actorKey = $user?->getUID() ?? ('share_' . $publicShare->getId());
		$cacheKey = implode(':', [$eventName, (string)$node->getId(), $actorKey]);
		if ($this->cache->get($cacheKey) === true) {
			return;
		}
		$this->cache->set($cacheKey, true, 10);

		$recipients = array_values($this->subscriptionService->getRecipientEmailsForNode(
			$node,
			$eventBit,
			$user?->getUID(),
		));
		if ($recipients !== []) {
			$this->sendNotification($node, $recipients, $eventName, $publicShare !== null);
		}

		if ($event instanceof NodeDeletedEvent) {
			$this->subscriptionService->deleteRulesForNode((int)$node->getId());
		}
	}

	private function getPublicLinkShare(Node $node): ?IShare {
		try {
			$storage = $node->getStorage();
			if ($storage->instanceOfStorage(ISharedStorage::class)) {
				/** @var ISharedStorage $storage */
				$share = $storage->getShare();
				if ($share->getShareType() === IShare::TYPE_LINK) {
					return $share;
				}
			}
		} catch (NotFoundException $e) {
			// Continue with request-token lookup below.
		}

		$token = (string)$this->request->getParam('token', '');
		if ($token === '') {
			return null;
		}

		try {
			$share = $this->shareManager->getShareByToken($token);
			if ($share->getShareType() !== IShare::TYPE_LINK) {
				return null;
			}

			$shareNode = $share->getNode();
			if ($shareNode->getId() === $node->getId()) {
				return $share;
			}

			$sharePath = rtrim($shareNode->getPath(), '/') . '/';
			return str_starts_with($node->getPath(), $sharePath) ? $share : null;
		} catch (ShareNotFound|NotFoundException $e) {
			return null;
		}
	}

	/** @param list<string> $recipients */
	private function sendNotification(Node $node, array $recipients, string $eventName, bool $publicLinkActivity): void {
		try {
			$isFolder = $node instanceof Folder;
			$path = $this->getUserRelativePath($node);
			$subject = $this->getSubject($isFolder, $eventName);
			$description = $this->getDescription($publicLinkActivity);

			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$template = $this->mailer->createEMailTemplate('abonnieren_file_event');
			$template->setSubject($subject);
			$template->addHeader();
			$template->addHeading($subject);
			$template->addBodyText($description);
			$template->addBodyListItem($this->l10n->t('Name:') . ' ' . $node->getName());
			$template->addBodyListItem($this->l10n->t('Path:') . ' ' . dirname($path));
			$template->addBodyListItem($this->l10n->t('Size:') . ' ' . $this->formatSize($node->getSize()));
			$template->addBodyListItem($this->l10n->t('Type:') . ' ' . ($node instanceof File ? $node->getMimeType() : $this->l10n->t('Folder')));
			$template->addBodyListItem($this->l10n->t('Changed by:') . ' ' . $this->getActorDisplayName($publicLinkActivity));
			$template->addBodyListItem($this->l10n->t('Time:') . ' ' . date('d.m.Y H:i:s'));

			if ($eventName !== 'deleted') {
				$template->addBodyButton(
					$this->l10n->t('Open file'),
					$this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', [
						'dir' => $isFolder ? $path : dirname($path),
						'fileid' => (string)$node->getId(),
					]),
				);
			}
			$template->addFooter();
			$message->setBody($template->renderText(), 'text/plain');
			$message->setHtmlBody($template->renderHtml());

			foreach ($recipients as $recipient) {
				$message->setTo([$recipient]);
				$this->mailer->send($message);
			}
		} catch (\Throwable $e) {
			$this->logger->error('Could not send file event notification', [
				'app' => 'abonnieren',
				'nodeId' => (int)$node->getId(),
				'event' => $eventName,
				'exception' => $e,
			]);
		}
	}

	private function getSubject(bool $isFolder, string $eventName): string {
		return match ($eventName) {
			'created' => $this->l10n->t($isFolder ? 'Folder created' : 'File uploaded'),
			'deleted' => $this->l10n->t($isFolder ? 'Folder deleted' : 'File deleted'),
			default => $this->l10n->t($isFolder ? 'Folder modified' : 'File modified'),
		};
	}

	private function getDescription(bool $publicLinkActivity): string {
		return $publicLinkActivity
			? $this->l10n->t('The event occurred through a public share link.')
			: $this->l10n->t('The event occurred within the scope of a subscription.');
	}

	private function getUserRelativePath(Node $node): string {
		$parts = explode('/', trim($node->getPath(), '/'));
		return '/' . implode('/', array_slice($parts, 2));
	}

	private function getActorDisplayName(bool $publicLinkActivity): string {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $user->getDisplayName();
		}
		return $publicLinkActivity ? $this->l10n->t('Anonymous visitor') : $this->l10n->t('Unknown');
	}

	private function formatSize(int|float $bytes): string {
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$bytes = max($bytes, 0);
		$power = $bytes > 0 ? (int)floor(log($bytes, 1024)) : 0;
		$power = min($power, count($units) - 1);
		return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
	}
}
