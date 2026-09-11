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

namespace OCA\YumiSignNxtC\RCDevs\Controller;

// RCDevs Bundle
use OCA\YumiSignNxtC\RCDevs\Constant\CstConfig;
use OCA\YumiSignNxtC\RCDevs\Dto\CDEM;
use OCA\YumiSignNxtC\RCDevs\Service\ConfigurationService;

// Nextcloud Core
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class UiController extends Controller
{
	public function __construct(
		IRequest $request,
		private ConfigurationService $configurationService,
		string $AppName
	) {
		parent::__construct($AppName, $request);
	}

	public function getItemsPerPage(): JSONResponse
	{
		$cdem = new CDEM();
		$cdem->setOk(
			data: [
				CstConfig::ITEMSPERPAGE => $this->configurationService->getUiItemsPerPage(),
			],
		);

		return new JSONResponse($cdem->toArray());
	}
}
