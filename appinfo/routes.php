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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 */

$requirements = [
	'apiVersion' => 'v(1)',
];

return [
	'routes' => [
		[
			'name'	=> 'Page#index',
			'url'	=> '/',
			'verb'	=> 'GET',
		],
		[
			'name'	=> 'Sign#webhook',
			'url'	=> '/webhook',
			'verb'	=> 'POST'
		],
		[
			'name'	=> 'Sign#signLocalAsyncSubmit',
			'url'	=> '/sign/mobile/async/external/submit',
			'verb'	=> 'GET',
		],

	],
	'ocs' => [
		/*
		USERS
		*/
		[
			'name'			=> 'User#getCurrentUserId',
			'url'			=> '/api/{apiVersion}/user/id',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'User#getCurrentUserEmail',
			'url'			=> '/api/{apiVersion}/user/email',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'User#getLocalUsers',
			'url'			=> '/api/{apiVersion}/users/all',
			'verb'			=> 'POST',
			'requirements'	=> $requirements,
		],

		/*
		TRANSACTIONS
		*/
		// Transactions according to status		
		[
			'name'			=> 'Transactions#getTransactionsCompleted',
			'url'			=> '/api/{apiVersion}/transactions/completed',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Transactions#getTransactionsDeclined',
			'url'			=> '/api/{apiVersion}/transactions/declined',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Transactions#getTransactionsExpired',
			'url'			=> '/api/{apiVersion}/transactions/expired',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Transactions#getTransactionsFailed',
			'url'			=> '/api/{apiVersion}/transactions/failed',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Transactions#getTransactionsPending',
			'url'			=> '/api/{apiVersion}/transactions/pending',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		// Operations on existing transactions
		[
			'name'			=> 'Transactions#cancelTransaction',
			'url'			=> '/api/{apiVersion}/transaction/cancel',
			'verb'			=> 'PUT',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Transactions#deleteTransaction',
			'url'			=> '/api/{apiVersion}/transaction/deletion',
			'verb'			=> 'PUT',
			'requirements'	=> $requirements,
		],

		/*
		UI
		*/
		[
			'name'			=> 'Ui#getItemsPerPage',
			'url'			=> '/api/{apiVersion}/ui/items/page',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],

		/*
		SETTINGS
		*/
		[
			'name'			=> 'Settings#checkCronStatus',
			'url'			=> '/api/{apiVersion}/settings/check/cron',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Settings#checkEnabledSign',
			'url'			=> '/api/{apiVersion}/settings/check/app',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Settings#checkSignTypes',
			'url'			=> '/api/{apiVersion}/settings/check/types',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Settings#checkServerUrl',
			'url'			=> '/api/{apiVersion}/settings/check',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Settings#checkWorkspaceId',
			'url'			=> '/api/{apiVersion}/settings/check/workspace/id',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Settings#checkWorkspaceName',
			'url'			=> '/api/{apiVersion}/settings/check/workspace/name',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Settings#resetJob',
			'url'			=> '/api/{apiVersion}/settings/job/reset',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Settings#saveSettings',
			'url'			=> '/api/{apiVersion}/settings/save',
			'verb'			=> 'POST',
			'requirements'	=> $requirements,
		],

		/*
		SETTINGS - PERSONAL
		*/
		[
			'name'			=> 'PersonalSettings#accessTokenRefreshToken',
			'url'			=> '/api/{apiVersion}/settings/personal/token',
			'verb'			=> 'POST',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'PersonalSettings#checkAccessToken',
			'url'			=> '/api/{apiVersion}/settings/personal/token/check',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],

		/*
		SIGN
		*/
		[
			'name'			=> 'Sign#signLocalAsyncAdvanced',
			'url'			=> '/api/{apiVersion}/sign/local/async/advanced',
			'verb'			=> 'POST',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Sign#signLocalAsyncQualified',
			'url'			=> '/api/{apiVersion}/sign/local/async/qualified',
			'verb'			=> 'POST',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'Sign#signLocalAsyncStandard',
			'url'			=> '/api/{apiVersion}/sign/local/async/standard',
			'verb'			=> 'POST',
			'requirements'	=> $requirements,
		],
	],
];
