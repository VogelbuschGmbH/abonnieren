<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Activity;

use OCP\Activity\ISetting;
use OCP\IL10N;

class Setting implements ISetting {
	public const IDENTIFIER = 'file_downloaded';

	public function __construct(
		private IL10N $l10n,
	) {
	}

	public function getIdentifier(): string {
		return self::IDENTIFIER;
	}

	public function getName(): string {
		return $this->l10n->t('A file or folder was downloaded');
	}

	public function getPriority(): int {
		return 70;
	}

	public function canChangeStream(): bool {
		return true;
	}

	public function isDefaultEnabledStream(): bool {
		return true;
	}

	public function canChangeMail(): bool {
		return false;
	}

	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
