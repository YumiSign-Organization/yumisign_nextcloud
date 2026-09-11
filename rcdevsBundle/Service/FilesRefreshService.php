<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\RCDevs\Service;

use OCA\YumiSignNxtC\RCDevs\Constant\CstSignedFolder;
use OCP\Config\IUserConfig;

class FilesRefreshService
{
	public function __construct(private ConfigurationService $configuration, private IUserConfig $preferences) {
	}

	public function signal(string $uid): void
	{
		$this->preferences->setValueString($uid, $this->configuration->getAppId(), CstSignedFolder::REFRESH_TOKEN, bin2hex(random_bytes(16)));
	}

	public function token(string $uid): string
	{
		return $this->preferences->getValueString($uid, $this->configuration->getAppId(), CstSignedFolder::REFRESH_TOKEN);
	}
}
