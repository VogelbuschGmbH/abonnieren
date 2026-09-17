<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Activity;

use OCA\Abonnieren\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {
	public const SUBJECT_DOWNLOADED = 'downloaded';

	public function __construct(
		private IFactory $languageFactory,
		private IURLGenerator $url,
		private IManager $activityManager,
		private IEventMerger $eventMerger,
		private IUserManager $userManager,
	) {
	}

	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID || $event->getType() !== Setting::IDENTIFIER) {
			throw new UnknownActivityException();
		}

		$l = $this->languageFactory->get(Application::APP_ID, $language);
		$parameters = $this->getParsedParameters($event);
		$icon = $this->activityManager->getRequirePNG() ? 'download.png' : 'download.svg';
		$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/' . $icon)));

		if ($this->activityManager->isFormattingFilteredObject()) {
			$subject = $l->t('Downloaded by {user}');
			$this->setSubjects($event, $subject, ['user' => $parameters['user']]);
			return $event;
		}

		$currentUser = $this->activityManager->getCurrentUserId();
		if ($parameters['user']['id'] === $currentUser) {
			$subject = $l->t('You downloaded {file}');
			unset($parameters['user']);
		} else {
			$subject = $l->t('{user} downloaded {file}');
		}
		$this->setSubjects($event, $subject, $parameters);

		return $this->eventMerger->mergeEvents('user', $event, $previousEvent);
	}

	/**
	 * @return array{user: array<string, string>, file: array<string, string>}
	 */
	private function getParsedParameters(IEvent $event): array {
		$parameters = $event->getSubjectParameters();
		$userId = (string)($parameters['user'] ?? '');
		$file = is_array($parameters['file'] ?? null) ? $parameters['file'] : [];
		$path = (string)($file['path'] ?? $event->getObjectName());
		$fileId = (string)($file['id'] ?? $event->getObjectId());

		return [
			'user' => [
				'type' => 'user',
				'id' => $userId,
				'name' => $this->userManager->getDisplayName($userId) ?? $userId,
			],
			'file' => [
				'type' => 'file',
				'id' => $fileId,
				'name' => basename($path) !== '' ? basename($path) : $path,
				'path' => ltrim($path, '/'),
				'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $fileId]),
			],
		];
	}

	private function setSubjects(IEvent $event, string $subject, array $parameters): void {
		$placeholders = [];
		$replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'];
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $parameters);
	}
}
