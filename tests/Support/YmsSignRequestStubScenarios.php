<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Support;

use OCA\YumiSignNxtC\Constant\CstRequest;

class YmsSignRequestStubScenarios
{
	public static function selfStandardSuccess(
		int $workspaceId,
		int $workflowId,
		string $envelopeId,
		string $recipientEmail,
	): array {
		$now = time();

		return [
			'GET ~^/api/v1/workspaces/\d+$~' => [
				'status' => 200,
				'body' => ['id' => $workspaceId, 'name' => 'NXC-STUB'],
			],
			'POST ~^/api/v1/workspaces/\d+/workflows$~' => [
				'status' => 200,
				'body' => [
					'id' => $workflowId,
					'envelopeId' => $envelopeId,
					'documents' => [['id' => 'doc-1']],
				],
			],
			'POST ~^/api/v1/workspaces/\d+/workflows/\d+/preferences$~' => [
				'status' => 200,
				'body' => [[
					'name' => CstRequest::WORKFLOW_CALLBACK_SECRET,
					'value' => 'stub-secret',
				]],
			],
			'PUT ~^/api/v1/workspaces/\d+/workflows/\d+/roles$~' => [
				'status' => 200,
				'body' => [[
					'id' => 53071,
					'name' => 'Self Recipient',
					'email' => $recipientEmail,
					'roles' => [['id' => 53501, 'type' => 'sign', 'color' => '#98b7e4']],
				]],
			],
			'GET ~^/api/v1/workspaces/\d+/workflows/\d+/debrief$~' => [
				'status' => 200,
				'body' => [
					'createDate' => $now,
					'expiryDate' => $now + 86400,
					'status' => 'pending',
					'recipients' => [['email' => $recipientEmail]],
				],
			],
			'POST ~^/api/v1/workspaces/\d+/workflows/\d+/steps$~' => [
				'status' => 200,
				'body' => [[
					'order' => 1,
					'type' => 'sign',
					'status' => 'not_started',
				]],
			],
			'GET ~^/api/v1/workspaces/\d+/workflows/\d+/session$~' => [
				'status' => 200,
				'body' => [
					'session' => 'sess-sign-request',
					'designerUrl' => 'https://stub/designer',
				],
			],
		];
	}
}

