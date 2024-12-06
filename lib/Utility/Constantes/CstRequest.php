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

namespace OCA\YumiSignNxtC\Utility\Constantes;

use OCA\RCDevs\Utility\Constantes\CstRequest as RCDevsCstRequest;

class CstRequest extends RCDevsCstRequest
{
	public const ACCESS_TOKEN				= 'access_token';
	public const CLIENT_ID					= 'client_id';
	public const ENVELOPEID					= 'envelopeId';
	public const OPENDESIGNER				= 'OpenDesigner';
	public const REFRESH_TOKEN				= 'refresh_token';
	public const SESSION					= 'session';
	public const STATE						= 'state';
	public const STEPS						= 'steps';
	public const TOKENREGISTERED			= 'tokenRegistered';
	public const WORKFLOW_CALLBACK_SECRET	= 'WorkflowNotificationCallbackUrlSecretPreference';
	public const WORKFLOWID					= 'workflowId';
	public const WORKSPACEID				= 'workspaceId';
    public const CLIENT_SECRET				= 'client_secret';
    public const CODE						= 'code';
    public const GRANT_TYPE					= 'grant_type';
    public const REDIRECT_URI				= 'redirect_uri';
}
