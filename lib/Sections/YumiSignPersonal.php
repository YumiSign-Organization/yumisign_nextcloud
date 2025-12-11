<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Sections;

use OCA\YumiSignNxtC\Service\ConfigurationService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class YumiSignPersonal implements IIconSection
{
	private	ConfigurationService	$configurationService;

	public function __construct(
		private		IL10N			$l10nYmsPersonalSection,
		private		IConfig			$config,
		private		IURLGenerator	$url,
	) {
		$this->configurationService = new ConfigurationService($config);
	}

	public function getIcon(): string
	{
		return $this->url->imagePath($this->configurationService->getAppId(), 'app-dark.svg');
	}

	public function getID(): string
	{
		// return 'yumisign';
		return $this->configurationService->getAppId();
	}

	public function getName(): string
	{
		return $this->l10nYmsPersonalSection->t($this->configurationService->getApplicationName());
	}

	public function getPriority(): int
	{
		return 50;
	}
}
