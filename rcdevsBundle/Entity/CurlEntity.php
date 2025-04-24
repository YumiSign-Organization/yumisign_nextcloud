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

namespace OCA\RCDevs\Entity;

use CurlHandle;
use JsonSerializable;

class CurlEntity implements JsonSerializable
{
	private $body;
	private $code;
	private $header_size;
	private $header;
	private $response;

	public function __construct(CurlHandle $curlHandle = null)
	{
		if (!is_null($curlHandle)) {
			$this->response		= curl_exec($curlHandle);
			$this->header_size	= curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);

			$this->body			= substr($this->response, $this->header_size);
			$this->code			= curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
			$this->header		= substr($this->response, 0, $this->header_size);
		}
	}

	public function getBody()
	{
		return $this->body;
	}

	public function getCode()
	{
		return $this->code;
	}

	public function getHeaderSize()
	{
		return $this->header_size;
	}

	public function getHeader()
	{
		return $this->header;
	}

	public function getResponse()
	{
		return $this->response;
	}

	public function setBody(string $value)
	{
		$this->body = $value;
	}

	public function jsonSerialize(): mixed
	{
		return [
			'body'			=> $this->body,
			'code'			=> $this->code,
			'header_size'	=> $this->header_size,
			'header'		=> $this->header,
			'response'		=> $this->response,
		];
	}
}
