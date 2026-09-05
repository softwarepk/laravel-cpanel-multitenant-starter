<?php

$environmentPath = dirname(__DIR__).'/.env';
$createdEnvironment = false;

if (! is_file($environmentPath)) {
    $handle = @fopen($environmentPath, 'x');

    if ($handle === false) {
        if (! is_file($environmentPath)) {
            throw new RuntimeException('Unable to create a temporary .env file for the test suite.');
        }
    } else {
        fclose($handle);
        $createdEnvironment = true;
    }
}

if ($createdEnvironment) {
    register_shutdown_function(static function () use ($environmentPath): void {
        if (is_file($environmentPath)) {
            @unlink($environmentPath);
        }
    });
}

require dirname(__DIR__).'/vendor/autoload.php';
