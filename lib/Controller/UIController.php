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

namespace OCA\YumiSignNxtC\Controller;

use OCA\YumiSignNxtC\Service\Constante;
use OCA\YumiSignNxtC\Service\Cst;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;

use OCA\YumiSignNxtC\Service\SignService;

class UIController extends Controller
{


	public function __construct(
		$AppName,
		IRequest $request,
		private IConfig $config,
		private SignService $signService
	) {
		parent::__construct($AppName, $request);
	}

	public function getItemsPerPage()
	{
		return new JSONResponse(['itemsPerPage' => 10]);
	}
}
