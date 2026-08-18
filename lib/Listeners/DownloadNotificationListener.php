<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Listeners;

use OCA\Abonnieren\Service\SubscriptionService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\BeforeZipCreatedEvent;
use OCP\Files\Events\Node\BeforeNodeReadEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\ISharedStorage;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<BeforeNodeReadEvent|BeforeZipCreatedEvent|Event>
 */
class DownloadNotificationListener implements IEventListener {
	private ICache $cache;

	public function __construct(
		private IMailer $mailer,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private SubscriptionService $subscriptionService,
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private IURLGenerator $urlGenerator,
		private ISession $session,
		private IRequest $request,
		ICacheFactory $cacheFactory,
	) {
		$this->cache = $cacheFactory->createDistributed('abonnieren_download_notifications');
	}

	public function handle(Event $event): void {
		if ($event instanceof BeforeZipCreatedEvent) {
			$this->handleZipDownload($event);
			return;
		}

		if ($event instanceof BeforeNodeReadEvent) {
			$this->handleFileDownload($event);
		}
	}

	private function handleZipDownload(BeforeZipCreatedEvent $event): void {
		// If explicit files were selected, their BeforeNodeReadEvent instances
		// create the individual notifications. An empty list means that the
		// whole folder was requested as one ZIP download.
		if (count($event->getFiles()) !== 0) {
			return;
		}

		$folder = $event->getFolder();
		if (!$folder instanceof Folder) {
			return;
		}

		$share = $this->getPublicLinkShare($folder);
		if ($share === null && $this->userSession->getUser() === null) {
			return;
		}

		$this->cache->set('request:' . $this->request->getId(), $folder->getPath(), 3600);
		$this->notify($share, $folder, $this->resolveSubscriptionNode($folder, $share));
	}

	private function handleFileDownload(BeforeNodeReadEvent $event): void {
		$node = $event->getNode();
		if (!$node instanceof File) {
			return;
		}

		$share = $this->getPublicLinkShare($node);
		$user = $this->userSession->getUser();
		if ($share === null && $user === null) {
			return;
		}

		$folderPath = $this->cache->get('request:' . $this->request->getId());
		if (is_string($folderPath) && str_starts_with($node->getPath(), $folderPath)) {
			return;
		}

		// Avoid repeated emails for browser/video range requests in the same
		// public-link session. The remote address is hashed into the key and is
		// never stored or included in the message.
		$accessKey = $share !== null
			? 'share:' . (string)$share->getId()
			: 'user:' . $user->getUID();
		$visitorKey = hash('sha256', implode('|', [
			$accessKey,
			(string)$node->getId(),
			$this->session->getId(),
			$this->request->getRemoteAddress(),
		]));
		$cacheKey = 'range:' . $visitorKey;
		if ($this->request->getHeader('range') !== '' && $this->cache->get($cacheKey) === 'true') {
			return;
		}
		$this->cache->set($cacheKey, 'true', 3600);

		$this->notify($share, $node, $this->resolveSubscriptionNode($node, $share));
	}

	private function getPublicLinkShare(Node $node): ?IShare {
		try {
			$storage = $node->getStorage();
		} catch (NotFoundException $e) {
			return null;
		}

		if (!$storage->instanceOfStorage(ISharedStorage::class)) {
			return null;
		}

		/** @var ISharedStorage $storage */
		$share = $storage->getShare();
		return $share->getShareType() === IShare::TYPE_LINK ? $share : null;
	}

	private function notify(?IShare $share, File|Folder $node, Node $subscriptionNode): void {
		$recipients = array_values($this->subscriptionService->getRecipientEmailsForNode(
			$subscriptionNode,
			SubscriptionService::EVENT_DOWNLOAD,
			$this->userSession->getUser()?->getUID(),
		));
		if ($recipients === []) {
			return;
		}

		try {
			$isFolder = $node instanceof Folder;
			$isPublicLink = $share !== null;
			$subject = $isFolder
				? ($isPublicLink
					? $this->l10n->t('Public share folder downloaded')
					: $this->l10n->t('Folder downloaded'))
				: ($isPublicLink
					? $this->l10n->t('Public share file downloaded')
					: $this->l10n->t('File downloaded'));
			$description = $isFolder
				? ($isPublicLink
					? $this->l10n->t('A folder was downloaded through one of your public share links.')
					: $this->l10n->t('A folder covered by one of your subscriptions was downloaded.'))
				: ($isPublicLink
					? $this->l10n->t('A file was downloaded through one of your public share links.')
					: $this->l10n->t('A file covered by one of your subscriptions was downloaded.'));

			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$template = $this->mailer->createEMailTemplate('abonnieren_download');
			$template->setSubject($subject);
			$template->addHeader();
			$template->addHeading($subject);
			$template->addBodyText($description);
			$template->addBodyListItem($this->l10n->t('Name:') . ' ' . $node->getName());
			$template->addBodyListItem($this->l10n->t('Path:') . ' ' . $this->getUserRelativePath($subscriptionNode));
			if ($node instanceof File) {
				$template->addBodyListItem($this->l10n->t('Size:') . ' ' . $this->formatSize($node->getSize()));
			}
			$template->addBodyListItem($this->l10n->t('Downloaded by:') . ' ' . $this->getActorDisplayName($isPublicLink));
			$template->addBodyListItem($this->l10n->t('Time:') . ' ' . date('d.m.Y H:i:s'));

			$token = $share?->getToken();
			if ($share !== null && is_string($token) && $token !== '') {
				$template->addBodyButton(
					$this->l10n->t('Open public share'),
					$this->urlGenerator->linkToRouteAbsolute(
						'files_sharing.sharecontroller.showShare',
						['token' => $token],
					),
				);
			} else {
				$template->addBodyButton(
					$this->l10n->t('Open file'),
					$this->urlGenerator->linkToRouteAbsolute(
						'files.viewcontroller.showFile',
						[
							'dir' => $subscriptionNode instanceof Folder
								? $this->getUserRelativePath($subscriptionNode)
								: dirname($this->getUserRelativePath($subscriptionNode)),
							'fileid' => (string)$subscriptionNode->getId(),
						],
					),
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
			$this->logger->error('Could not send download notification', [
				'app' => 'abonnieren',
				'shareId' => $share !== null ? (int)$share->getId() : null,
				'nodeId' => (int)$node->getId(),
				'exception' => $e,
			]);
		}
	}

	private function resolveSubscriptionNode(Node $node, ?IShare $share): Node {
		if ($share === null) {
			return $node;
		}

		try {
			$shareNode = $share->getNode();
			if ($shareNode->getId() === $node->getId()) {
				return $shareNode;
			}

			$ownerFolder = $this->rootFolder->getUserFolder($share->getSharedBy());
			$matches = $ownerFolder->getById($node->getId());
			if ($matches !== []) {
				return $matches[0];
			}

			return $shareNode;
		} catch (\Throwable $e) {
			$this->logger->debug('Could not resolve owner node for download subscription', [
				'app' => 'abonnieren',
				'nodeId' => (int)$node->getId(),
				'exception' => $e,
			]);
			return $node;
		}
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

		return $publicLinkActivity
			? $this->l10n->t('Anonymous visitor')
			: $this->l10n->t('Unknown');
	}

	private function formatSize(int|float $bytes): string {
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$bytes = max($bytes, 0);
		$power = $bytes > 0 ? (int)floor(log($bytes, 1024)) : 0;
		$power = min($power, count($units) - 1);
		$value = $bytes / (1024 ** $power);

		return round($value, 2) . ' ' . $units[$power];
	}
}
