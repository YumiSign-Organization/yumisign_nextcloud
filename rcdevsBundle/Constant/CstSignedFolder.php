<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\RCDevs\Constant;

class CstSignedFolder
{
	public const APPLICANT = 'applicant';
	public const RECIPIENT = 'recipient';
	public const KEYS = [
		self::APPLICANT => 'user-signed-folder-applicant',
		self::RECIPIENT => 'user-signed-folder-recipient',
	];
	public const COLLISION_SUFFIX = '_%d';
	public const FIRST_SUFFIX = 2;
	public const REFRESH_TOKEN = 'ui_files_refresh_token';
	public const OUTBOX = 'signed-delivery';
}
