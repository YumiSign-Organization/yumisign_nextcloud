<?php
/**
 *
 * @copyright Copyright (c) 2021, RCDevs (info@rcdevs.com)
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

namespace OCA\YumiSignNxtC\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {

    /** @var IConfig */
    protected $config;

    /**
     * @param IConfig $config
     */
    public function __construct(IConfig $config) {
        $this->config = $config;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        return new TemplateResponse('yumisign_nextcloud', 'settings/admin', [
            'server_urls' => $this->config->getAppValue('yumisign_nextcloud', 'server_urls', '[]'),
            'api_key' => $this->config->getAppValue('yumisign_nextcloud', 'api_key', 'Write here the API key from YumiSign'),
            'default_domain' => $this->config->getAppValue('yumisign_nextcloud', 'default_domain'),
            'user_settings' => $this->config->getAppValue('yumisign_nextcloud', 'user_settings'),
            'use_proxy' => $this->config->getAppValue('yumisign_nextcloud', 'use_proxy'),
            'proxy_host' => $this->config->getAppValue('yumisign_nextcloud', 'proxy_host'),
            'proxy_port' => $this->config->getAppValue('yumisign_nextcloud', 'proxy_port'),
            'proxy_username' => $this->config->getAppValue('yumisign_nextcloud', 'proxy_username'),
            'proxy_password' => $this->config->getAppValue('yumisign_nextcloud', 'proxy_password'),
            'sign_scope' => $this->config->getAppValue('yumisign_nextcloud', 'sign_scope', 'Global'),
            'signed_file' => $this->config->getAppValue('yumisign_nextcloud', 'signed_file', 'copy'),
            'sync_timeout' => $this->config->getAppValue('yumisign_nextcloud', 'sync_timeout', 2),
            'async_timeout' => $this->config->getAppValue('yumisign_nextcloud', 'async_timeout', 1),
            'cron_interval' => $this->config->getAppValue('yumisign_nextcloud', 'cron_interval', 5),
            'enable_demo_mode' => $this->config->getAppValue('yumisign_nextcloud', 'enable_demo_mode'),
            'watermark_text' => $this->config->getAppValue('yumisign_nextcloud', 'watermark_text', 'RCDEVS - SPECIMEN - YUMISIGN'),
        ], 'blank');
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     */
    public function getSection() {
        return 'yumisign_nextcloud';
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority() {
        return 55;
    }
}
