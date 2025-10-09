# Manual Tests

This directory contains manual test scripts for verifying specific functionality.

## test_http_handler.php

Tests the HTTP handler factory's coroutine detection and handler selection logic.

### Running the test

#### In non-coroutine context (regular PHP):
```bash
php tests/manual/test_http_handler.php
```

Expected output:
- `in_coroutine_context: false`
- `recommended_handler: curl` (if cURL is available)

#### In coroutine context (Hyperf/Swoole):

To test in a coroutine context, create a Hyperf command with `$coroutine = true`:

```php
<?php
namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Odin\Api\Providers\HttpHandlerFactory;

#[Command()]
class TestHttpHandlerCommand extends \Hyperf\Command\Command
{
    // Enable coroutine mode (default is true)
    protected bool $coroutine = true;

    public function __construct()
    {
        parent::__construct('test:http-handler');
    }

    public function handle(): void
    {
        echo "=== HTTP Handler Test in Coroutine Context ===\n\n";
        
        // Test coroutine detection
        $inCoroutine = HttpHandlerFactory::isInCoroutineContext();
        echo "In coroutine context: " . ($inCoroutine ? "YES" : "NO") . "\n";
        
        // Test recommended handler
        $recommended = HttpHandlerFactory::getRecommendedHandler();
        echo "Recommended handler: $recommended\n";
        echo "Expected: stream (auto-detected in coroutine)\n\n";
        
        // Test environment info
        $envInfo = HttpHandlerFactory::getEnvironmentInfo();
        echo "Environment Info:\n";
        foreach ($envInfo as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                continue; // Skip complex arrays
            }
            echo "  - $key: $value\n";
        }
    }
}
```

Run with:
```bash
php bin/hyperf.php test:http-handler
```

Expected output in coroutine context:
- `in_coroutine_context: true`
- `recommended_handler: stream`

## Why This Matters

The automatic coroutine detection and handler switching solves the issue where:
- Swoole compiled with older OpenSSL versions
- cURL handler fails with "Connection refused" in HTTPS connections within coroutines
- Stream handler works correctly in both coroutine and non-coroutine contexts

The framework now automatically detects the coroutine context and switches to the stream handler to ensure compatibility.
