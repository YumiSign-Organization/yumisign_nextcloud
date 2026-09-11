<?php

declare(strict_types=1);

namespace OCA\YumiSignNxtC\Tests\Support;

trait CoverageAssertions
{
	/**
	 * Assert a PHP file contains at least one declared named function/method.
	 *
	 * @param string $filePath
	 * @return void
	 */
	protected function assertFileFunctionInventory(string $filePath): void
	{
		self::assertTrue(is_file($filePath), sprintf('File not found: %s', $filePath));

		$source = (string) file_get_contents($filePath);
		$tokens = token_get_all($source);
		$functions = [];

		$count = count($tokens);
		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			if (!is_array($token) || $token[0] !== T_FUNCTION) {
				continue;
			}

			for ($j = $i + 1; $j < $count; $j++) {
				$next = $tokens[$j];
				if (is_array($next) && $next[0] === T_WHITESPACE) {
					continue;
				}
				if (is_array($next) && $next[0] === T_STRING) {
					$functions[] = $next[1];
				}
				break;
			}
		}

		if ($functions === []) {
			$isExceptionSubclass = (preg_match('/class\s+\w+\s+extends\s+[\\\\\w]*Exception\b/', $source) === 1);
			if (!$isExceptionSubclass && preg_match('/namespace\s+([^;]+);/', $source, $namespace)
				&& preg_match('/class\s+(\w+)\s+extends\s+/', $source, $class)) {
				$reflection = new \ReflectionClass(trim($namespace[1]) . '\\' . $class[1]);
				self::assertNotEmpty($reflection->getMethods(), 'An adapter must inherit its implementation');
				return;
			}
			self::assertTrue($isExceptionSubclass, sprintf('No function found in file: %s', $filePath));
			return;
		}
		foreach ($functions as $function) {
			self::assertNotSame('', trim((string) $function), sprintf('Empty function name in file: %s', $filePath));
		}
	}
}
