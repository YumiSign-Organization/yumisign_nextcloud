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

namespace OCA\YumiSignNxtC\Service;

enum Cst: string
{
	case CODE							= 'code';
	case DATA							= 'data';
	case ERROR							= 'error';
	case YMS_ALREADY_CANCELLED			= 'already_cancelled';
	case YMS_APPLICANT					= 'applicant';
	case YMS_APPLICANT_ID				= 'applicant_id';
	case YMS_APPLICANTID				= 'applicantId';
	case YMS_APPLICANTS					= 'applicants';
	case YMS_ARCHIVED					= 'archived';
	case YMS_DELETED					= 'deleted';
	case YMS_ENVELOPEID					= 'envelopeId';
	case YMS_EXCEPTION					= 'Exception';
	case YMS_GLOBAL_STATUS				= 'global_status';
	case YMS_ID							= 'id';
	case YMS_IDENTIFIER					= 'identifier';
	case YMS_ITEMS						= 'items';
	case YMS_LIST_ID					= 'listId';
	case YMS_MESSAGE					= 'message';
	case YMS_NAME						= 'name';
	case YMS_OWNER						= 'owner';
	case YMS_PENDINGACTIONS				= 'pendingActions';
	case YMS_RECIPIENT					= 'recipient';
	case YMS_RECIPIENTEMAIL				= 'recipientEmail';
	case YMS_RECIPIENTS					= 'recipients';
	case YMS_RESPONSE					= 'response';
	case YMS_RESULT						= 'result';
	case YMS_SIGNED						= 'signed';
	case YMS_SIMPLE						= 'simple';
	case YMS_STATUS						= 'status';
	case YMS_SUCCESS					= 'success';
	case YMS_URL_ARCHIVE				= '/storage/archive/';
	case YMS_VALUE						= 'value';
	case YMS_WF_SECRET					= 'WorkflowNotificationCallbackUrlSecretPreference';
}
// Errors codes from YuniSign server
enum Error: string
{
	case YMS_ERR_ENVELOPE_NOT_FOUND		= 'ENVELOPE_NOT_FOUND';
}

// Status
enum Status: string
{
	case APPROVED			= 'approved';
	case CANCELED			= 'canceled';
	case DECLINED			= 'declined';
	case EXPIRED			= 'expired';
	case NOT_APPLICABLE		= 'not applicable';
	case NOT_STARTED		= 'not_started';
	case NOT_FOUND			= 'not found';
	case SIGNED				= 'signed';
	case STARTED			= 'started';
	case TO_BE_ARCHIVED		= 'to_be_archived';
}

enum Yumisign: string
{
	case IDENTIFIER			= 'identifier';
	case RESULT				= 'result';
	case RESPONSE			= 'response';
	case ERROR				= 'error';
	case CODE				= 'code';
	case MESSAGE			= 'message';
	case STATUS				= 'status';
	case STEPS				= 'steps';
	case ACTIONS			= 'actions';
	case RECIPIENTEMAIL		= 'recipientEmail';
	case STATUSCODE			= 'statusCode';
}

enum Entity: string{
	case APPLICANT_ID		= 'applicant_id';
	case CHANGE_STATUS		= 'change_status';
	case ENVELOPE_ID		= 'envelope_id';
	case EXPIRY_DATE		= 'expiry_date';
	case GLOBAL_STATUS		= 'global_status';
	case RECIPIENT			= 'recipient';
	case STATUS				= 'status';
	case WORKFLOW_ID		= 'workflow_id';
}
class Constante
{
	static function get(Cst $cst): string
	{
		return $cst->value;
	}

	static function error(Error $error): string
	{
		return $error->value;
	}

	static function status(Status $status): string
	{
		return $status->value;
	}

	static function entity(Entity $entity): string
	{
		return $entity->value;
	}

	static function yumisign(Yumisign $yumisign): string
	{
		return $yumisign->value;
	}
}
