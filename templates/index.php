<?php

use OCA\YumiSignNxtC\AppInfo\Application;
use OCP\Util;

$appId = Application::APP_ID();

Util::addScript($appId, $appId . '-main');
