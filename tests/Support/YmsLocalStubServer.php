<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Support;

class YmsLocalStubServer
{
	/** @var resource|false|null */
	private $process = null;

	/** @var resource[] */
	private array $pipes = [];

	private int $port = 0;

	private string $responsesFile = '';

	public static function start(array $routes): self
	{
		$server = new self();
		$server->boot($routes);
		return $server;
	}

	public function baseUrl(): string
	{
		return sprintf('http://127.0.0.1:%d', $this->port);
	}

	public function stop(): void
	{
		if (is_resource($this->process)) {
			@proc_terminate($this->process);
		}

		foreach ($this->pipes as $pipe) {
			if (is_resource($pipe)) {
				@fclose($pipe);
			}
		}
		$this->pipes = [];

		if (is_resource($this->process)) {
			@proc_close($this->process);
		}
		$this->process = null;

		if ($this->responsesFile !== '' && is_file($this->responsesFile)) {
			@unlink($this->responsesFile);
		}
		$this->responsesFile = '';
	}

	public function __destruct()
	{
		$this->stop();
	}

	private function boot(array $routes): void
	{
		$this->port = $this->findFreePort();
		$this->responsesFile = (string) tempnam(sys_get_temp_dir(), 'yms_local_stub_routes_');
		file_put_contents($this->responsesFile, json_encode($routes));

		$routerPath = __DIR__ . '/yms_local_stub_router.php';
		$command = sprintf(
			'YMS_STUB_RESPONSES_FILE=%s php -S 127.0.0.1:%d %s',
			escapeshellarg($this->responsesFile),
			$this->port,
			escapeshellarg($routerPath),
		);

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));
		if (!is_resource($process)) {
			throw new \RuntimeException('Unable to start local YMS stub server process');
		}

		$this->process = $process;
		$this->pipes = $pipes;
		$this->waitUntilServerReady();
	}

	private function findFreePort(): int
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
		if ($socket === false) {
			throw new \RuntimeException(sprintf('Unable to find free port: %s (%d)', $errstr, $errno));
		}

		$name = stream_socket_get_name($socket, false);
		fclose($socket);

		if (!is_string($name) || !str_contains($name, ':')) {
			throw new \RuntimeException('Unable to parse free port');
		}

		return (int) substr($name, strrpos($name, ':') + 1);
	}

	private function waitUntilServerReady(): void
	{
		$deadline = microtime(true) + 5.0;
		while (microtime(true) < $deadline) {
			$conn = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
			if (is_resource($conn)) {
				fclose($conn);
				return;
			}
			usleep(50000);
		}

		throw new \RuntimeException(sprintf('Local YMS stub server did not start on port %d', $this->port));
	}
}

