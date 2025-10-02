# CloudBridge PHP SDK (PHP 7.2+)

Production-grade PHP SDK to upload files to CloudBridge using cURL.

## Install

```bash
composer require nugitech/cloudbridge-php:dev-master
```

## Requirements
- PHP 7.2+
- ext-curl enabled

## Usage

```php
require 'vendor/autoload.php';

use CloudBridge\CloudBridgeClient;

$client = new CloudBridgeClient('NUGI-AK-ACCESS', 'SECRET');

// Single
$result = $client->uploadFile('/path/to/logo.png', 'ghost/up');
print_r($result);

// Multiple
$result = $client->uploadFiles([
    '/path/to/file1.jpg',
    '/path/to/file2.png'
], 'ghost/up');
print_r($result);
```

## Environment variables
- `CLOUDBRIDGE_BASE_URL` (default `https://api.cloudbridge.nugitech.com`)
- `CLOUDBRIDGE_ACCESS_KEY`
- `CLOUDBRIDGE_SECRET_KEY`

## API

- `__construct(?string $accessKey = null, ?string $secretKey = null, ?string $baseUrl = null, int $timeout = 60)`
- `setCredentials(string $accessKey, string $secretKey): void`
- `setBaseUrl(string $baseUrl): void`
- `uploadFile(string $filePath, string $folder): array`
- `uploadFiles(array $filePaths, string $folder): array`

### Auth & Endpoint
- Base URL: `https://api.cloudbridge.nugitech.com`
- Upload: `${BASE_URL}/api/v1/public/upload`
- Headers:
    - `x-access-key: <accessKey>`
    - `x-signature: HMAC-SHA256(accessKey, secretKey)`

### Responses
- Success
```json
{
    "success": true,
    "files": [
        {
            "filename": "pw.zip",
            "size": 62756366,
            "public_url": "https://...",
            "short_url": "https://...",
            "nextcloud_path": "apps/..."
        }
    ]
}
```
- Error (non-401)
```json
{
    "success": false,
    "status": "error",
    "message": "Validation failed",
    "errors": ["..."]
}
```
- Invalid credentials → throws `CloudBridge\\Exceptions\\InvalidCredentialsException`

## Testing
```bash
composer install
composer test
```

## License
MIT
