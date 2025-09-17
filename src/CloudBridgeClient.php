<?php
declare(strict_types=1);

namespace CloudBridge;

use CloudBridge\Exceptions\InvalidCredentialsException;

/**
 * CloudBridge PHP SDK client (PHP 7.2+).
 */
class CloudBridgeClient
{
    /** @var string */
    private $accessKey;
    /** @var string */
    private $secretKey;
    /** @var string */
    private $baseUrl;
    /** @var int */
    private $timeout;

    public const DEFAULT_BASE_URL = 'https://api.cloudbridge.nugitech.com';
    public const UPLOAD_PATH = '/api/v1/public/upload';

    /**
     * @param string|null $accessKey
     * @param string|null $secretKey
     * @param string|null $baseUrl
     * @param int $timeout
     */
    public function __construct($accessKey = null, $secretKey = null, $baseUrl = null, $timeout = 60)
    {
        $envBaseUrl = getenv('CLOUDBRIDGE_BASE_URL') ?: null;
        $envAccessKey = getenv('CLOUDBRIDGE_ACCESS_KEY') ?: null;
        $envSecretKey = getenv('CLOUDBRIDGE_SECRET_KEY') ?: null;

        $this->accessKey = (string)($accessKey ?? $envAccessKey ?? '');
        $this->secretKey = (string)($secretKey ?? $envSecretKey ?? '');
        $this->baseUrl = rtrim((string)($baseUrl ?? $envBaseUrl ?? self::DEFAULT_BASE_URL), '/');
        $this->timeout = (int)$timeout;
    }

    /**
     * Set credentials at runtime.
     * @param string $accessKey
     * @param string $secretKey
     * @return void
     */
    public function setCredentials($accessKey, $secretKey)
    {
        $this->accessKey = (string)$accessKey;
        $this->secretKey = (string)$secretKey;
    }

    /**
     * Override the base API URL.
     * @param string $baseUrl
     * @return void
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim((string)$baseUrl, '/');
    }

    /**
     * Upload a single file to a folder.
     * @param string $filePath Absolute or relative file path
     * @param string $folder Target folder/path on CloudBridge
     * @return array<string, mixed> Associative array decoded from JSON
     * @throws InvalidCredentialsException
     * @throws \InvalidArgumentException If file does not exist
     */
    public function uploadFile($filePath, $folder)
    {
        return $this->uploadFiles([$filePath], $folder);
    }

    /**
     * Upload multiple files to a folder.
     * @param array<int, string> $filePaths List of file paths
     * @param string $folder Target folder/path on CloudBridge
     * @return array<string, mixed> Associative array decoded from JSON
     * @throws InvalidCredentialsException
     * @throws \InvalidArgumentException If any file does not exist
     */
    public function uploadFiles(array $filePaths, $folder)
    {
        $normalized = [];
        foreach ($filePaths as $index => $path) {
            if (!is_string($path)) {
                throw new \InvalidArgumentException('File path at index ' . $index . ' must be a string.');
            }
            $real = $this->normalizePath($path);
            if (!is_file($real)) {
                throw new \InvalidArgumentException('File not found: ' . $path);
            }
            $normalized[] = $real;
        }

        $headers = $this->buildAuthHeaders();
        /** @var array<string, mixed> $postFields */
        $postFields = ['folder' => (string)$folder];

        // Attach multiple files as files[0], files[1], ...
        foreach ($normalized as $i => $file) {
            $mime = function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream';
            $postFields['files[' . $i . ']'] = curl_file_create($file, $mime, basename($file));
        }

        list($statusCode, $body) = $this->sendMultipartRequest($this->baseUrl . self::UPLOAD_PATH, $headers, $postFields, $this->timeout);

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $decoded = ['success' => false, 'status' => 'error', 'message' => 'Invalid JSON response', 'raw' => $body];
        }

        $message = isset($decoded['message']) && is_string($decoded['message']) ? $decoded['message'] : '';
        if ($statusCode === 401 || stripos($message, 'Invalid API credentials') !== false) {
            throw new InvalidCredentialsException($message !== '' ? $message : 'Invalid API credentials');
        }

        return $decoded;
    }

    /**
     * Normalize paths across platforms.
     * @param string $path
     * @return string
     */
    private function normalizePath($path)
    {
        $path = str_replace(['\\'], DIRECTORY_SEPARATOR, $path);
        return $path;
    }

    /**
     * Build required auth headers.
     * @return array<int, string>
     */
    private function buildAuthHeaders()
    {
        $signature = hash_hmac('sha256', (string)$this->accessKey, (string)$this->secretKey);
        return [
            'x-access-key: ' . $this->accessKey,
            'x-signature: ' . $signature,
            'Accept: application/json',
        ];
    }

    /**
     * Perform a multipart/form-data POST using cURL.
     * @param string $url
     * @param array<int, string> $headers Header lines
     * @param array<string, mixed> $postFields Fields including CURLFile entries
     * @param int $timeout Timeout seconds
     * @return array{0:int,1:string} [statusCode, body]
     */
    protected function sendMultipartRequest($url, array $headers, array $postFields, $timeout)
    {
        $ch = curl_init();
        if ($ch === false) {
            return [0, ''];
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeout);

        $response = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [0, json_encode(['success' => false, 'status' => 'error', 'message' => $error]) ?: ''];
        }

        curl_close($ch);
        return [$statusCode, (string)$response];
    }
}


