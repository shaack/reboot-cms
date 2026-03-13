<?php

namespace Shaack\Tests;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function run(string $testDir): int
    {
        echo "Running tests...\n\n";
        foreach (glob($testDir . "/*Test.php") as $file) {
            require_once $file;
            $className = "Shaack\\Tests\\" . basename($file, ".php");
            $testInstance = new $className();
            $methods = get_class_methods($testInstance);
            foreach ($methods as $method) {
                if (str_starts_with($method, "test")) {
                    $label = $className . "::" . $method;
                    try {
                        if (method_exists($testInstance, "setUp")) {
                            $testInstance->setUp();
                        }
                        $testInstance->$method();
                        if (method_exists($testInstance, "tearDown")) {
                            $testInstance->tearDown();
                        }
                        $this->passed++;
                        echo "  PASS  $label\n";
                    } catch (\Throwable $e) {
                        if (method_exists($testInstance, "tearDown")) {
                            try { $testInstance->tearDown(); } catch (\Throwable $ignored) {}
                        }
                        $this->failed++;
                        $this->failures[] = ["label" => $label, "error" => $e];
                        echo "  FAIL  $label\n";
                        echo "        " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        echo "\n" . ($this->passed + $this->failed) . " tests, "
            . $this->passed . " passed, " . $this->failed . " failed.\n";
        return $this->failed > 0 ? 1 : 0;
    }
}

class Assert
{
    public static function equals($expected, $actual, string $message = ""): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
            throw new \RuntimeException($msg);
        }
    }

    public static function true($value, string $message = ""): void
    {
        if ($value !== true) {
            throw new \RuntimeException($message ?: "Expected true, got " . var_export($value, true));
        }
    }

    public static function false($value, string $message = ""): void
    {
        if ($value !== false) {
            throw new \RuntimeException($message ?: "Expected false, got " . var_export($value, true));
        }
    }

    public static function null($value, string $message = ""): void
    {
        if ($value !== null) {
            throw new \RuntimeException($message ?: "Expected null, got " . var_export($value, true));
        }
    }

    public static function notNull($value, string $message = ""): void
    {
        if ($value === null) {
            throw new \RuntimeException($message ?: "Expected non-null value");
        }
    }

    public static function contains(string $needle, string $haystack, string $message = ""): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException($message ?: "Expected string to contain '$needle'");
        }
    }

    public static function count(int $expected, array $array, string $message = ""): void
    {
        if (count($array) !== $expected) {
            throw new \RuntimeException($message ?: "Expected count $expected, got " . count($array));
        }
    }

    public static function throws(string $exceptionClass, callable $fn, string $message = ""): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $exceptionClass) {
                return;
            }
            throw new \RuntimeException($message ?: "Expected $exceptionClass, got " . get_class($e));
        }
        throw new \RuntimeException($message ?: "Expected $exceptionClass, but no exception was thrown");
    }
}
