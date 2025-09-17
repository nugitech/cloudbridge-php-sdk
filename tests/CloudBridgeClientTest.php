<?php
declare(strict_types=1);

namespace CloudBridge\Tests;

use CloudBridge\CloudBridgeClient;
use CloudBridge\Exceptions\InvalidCredentialsException;
use PHPUnit\Framework\TestCase;

final class CloudBridgeClientTest extends TestCase
{
    public function testUploadFileThrowsIfMissing(): void
    {
        $client = new CloudBridgeClient('ak', 'sk', 'https://api.example.com');
        $this->expectException(\InvalidArgumentException::class);
        $client->uploadFile('/no/such/file.txt', 'folder');
    }

    public function testUploadFilesHappyPathReturnsArray(): void
    {
        $client = new class('ak', 'sk', 'https://api.example.com') extends CloudBridgeClient {
            protected function sendMultipartRequest($url, array $headers, array $postFields, $timeout)
            {
                $body = json_encode([
                    'success' => true,
                    'files' => [
                        ['filename' => 'a.txt', 'size' => 1, 'public_url' => 'https://u', 'short_url' => 'https://s', 'nextcloud_path' => 'apps/x']
                    ]
                ]);
                return [200, $body];
            }
        };

        $tmp = tempnam(sys_get_temp_dir(), 'cb');
        assert($tmp !== false);
        file_put_contents($tmp, 'x');

        $result = $client->uploadFiles([$tmp], 'ghost/up');
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['files']);

        unlink($tmp);
    }

    public function testInvalidCredentialsThrows(): void
    {
        $client = new class('ak', 'sk', 'https://api.example.com') extends CloudBridgeClient {
            protected function sendMultipartRequest($url, array $headers, array $postFields, $timeout)
            {
                $body = json_encode(['success' => false, 'message' => 'Invalid API credentials']);
                return [401, $body];
            }
        };

        $tmp = tempnam(sys_get_temp_dir(), 'cb');
        assert($tmp !== false);
        file_put_contents($tmp, 'x');

        $this->expectException(InvalidCredentialsException::class);
        try {
            $client->uploadFiles([$tmp], 'ghost/up');
        } finally {
            unlink($tmp);
        }
    }
}


