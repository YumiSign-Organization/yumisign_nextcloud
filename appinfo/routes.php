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

$requirements = [
	'apiVersion' => 'v(1)',
];

return [
	'routes' => [
		/*
		OAUTH
		*/
		[
			'name'	=> 'oauth#connect',
			'url'	=> '/oauth/connect',
			'verb'	=> 'GET'
		],
		[
			'name'	=> 'oauth#callback',
			'url'	=> '/oauth/callback',
			'verb'	=> 'GET'
		],
		[
			'name'	=> 'oauth#checkAccessToken',
			'url'	=> '/oauth/token/check',
			'verb'	=> 'GET',
		],
		[
			'name'	=> 'oauth#deleteAccessToken',
			'url'	=> '/oauth/token/delete',
			'verb'	=> 'GET',
		],
		[
			'name'	=> 'oauth#refreshToken',
			'url'	=> '/oauth/token/refresh',
			'verb'	=> 'GET'
		],
		[
			'name'	=> 'page#index',
			'url'	=> '/',
			'verb'	=> 'GET',
		],

		/*
		TRANSACTIONS
		*/
		// Transactions according to status
		[
			'name'	=> 'transactions#getTransactionsCompleted',
			'url'	=> '/transactions/completed',
			'verb'	=> 'GET',
		],
		[
			'name'	=> 'transactions#getTransactionsDeclined',
			'url'	=> '/transactions/declined',
			'verb'	=> 'GET',
		],
		[
			'name'	=> 'transactions#getTransactionsExpired',
			'url'	=> '/transactions/expired',
			'verb'	=> 'GET',
		],
		[
			'name'	=> 'transactions#getTransactionsFailed',
			'url'	=> '/transactions/failed',
			'verb'	=> 'GET',
		],
		[
			'name'	=> 'transactions#getTransactionsPending',
			'url'	=> '/transactions/pending',
			'verb'	=> 'GET',
		],
		// Operations on existing transactions
		[
			'name'	=> 'transactions#cancelTransaction',
			'url'	=> '/transaction/cancel',
			'verb'	=> 'PUT',
		],
		[
			'name'	=> 'transactions#deleteTransaction',
			'url'	=> '/transaction/deletion',
			'verb'	=> 'PUT',
		],

		/*
		SIGNATURES
		*/
		[
			'name'	=> 'sign#webhook',
			'url'	=> '/webhook',
			'verb'	=> 'POST'
		],
		[
			'name'	=> 'sign#signLocalAsyncSubmit',
			'url'	=> '/sign/mobile/async/external/submit',
			'verb'	=> 'GET',
		],

		/*
		UI
		*/
		[
			'name'	=> 'ui#getItemsPerPage',
			'url'	=> '/ui/items/page',
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
		SETTINGS - ADMIN
		*/
		[
			'name'			=> 'AdminSettings#checkCronStatus',
			'url'			=> '/api/{apiVersion}/settings/check/cron',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'AdminSettings#checkEnabledSign',
			'url'			=> '/api/{apiVersion}/settings/check/app',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'AdminSettings#checkSignTypes',
			'url'			=> '/api/{apiVersion}/settings/check/types',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'AdminSettings#checkServerUrl',
			'url'			=> '/api/{apiVersion}/settings/check',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'AdminSettings#checkWorkspaceId',
			'url'			=> '/api/{apiVersion}/settings/check/workspace/id',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'AdminSettings#checkWorkspaceName',
			'url'			=> '/api/{apiVersion}/settings/check/workspace/name',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'AdminSettings#resetJob',
			'url'			=> '/api/{apiVersion}/settings/job/reset',
			'verb'			=> 'GET',
			'requirements'	=> $requirements,
		],
		[
			'name'			=> 'AdminSettings#saveSettings',
			'url'			=> '/api/{apiVersion}/settings/save',
			'verb'			=> 'POST',
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
