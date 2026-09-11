<?php

/**
 *
 * @copyright Copyright (c) 2025, RCDevs (info@rcdevs.com)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Controller;

// RCDevs App
use OCA\YumiSignNxtC\AppInfo\Application as RCDevsApp;
use OCA\YumiSignNxtC\Constant\CstApplication;

// Nextcloud Core
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\HintException;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;

class PageController extends Controller
{
	public function __construct(
		IRequest	$request,
		private		IInitialState	$initialState,
		private		RCDevsApp	$application,
		string		$appName,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 * @throws HintException
	 */
	public function index(): Response
	{
		$this->initialState->provideInitialState('debugVueJs', $this->application->getDebugVueJs());

		$response = new TemplateResponse(
			$this->application->getAppId(),
			CstApplication::INDEX,
			[
				CstApplication::APP					=> $this->application->getAppId(),
				CstApplication::ID_APP_CONTENT		=> '#app-content-vue',
				CstApplication::ID_APP_NAVIGATION	=> '#app-navigation-vue',
			]
		);
		return $response;
	}
}
