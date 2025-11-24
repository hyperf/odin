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

namespace Hyperf\Odin\Api\Providers\Gemini;

use Hyperf\Context\ApplicationContext;
use Hyperf\Odin\Exception\RuntimeException;
use Psr\SimpleCache\CacheInterface;

/**
 * Manager for Gemini thought signatures.
 *
 * Thought signatures are cryptographic representations of the model's internal thinking process,
 * used to preserve reasoning context across multi-turn interactions.
 *
 * @see https://ai.google.dev/gemini-api/docs/thought-signatures
 */
class ThoughtSignatureCache
{
    private const CACHE_PREFIX = 'gemini:thought_signature:';

    private const CACHE_TTL = 3600;

    /**
     * Store a thought signature for a tool call.
     *
     * @param string $toolCallId The tool call ID
     * @param string $thoughtSignature The thought signature from Gemini response
     */
    public static function store(string $toolCallId, string $thoughtSignature): void
    {
        $cache = self::getCacheDriver();
        $key = self::getCacheKey($toolCallId);
        $cache->set($key, $thoughtSignature, self::CACHE_TTL);
    }

    /**
     * Retrieve a thought signature for a tool call.
     *
     * @param string $toolCallId The tool call ID
     * @return null|string The thought signature, or null if not found
     */
    public static function get(string $toolCallId): ?string
    {
        $cache = self::getCacheDriver();
        $key = self::getCacheKey($toolCallId);
        $signature = $cache->get($key);
        return is_string($signature) ? $signature : null;
    }

    /**
     * Delete a thought signature for a tool call.
     *
     * @param string $toolCallId The tool call ID
     */
    public static function delete(string $toolCallId): void
    {
        $cache = self::getCacheDriver();
        $key = self::getCacheKey($toolCallId);
        $cache->delete($key);
    }

    /**
     * Check if cache is available.
     */
    public static function isAvailable(): bool
    {
        return self::getCacheDriver() !== null;
    }

    /**
     * Get cache key for a tool call ID.
     */
    private static function getCacheKey(string $toolCallId): string
    {
        return self::CACHE_PREFIX . $toolCallId;
    }

    private static function getCacheDriver(): CacheInterface
    {
        $cache = ApplicationContext::getContainer()->get(CacheInterface::class);
        if (! $cache instanceof CacheInterface) {
            throw new RuntimeException('CacheInterface must have a valid cache driver instance.');
        }
        return $cache;
    }
}
