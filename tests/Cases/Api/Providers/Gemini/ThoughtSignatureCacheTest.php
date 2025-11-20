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

namespace HyperfTest\Odin\Cases\Api\Providers\Gemini;

use DateInterval;
use DateTime;
use Hyperf\Odin\Api\Providers\Gemini\ThoughtSignatureCache;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Providers\Gemini\ThoughtSignatureCache
 */
class ThoughtSignatureCacheTest extends AbstractTestCase
{
    private CacheInterface $cache;

    private ThoughtSignatureCache $thoughtSignatureCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new InMemoryCache();
        $this->thoughtSignatureCache = new ThoughtSignatureCache($this->cache);
    }

    public function testStoreAndGet()
    {
        $toolCallId = 'call_123456';
        $thoughtSignature = 'EoAiCv0hAdHtim9bajzlkTVfjaaMmVOlEl1fFDOhEcBv';

        // Store thought signature
        $this->thoughtSignatureCache->store($toolCallId, $thoughtSignature);

        // Retrieve thought signature
        $retrieved = $this->thoughtSignatureCache->get($toolCallId);
        $this->assertSame($thoughtSignature, $retrieved);
    }

    public function testGetNonExistentKey()
    {
        $result = $this->thoughtSignatureCache->get('non_existent_key');
        $this->assertNull($result);
    }

    public function testStoreEmptySignature()
    {
        $toolCallId = 'call_empty';

        // Store empty signature (should be ignored)
        $this->thoughtSignatureCache->store($toolCallId, '');

        // Should not be stored
        $result = $this->thoughtSignatureCache->get($toolCallId);
        $this->assertNull($result);
    }

    public function testDelete()
    {
        $toolCallId = 'call_to_delete';
        $thoughtSignature = 'SomeSignature123';

        // Store
        $this->thoughtSignatureCache->store($toolCallId, $thoughtSignature);
        $this->assertNotNull($this->thoughtSignatureCache->get($toolCallId));

        // Delete
        $this->thoughtSignatureCache->delete($toolCallId);
        $this->assertNull($this->thoughtSignatureCache->get($toolCallId));
    }

    public function testIsAvailableWithCache()
    {
        $this->assertTrue($this->thoughtSignatureCache->isAvailable());
    }

    public function testIsAvailableWithoutCache()
    {
        $cache = new ThoughtSignatureCache(null);
        $this->assertFalse($cache->isAvailable());
    }

    public function testStoreWithNullCache()
    {
        $cache = new ThoughtSignatureCache(null);

        // Should not throw exception, just silently do nothing
        $cache->store('call_123', 'signature');

        // Cannot retrieve
        $result = $cache->get('call_123');
        $this->assertNull($result);
    }

    public function testGetWithNullCache()
    {
        $cache = new ThoughtSignatureCache(null);

        $result = $cache->get('call_123');
        $this->assertNull($result);
    }

    public function testDeleteWithNullCache()
    {
        $cache = new ThoughtSignatureCache(null);

        // Should not throw exception
        $cache->delete('call_123');
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testCacheKeyFormat()
    {
        $toolCallId = 'test_call_id';
        $thoughtSignature = 'TestSignature';

        $this->thoughtSignatureCache->store($toolCallId, $thoughtSignature);

        // Verify the key format in underlying cache
        $expectedKey = 'gemini:thought_signature:' . $toolCallId;
        $this->assertTrue($this->cache->has($expectedKey));
        $this->assertSame($thoughtSignature, $this->cache->get($expectedKey));
    }

    public function testMultipleToolCalls()
    {
        $toolCalls = [
            'call_1' => 'Signature1',
            'call_2' => 'Signature2',
            'call_3' => 'Signature3',
        ];

        // Store multiple
        foreach ($toolCalls as $id => $signature) {
            $this->thoughtSignatureCache->store($id, $signature);
        }

        // Retrieve all
        foreach ($toolCalls as $id => $signature) {
            $retrieved = $this->thoughtSignatureCache->get($id);
            $this->assertSame($signature, $retrieved);
        }

        // Delete one
        $this->thoughtSignatureCache->delete('call_2');
        $this->assertNull($this->thoughtSignatureCache->get('call_2'));

        // Others should still exist
        $this->assertSame('Signature1', $this->thoughtSignatureCache->get('call_1'));
        $this->assertSame('Signature3', $this->thoughtSignatureCache->get('call_3'));
    }

    public function testOverwriteExistingSignature()
    {
        $toolCallId = 'call_overwrite';
        $signature1 = 'FirstSignature';
        $signature2 = 'SecondSignature';

        // Store first
        $this->thoughtSignatureCache->store($toolCallId, $signature1);
        $this->assertSame($signature1, $this->thoughtSignatureCache->get($toolCallId));

        // Overwrite
        $this->thoughtSignatureCache->store($toolCallId, $signature2);
        $this->assertSame($signature2, $this->thoughtSignatureCache->get($toolCallId));
    }

    public function testCacheTTL()
    {
        $toolCallId = 'call_ttl_test';
        $thoughtSignature = 'TTLSignature';

        // Store with TTL
        $this->thoughtSignatureCache->store($toolCallId, $thoughtSignature);

        // Verify TTL was set in underlying cache (should be 3600 seconds = 1 hour)
        $expectedKey = 'gemini:thought_signature:' . $toolCallId;

        // Use InMemoryCache's getTTL method for testing
        if ($this->cache instanceof InMemoryCache) {
            $ttl = $this->cache->getTTL($expectedKey);
            $this->assertNotNull($ttl);
            $this->assertGreaterThan(0, $ttl);
            $this->assertLessThanOrEqual(3600, $ttl);
        }
    }

    public function testLongSignature()
    {
        $toolCallId = 'call_long';
        // Simulate a very long thought signature (real ones can be quite long)
        $longSignature = str_repeat('AbCdEf123456', 100);

        $this->thoughtSignatureCache->store($toolCallId, $longSignature);
        $retrieved = $this->thoughtSignatureCache->get($toolCallId);

        $this->assertSame($longSignature, $retrieved);
    }

    public function testSpecialCharactersInSignature()
    {
        $toolCallId = 'call_special';
        // Base64-like characters (what real thought signatures look like)
        $signature = 'EoAiCv0h+/=AdHtim9bajzlkTVfjaaMmVOlEl1f=';

        $this->thoughtSignatureCache->store($toolCallId, $signature);
        $retrieved = $this->thoughtSignatureCache->get($toolCallId);

        $this->assertSame($signature, $retrieved);
    }

    public function testSpecialCharactersInToolCallId()
    {
        $toolCallId = 'call_123-abc_def.xyz';
        $signature = 'TestSignature';

        $this->thoughtSignatureCache->store($toolCallId, $signature);
        $retrieved = $this->thoughtSignatureCache->get($toolCallId);

        $this->assertSame($signature, $retrieved);
    }
}

/**
 * Simple in-memory cache implementation for testing.
 * This is a REAL cache implementation, not a mock.
 */
class InMemoryCache implements CacheInterface
{
    private array $data = [];

    private array $ttls = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->has($key)) {
            return $default;
        }

        return $this->data[$key];
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->data[$key] = $value;

        if ($ttl !== null) {
            $seconds = $ttl instanceof DateInterval
                ? (new DateTime())->add($ttl)->getTimestamp() - time()
                : $ttl;
            $this->ttls[$key] = time() + $seconds;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->data[$key], $this->ttls[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->data = [];
        $this->ttls = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        // Check if key exists and not expired
        if (! array_key_exists($key, $this->data)) {
            return false;
        }

        // Check TTL
        if (isset($this->ttls[$key]) && $this->ttls[$key] < time()) {
            unset($this->data[$key], $this->ttls[$key]);
            return false;
        }

        return true;
    }

    /**
     * Get remaining TTL for a key (in seconds).
     * This is a helper method for testing, not part of PSR-16.
     */
    public function getTTL(string $key): ?int
    {
        if (! isset($this->ttls[$key])) {
            return null;
        }

        $remaining = $this->ttls[$key] - time();
        return max(0, $remaining);
    }
}
