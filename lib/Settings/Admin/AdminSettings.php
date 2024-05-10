<?php

/**
 *
 * @copyright Copyright (c) 2024, RCDevs (info@rcdevs.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\YumiSignNxtC\Settings\Admin;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCA\YumiSignNxtC\Config;


class AdminSettings implements ISettings
{

    /** @var IConfig */
    // protected $config;

    /**
     * @param IConfig $config
     */
    public function __construct(private IConfig $config, private IInitialState $initialState)
    {
    }

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse
    {
        $initialSettings = [
            'installedVersion'  => $this->config->getAppValue('yumisign_nextcloud', 'installed_version'),
            'apiKey'            => $this->config->getAppValue('yumisign_nextcloud', 'api_key'),
            'workspaceId'       => $this->config->getAppValue('yumisign_nextcloud', 'workspace_id'),
            'workspaceName'     => $this->config->getAppValue('yumisign_nextcloud', 'workspace_name'),
            'defaultDomain'     => $this->config->getAppValue('yumisign_nextcloud', 'default_domain'),
            'userSettings'      => $this->config->getAppValue('yumisign_nextcloud', 'user_settings'),
            'useProxy'          => empty($this->config->getAppValue('yumisign_nextcloud', 'use_proxy')) ? '0' : $this->config->getAppValue('yumisign_nextcloud', 'use_proxy'),
            'proxyHost'         => $this->config->getAppValue('yumisign_nextcloud', 'proxy_host'),
            'proxyPort'         => $this->config->getAppValue('yumisign_nextcloud', 'proxy_port'),
            'proxyUsername'     => $this->config->getAppValue('yumisign_nextcloud', 'proxy_username'),
            'proxyPassword'     => $this->config->getAppValue('yumisign_nextcloud', 'proxy_password'),
            'signScope'         => $this->config->getAppValue('yumisign_nextcloud', 'sign_scope', 'Global'),
            'signedFile'        => $this->config->getAppValue('yumisign_nextcloud', 'signed_file', 'copy'),
            'syncTimeout'       => $this->config->getAppValue('yumisign_nextcloud', 'sync_timeout', 2),
            'asyncTimeout'      => $this->config->getAppValue('yumisign_nextcloud', 'async_timeout', 1),
            'cronInterval'      => $this->config->getAppValue('yumisign_nextcloud', 'cron_interval', 5),
            'enableDemoMode'    => $this->config->getAppValue('yumisign_nextcloud', 'enable_demo_mode'),
            'watermarkText'     => $this->config->getAppValue('yumisign_nextcloud', 'watermark_text', 'RCDEVS - SPECIMEN - YUMISIGN'),
            'description'       => $this->config->getAppValue('yumisign_nextcloud', 'description'),
        ];

        $this->initialState->provideInitialState('initialSettings', $initialSettings);

        Util::addScript(Config::APP_ID, Config::APP_ID . '-admin-settings');

        return new TemplateResponse(Config::APP_ID, 'settings/admin-settings', [], '');
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     */
    public function getSection()
    {
        return 'yumisign_nextcloud';
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority()
    {
        return 55;
    }
}
