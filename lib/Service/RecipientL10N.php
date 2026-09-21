<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Service;

use OCA\Abonnieren\AppInfo\Application;
use OCP\IConfig;
use OCP\IL10N;
use OCP\L10N\IFactory;

/** Resolves app translations for a subscriber, not the current request user. */
class RecipientL10N {
	public function __construct(
		private IFactory $l10nFactory,
		private IConfig $config,
	) {
	}

	public function forUser(string $userId): IL10N {
		$candidates = [
			$this->config->getUserValue($userId, 'core', 'lang', ''),
			$this->config->getSystemValueString('default_language', 'en'),
			'en',
		];

		$language = 'en';
		foreach ($candidates as $candidate) {
			if (!is_string($candidate) || $candidate === '') {
				continue;
			}
			$resolved = $this->resolveAvailableLanguage($candidate);
			if ($resolved !== null) {
				$language = $resolved;
				break;
			}
		}

		return $this->l10nFactory->get(Application::APP_ID, $language);
	}

	/**
	 * Factory::get() falls back to the current request/actor language when the
	 * exact code is missing. Regional German profiles (de_AT, de_DE, de_CH, …)
	 * must use de.json. Never pass an unknown code.
	 */
	private function resolveAvailableLanguage(string $lang): ?string {
		$normalized = str_replace('-', '_', $lang);
		$tries = array_unique(array_filter([
			$lang,
			$normalized,
			strtolower($normalized),
			explode('_', $normalized)[0],
			strtolower(explode('_', $normalized)[0]),
		], static fn (string $code): bool => $code !== ''));

		foreach ($tries as $try) {
			if ($this->l10nFactory->languageExists(Application::APP_ID, $try)) {
				return $try;
			}
		}

		return null;
	}
}
