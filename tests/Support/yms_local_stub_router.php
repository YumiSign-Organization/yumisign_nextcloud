<?php

declare(strict_types=1);

$responsesFile = getenv('YMS_STUB_RESPONSES_FILE');
$routes = [];

if (is_string($responsesFile) && $responsesFile !== '' && is_file($responsesFile)) {
	$decoded = json_decode((string) file_get_contents($responsesFile), true);
	if (is_array($decoded)) {
		$routes = $decoded;
	}
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = (string) parse_url($uri, PHP_URL_PATH);

$routeKeyWithQuery = sprintf('%s %s', $method, $uri);
$routeKeyPathOnly = sprintf('%s %s', $method, $path);
$route = $routes[$routeKeyWithQuery] ?? $routes[$routeKeyPathOnly] ?? null;

if (!is_array($route)) {
	foreach ($routes as $key => $candidate) {
		if (!is_string($key) || !is_array($candidate)) {
			continue;
		}

		if (!str_starts_with($key, $method . ' ')) {
			continue;
		}

		$pattern = trim(substr($key, strlen($method) + 1));
		if ($pattern === '' || $pattern[0] !== '~') {
			continue;
		}

		if (@preg_match($pattern, $path) === 1) {
			$route = $candidate;
			break;
		}
	}
}

if (!is_array($route)) {
	http_response_code(404);
	header('Content-Type: application/json');
	echo json_encode([
		'code' => 404,
		'error' => 'route_not_found',
		'method' => $method,
		'path' => $path,
	]);
	return;
}

$status = (int) ($route['status'] ?? 200);
http_response_code($status);

$headers = $route['headers'] ?? [];
if (is_array($headers)) {
	foreach ($headers as $name => $value) {
		header(sprintf('%s: %s', $name, $value));
	}
}

$body = $route['body'] ?? null;
if (is_array($body) || is_object($body)) {
	if (!isset($headers['Content-Type'])) {
		header('Content-Type: application/json');
	}
	echo json_encode($body);
	return;
}

echo (string) $body;

