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

declare(strict_types=1);


$requirements = [
	'apiVersion' => 'v(1)',
];

return [
	'routes' => [
		[
			'name' => 'Page#index',
			'url' => '/',
			'verb' => 'GET',
		],
		[
			'name' => 'sign#webhook',
			'url' => '/webhook',
			'verb' => 'POST'
		],
		[
			'name' => 'Sign#asyncExternalMobileSignSubmit',
			'url' => '/sign/mobile/async/external/submit',
			'verb' => 'GET',
		],

	],
	'ocs' => [
		/*
		USERS
		*/
		[
			'name' => 'User#getCurrentUserId',
			'url' => '/api/{apiVersion}/user/id',
			'verb' => 'GET',
			'requirements' => $requirements,
		],
		[
			'name' => 'User#getCurrentUserEmail',
			'url' => '/api/{apiVersion}/user/email',
			'verb' => 'GET',
			'requirements' => $requirements,
		],
		[
			'name' => 'User#getLocalUsers',
			'url' => '/api/{apiVersion}/users/all',
			'verb' => 'POST',
			'requirements' => $requirements,
		],

		/*
		REQUESTS
		*/
		[
			'name' => 'Requests#getPendingRequests',
			'url' => '/api/{apiVersion}/requests/pending',
			'verb' => 'GET',
			'requirements' => $requirements,
		],
		[
			'name' => 'Requests#getIssuesRequests',
			'url' => '/api/{apiVersion}/requests/issues',
			'verb' => 'GET',
			'requirements' => $requirements,
		],

		/*
		UI
		*/
		[
			'name' => 'UI#getItemsPerPage',
			'url' => '/api/{apiVersion}/ui/items/page',
			'verb' => 'GET',
			'requirements' => $requirements,
		],

		/*
		SETTINGS
		*/
		[
			'name' => 'Settings#checkSettings',
			'url' => '/api/{apiVersion}/settings/check',
			'verb' => 'GET',
			'requirements' => $requirements,
		],
		[
			'name' => 'Settings#checkServerUrl',
			'url' => '/api/{apiVersion}/settings/check/server',
			'verb' => 'POST',
			'requirements' => $requirements,
		],
		[
			'name' => 'Settings#checkWorkspace',
			'url' => '/api/{apiVersion}/settings/check/workspace',
			'verb' => 'POST',
			'requirements' => $requirements,
		],
		[
			'name' => 'Settings#checkCronStatus',
			'url' => '/api/{apiVersion}/settings/check/cron',
			'verb' => 'GET',
			'requirements' => $requirements,
		],
		[
			'name' => 'Settings#resetJob',
			'url' => '/api/{apiVersion}/settings/job/reset',
			'verb' => 'GET',
			'requirements' => $requirements,
		],
		[
			'name' => 'Settings#saveSettings',
			'url' => '/api/{apiVersion}/settings/save',
			'verb' => 'POST',
			'requirements' => $requirements,
		],

		/*
		SIGN
		*/
		[
			'name' => 'Sign#mobileSign',
			'url' => '/api/{apiVersion}/sign/mobile',
			'verb' => 'POST',
			'requirements' => $requirements,
		],
		[
			'name' => 'Sign#asyncLocalMobileSign',
			'url' => '/api/{apiVersion}/sign/mobile/async/local',
			'verb' => 'POST',
			'requirements' => $requirements,
		],
		[
			'name' => 'Sign#asyncExternalMobileSign',
			'url' => '/api/{apiVersion}/sign/mobile/async/external',
			'verb' => 'POST',
			'requirements' => $requirements,
		],
		[
			'name' => 'Sign#cancelSignRequest',
			'url' => '/api/{apiVersion}/sign/cancel',
			'verb' => 'PUT',
			'requirements' => $requirements,
		],
		[
			'name' => 'Sign#forceDeletion',
			'url' => '/api/{apiVersion}/sign/deletion',
			'verb' => 'PUT',
			'requirements' => $requirements,
		],

	],
];
