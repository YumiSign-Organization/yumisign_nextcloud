<?php

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\YumiSignNxtC\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
	'routes' => [
		['name' => 'sign#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'sign#mobileSign', 'url' => '/mobile_sign', 'verb' => 'POST'],
		['name' => 'sign#asyncLocalMobileSign', 'url' => '/async_local_mobile_sign', 'verb' => 'POST'],

		['name' => 'sign#asyncExternalMobileSign', 'url' => '/async_external_mobile_sign', 'verb' => 'POST'],
		['name' => 'sign#asyncExternalMobileSignSubmit', 'url' => '/async_external_mobile_sign_submit', 'verb' => 'GET'],
		['name' => 'sign#cancelSignRequest', 'url' => '/cancel_sign_request', 'verb' => 'PUT'],
		['name' => 'sign#forceDeletion', 'url' => '/force_deletion_request', 'verb' => 'PUT'],

		['name' => 'sign#webhook', 'url' => '/webhook', 'verb' => 'POST'],

		['name' => 'sign#getLocalUsers', 'url' => '/get_local_users', 'verb' => 'GET'],

		['name' => 'settings#saveSettings', 'url' => '/settings', 'verb' => 'POST'],
		['name' => 'settings#checkCronStatus',		'url' => '/check_cron_status',		'verb' => 'POST'],
		['name' => 'settings#checkServerUrl',		'url' => '/check_server_url',		'verb' => 'POST'],
		['name' => 'settings#checkWorkspace',		'url' => '/check_wspname_id',		'verb' => 'POST'],
		['name' => 'settings#checkSettings',		'url' => '/check_settings',			'verb' => 'GET'],
		['name' => 'settings#resetJob',				'url' => '/reset_job',				'verb' => 'POST'],

		['name' => 'requests#getPendingRequests', 'url' => '/pending_requests', 'verb' => 'GET'],
		['name' => 'requests#getIssuesRequests', 'url' => '/issues_requests', 'verb' => 'GET'],
	],
];
