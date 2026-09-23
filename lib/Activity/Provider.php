<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Activity;

use OCA\Abonnieren\AppInfo\Application;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {
	public const SUBJECT_DOWNLOADED = 'downloaded';
	public const SUBJECT_CREATED = 'created';
	public const SUBJECT_MODIFIED = 'modified';
	public const SUBJECT_DELETED = 'deleted';

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
		$parameters = $this->getParsedParameters($event, $l);
		$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'bell.svg')));

		$isAnonymous = ($parameters['user']['type'] ?? '') === 'highlight';
		$currentUser = $this->activityManager->getCurrentUserId();
		$isSelf = !$isAnonymous && ($parameters['user']['id'] ?? '') === $currentUser;

		if ($this->activityManager->isFormattingFilteredObject()) {
			$subject = $this->getShortSubject($l, $event->getSubject(), $isAnonymous);
			$subjectParameters = $isAnonymous ? [] : ['user' => $parameters['user']];
			$this->setSubjects($event, $subject, $subjectParameters);
			return $event;
		}

		$subject = $this->getLongSubject($l, $event->getSubject(), $isAnonymous, $isSelf);
		$subjectParameters = $parameters;
		if ($isSelf || $isAnonymous) {
			unset($subjectParameters['user']);
		}
		$this->setSubjects($event, $subject, $subjectParameters);

		return $this->eventMerger->mergeEvents('user', $event, $previousEvent);
	}

	private function getShortSubject(IL10N $l, string $subject, bool $isAnonymous): string {
		if ($isAnonymous) {
			return match ($subject) {
				self::SUBJECT_CREATED => $l->t('Created via public link'),
				self::SUBJECT_DELETED => $l->t('Deleted via public link'),
				self::SUBJECT_MODIFIED => $l->t('Modified via public link'),
				default => $l->t('Opened via public link'),
			};
		}

		return match ($subject) {
			self::SUBJECT_CREATED => $l->t('Created by {user}'),
			self::SUBJECT_DELETED => $l->t('Deleted by {user}'),
			self::SUBJECT_MODIFIED => $l->t('Modified by {user}'),
			default => $l->t('Opened by {user}'),
		};
	}

	private function getLongSubject(IL10N $l, string $subject, bool $isAnonymous, bool $isSelf): string {
		if ($isAnonymous) {
			return match ($subject) {
				self::SUBJECT_CREATED => $l->t('{file} was created via a public share link'),
				self::SUBJECT_DELETED => $l->t('{file} was deleted via a public share link'),
				self::SUBJECT_MODIFIED => $l->t('{file} was modified via a public share link'),
				default => $l->t('{file} was opened via a public share link'),
			};
		}

		if ($isSelf) {
			return match ($subject) {
				self::SUBJECT_CREATED => $l->t('You created {file}'),
				self::SUBJECT_DELETED => $l->t('You deleted {file}'),
				self::SUBJECT_MODIFIED => $l->t('You modified {file}'),
				default => $l->t('You opened {file}'),
			};
		}

		return match ($subject) {
			self::SUBJECT_CREATED => $l->t('{user} created {file}'),
			self::SUBJECT_DELETED => $l->t('{user} deleted {file}'),
			self::SUBJECT_MODIFIED => $l->t('{user} modified {file}'),
			default => $l->t('{user} opened {file}'),
		};
	}

	/**
	 * @return array{user: array<string, string>, file: array<string, string>}
	 */
	private function getParsedParameters(IEvent $event, IL10N $l): array {
		$parameters = $event->getSubjectParameters();
		$userId = (string)($parameters['user'] ?? '');
		$file = is_array($parameters['file'] ?? null) ? $parameters['file'] : [];
		$path = (string)($file['path'] ?? $event->getObjectName());
		$fileId = (string)($file['id'] ?? $event->getObjectId());

		return [
			'user' => $userId === ''
				? [
					'type' => 'highlight',
					'id' => 'guest',
					'name' => $l->t('Anonymous visitor'),
				]
				: [
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
