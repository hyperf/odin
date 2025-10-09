# Coroutine Stream Response Fix - Visual Flow

## Before the Fix

```
┌─────────────────────────────────────────────────────────────┐
│                    Hyperf Command                            │
│                  ($coroutine = true)                         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│              Model->chatStream($messages)                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         HttpHandlerFactory::create('auto')                   │
│         Always uses: Guzzle's default handler (cURL)         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         cURL Handler in Coroutine Context                    │
│         with old Swoole OpenSSL version                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
                        ❌ FAILS ❌
              "Connection refused for URI"
```

## After the Fix

```
┌─────────────────────────────────────────────────────────────┐
│                    Hyperf Command                            │
│                  ($coroutine = true)                         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│              Model->chatStream($messages)                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         HttpHandlerFactory::create('auto')                   │
│         ┌─────────────────────────────────────┐              │
│         │ isInCoroutineContext()              │              │
│         │   ✓ Swoole\Coroutine::getCid() > 0  │              │
│         │     OR                               │              │
│         │   ✓ Hyperf\Engine\Coroutine::id()>0 │              │
│         └─────────────────────────────────────┘              │
│         Decision: USE STREAM HANDLER                         │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         Stream Handler (Pure PHP)                            │
│         No cURL, No Swoole SSL dependency                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
                        ✅ SUCCESS ✅
                 Stream Response Works!
```

## Non-Coroutine Context (Unchanged Behavior)

```
┌─────────────────────────────────────────────────────────────┐
│                    Single File Script                        │
│                  (No coroutine)                              │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│              Model->chatStream($messages)                    │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         HttpHandlerFactory::create('auto')                   │
│         ┌─────────────────────────────────────┐              │
│         │ isInCoroutineContext()              │              │
│         │   ✗ Not in coroutine                │              │
│         └─────────────────────────────────────┘              │
│         Decision: USE DEFAULT (cURL preferred)               │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│         cURL Handler (Better Performance)                    │
│         No coroutine context issues                          │
└─────────────────────────────────────────────────────────────┘
                              ↓
                        ✅ SUCCESS ✅
                 Stream Response Works!
```

## Key Detection Logic

```php
public static function isInCoroutineContext(): bool
{
    // Check Swoole coroutine
    if (class_exists(\Swoole\Coroutine::class, false)) {
        try {
            $cid = \Swoole\Coroutine::getCid();
            return $cid > 0;  // -1 or 0 = not in coroutine
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Check Hyperf Engine coroutine (Swoole/Swow)
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

## Handler Selection Matrix

| Context        | Handler Type | Mode   | Result          |
|----------------|--------------|--------|-----------------|
| Coroutine      | auto         | ✅     | stream          |
| Coroutine      | stream       | ✅     | stream          |
| Coroutine      | curl         | ⚠️     | curl (explicit) |
| Non-Coroutine  | auto         | ✅     | curl/auto       |
| Non-Coroutine  | stream       | ✅     | stream          |
| Non-Coroutine  | curl         | ✅     | curl            |

✅ = Recommended/Safe
⚠️ = Not recommended but allowed (user override)

## Benefits Summary

1. **Zero Configuration**: Automatically works in coroutine contexts
2. **Performance**: Uses cURL in non-coroutine contexts for better performance
3. **Compatibility**: Stream handler works with all OpenSSL versions
4. **Flexibility**: Users can still override with explicit configuration
5. **Safety**: Graceful fallback on detection errors
