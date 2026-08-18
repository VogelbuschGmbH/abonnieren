<?php

declare(strict_types=1);

namespace OCA\Abonnieren\Listeners;

use OCA\Abonnieren\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** Loads the Files sidebar tab when the Files app initializes scripts. */
class LoadAdditionalScriptsListener implements IEventListener {
	public function __construct(
		private IAppManager $appManager,
	) {
	}

	public function handle(Event $event): void {
		// Compare by name so we never need to autoload OCA\Files\... from this file.
		if ($event::class !== 'OCA\\Files\\Event\\LoadAdditionalScriptsEvent') {
			return;
		}

		if (!$this->appManager->isEnabledForUser(Application::APP_ID)) {
			return;
		}

		// Receiving this event already proves that the Files app is active. Do not
		// depend on an internal Files class whose name can change between releases.
		Util::addInitScript(Application::APP_ID, 'files-init');
	}
}
