# Fix: Coroutine Stream Response Exception

## Problem

When executing stream responses in Hyperf's coroutine environment (e.g., Command with `$coroutine = true`), the application encounters "Connection refused" errors for HTTPS connections, even though the same code works perfectly in non-coroutine contexts (single-file mode).

### Error Example
```
LLM网络连接错误: Connection refused for URI https://one-api.system.xxx.com/v1/chat/completions
```

### Root Cause

According to the issue author (@huangdijia), the problem is related to the OpenSSL version that Swoole was compiled with. When Swoole is compiled with an older OpenSSL version, the cURL handler may not properly handle HTTPS connections within coroutine contexts, leading to connection failures.

## Solution

The fix automatically detects when code is running in a coroutine context and switches to PHP's stream handler, which:
- Is a pure PHP implementation
- Does not depend on Swoole's SSL/OpenSSL implementation
- Works reliably in both coroutine and non-coroutine contexts

## Implementation Details

### 1. Coroutine Detection

Added `isInCoroutineContext()` method to `HttpHandlerFactory` that detects:
- `Swoole\Coroutine` - Direct Swoole coroutine detection
- `Hyperf\Engine\Coroutine` - Hyperf's abstraction layer (supports both Swoole and Swow)

```php
public static function isInCoroutineContext(): bool
{
    // Check Swoole coroutine
    if (class_exists(\Swoole\Coroutine::class, false)) {
        try {
            $cid = \Swoole\Coroutine::getCid();
            return $cid > 0;  // > 0 means in coroutine
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Check Hyperf Engine coroutine
    if (class_exists(\Hyperf\Engine\Coroutine::class, false)) {
        try {
            $id = \Hyperf\Engine\Coroutine::id();
            return $id > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    return false;
}
```

### 2. Automatic Handler Switching

Modified `create()` method to automatically use stream handler in coroutine contexts:

```php
public static function create(string $type = 'auto'): callable
{
    // Automatically use stream handler in coroutine context to avoid OpenSSL compatibility issues
    if ($type === 'auto' && self::isInCoroutineContext()) {
        return self::createStreamHandler();
    }

    return match (strtolower($type)) {
        'stream' => self::createStreamHandler(),
        'auto' => self::createAutoHandler(),
        default => self::createCurlHandler(),
    };
}
```

### 3. Updated Recommendations

Modified `getRecommendedHandler()` to recommend stream handler in coroutine contexts:

```php
public static function getRecommendedHandler(): string
{
    // Recommend stream handler in coroutine context
    if (self::isInCoroutineContext()) {
        return 'stream';
    }

    if (function_exists('curl_multi_exec') && function_exists('curl_exec')) {
        return 'curl';
    }

    if (ini_get('allow_url_fopen')) {
        return 'stream';
    }

    return 'auto';
}
```

## Usage

### Automatic (Recommended)

No code changes required! The framework automatically detects coroutine context and uses the appropriate handler:

```php
$model = new DoubaoModel(
    'deepseek-r1-250120',
    [
        'api_key' => 'sk-xxx',
        'base_url' => 'https://api.example.com/v1',
    ],
    new Logger(),
);

// In coroutine context: automatically uses stream handler
// In non-coroutine context: uses default Guzzle selection
```

### Explicit Configuration

You can still explicitly specify the handler if needed:

```php
// Force stream handler
$model->setApiRequestOptions(new ApiOptions([
    'http_handler' => 'stream',
]));

// Force cURL handler (not recommended in coroutine context)
$model->setApiRequestOptions(new ApiOptions([
    'http_handler' => 'curl',
]));
```

### Environment Variable

Set globally via environment variable:
```env
ODIN_HTTP_HANDLER=stream
```

### Configuration File

Configure per model in `config/autoload/odin.php`:
```php
return [
    'models' => [
        'deepseek-r1' => [
            // ...
            'api_options' => [
                'http_handler' => 'stream',
            ],
        ],
    ],
];
```

## Testing

### Unit Tests

Added `HttpHandlerFactoryTest` with tests for:
- Coroutine detection
- Handler creation
- Environment information
- Handler availability

### Manual Testing

Created manual test script at `tests/manual/test_http_handler.php` that can be run:
- In non-coroutine context: `php tests/manual/test_http_handler.php`
- In coroutine context: Create a Hyperf command and run it

## Benefits

1. **Zero Configuration**: Works out of the box with no code changes
2. **Backward Compatible**: Existing explicit configurations continue to work
3. **Flexible**: Can still override with explicit handler selection
4. **Reliable**: Stream handler is stable in both environments
5. **Safe**: Uses try-catch blocks to handle detection failures gracefully

## Documentation

Updated documentation:
- Chinese FAQ (`doc/user-guide-cn/10-faq.md`)
- English FAQ (`doc/user-guide/10-faq.md`)
- Example code comments (`examples/stream.php`)
- Manual test README (`tests/manual/README.md`)

## Compatibility

- Works with Hyperf 2.2.x, 3.0.x, and 3.1.x
- Supports both Swoole and Swow (via Hyperf Engine)
- No breaking changes to existing API
- PHP 8.1+ required (as per existing requirements)
