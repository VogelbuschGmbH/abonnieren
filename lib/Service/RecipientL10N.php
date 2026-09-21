<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Service;

use OCA\Abonnieren\AppInfo\Application;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\L10N\IFactory;

/** Resolves app translations for a subscriber, not the current request user. */
class RecipientL10N {
	public function __construct(
		private IFactory $l10nFactory,
		private IUserManager $userManager,
	) {
	}

	public function forUser(string $userId): IL10N {
		$user = $this->userManager->get($userId);
		$language = $user !== null ? $this->l10nFactory->getUserLanguage($user) : null;
		return $this->l10nFactory->get(Application::APP_ID, $language);
	}
}
