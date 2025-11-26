<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

/**
 * Test script to verify coroutine detection and HTTP handler selection.
 * 
 * This script demonstrates:
 * 1. Coroutine detection in non-coroutine context
 * 2. Handler selection based on environment
 * 3. Environment information retrieval
 */

require_once __DIR__ . '/../../src/Api/Providers/HttpHandlerFactory.php';

use Hyperf\Odin\Api\Providers\HttpHandlerFactory;

echo "=== HTTP Handler Factory Test ===\n\n";

// Test 1: Check if in coroutine context
echo "1. Coroutine Detection Test:\n";
$inCoroutine = HttpHandlerFactory::isInCoroutineContext();
echo "   In coroutine context: " . ($inCoroutine ? "YES" : "NO") . "\n";
echo "   Expected: NO (running outside coroutine)\n\n";

// Test 2: Get recommended handler
echo "2. Recommended Handler Test:\n";
$recommended = HttpHandlerFactory::getRecommendedHandler();
echo "   Recommended handler: $recommended\n";
if ($inCoroutine) {
    echo "   Expected: stream (in coroutine context)\n";
} else {
    echo "   Expected: curl or stream (based on availability)\n";
}
echo "\n";

// Test 3: Environment information
echo "3. Environment Information:\n";
$envInfo = HttpHandlerFactory::getEnvironmentInfo();
foreach ($envInfo as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_array($value)) {
        $value = json_encode($value);
    } elseif (is_null($value)) {
        $value = 'null';
    }
    echo "   - $key: $value\n";
}
echo "\n";

// Test 4: Handler availability check
echo "4. Handler Availability Test:\n";
$streamAvailable = HttpHandlerFactory::isHandlerAvailable('stream');
$curlAvailable = HttpHandlerFactory::isHandlerAvailable('curl');
$autoAvailable = HttpHandlerFactory::isHandlerAvailable('auto');
echo "   Stream handler available: " . ($streamAvailable ? "YES" : "NO") . "\n";
echo "   Curl handler available: " . ($curlAvailable ? "YES" : "NO") . "\n";
echo "   Auto handler available: " . ($autoAvailable ? "YES" : "NO") . "\n";
echo "\n";

// Test 5: Handler selection logic
echo "5. Handler Selection Logic:\n";
if ($inCoroutine) {
    echo "   In coroutine context:\n";
    echo "   - 'auto' mode will use: stream handler\n";
    echo "   - This avoids OpenSSL compatibility issues\n";
} else {
    echo "   Not in coroutine context:\n";
    echo "   - 'auto' mode will use: default Guzzle selection\n";
    echo "   - Recommended: " . $recommended . "\n";
}
echo "\n";

echo "=== Test Complete ===\n";
