#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use CloudBridge\CloudBridgeClient;

$accessKey = getenv('CLOUDBRIDGE_ACCESS_KEY') ?: 'NUGI-AK-ACCESS';
$secretKey = getenv('CLOUDBRIDGE_SECRET_KEY') ?: 'SECRET';

$client = new CloudBridgeClient($accessKey, $secretKey);

if ($argc < 3) {
	fwrite(STDERR, "Usage: php examples/upload.php <folder> <file1> [file2 ...]\n");
	exit(1);
}

$folder = $argv[1];
$files = array_slice($argv, 2);

try {
	if (count($files) === 1) {
		$result = $client->uploadFile($files[0], $folder);
	} else {
		$result = $client->uploadFiles($files, $folder);
	}
	print_r($result);
} catch (\CloudBridge\Exceptions\InvalidCredentialsException $e) {
	fwrite(STDERR, "Invalid credentials: " . $e->getMessage() . "\n");
	exit(2);
} catch (\Throwable $e) {
	fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
	exit(3);
}
