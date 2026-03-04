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

namespace Hyperf\Odin\Api\Providers\DeepSeek;

use Hyperf\Context\ApplicationContext;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Cache manager for DeepSeek reasoning content.
 *
 * Reasoning content (reasoning_content) is the model's internal thinking process output.
 * In multi-turn tool calling scenarios, reasoning_content needs to be preserved and sent back
 * to the API to allow the model to continue its reasoning from where it left off.
 *
 * The cache uses tool_call_id as the key to store reasoning_content, allowing the client
 * to restore reasoning_content when processing subsequent tool result messages.
 *
 * @see https://api-docs.deepseek.com/zh-cn/guides/thinking_mode
 */
class ReasoningContentCache
{
    private const CACHE_PREFIX = 'deepseek:reasoning_content:';

    /**
     * Default cache TTL in seconds.
     */
    private const CACHE_TTL = 1800;

    /**
     * Store reasoning content for a tool call.
     *
     * @param string $toolCallId The tool call ID
     * @param string $reasoningContent The reasoning content from DeepSeek response
     */
    public static function store(string $toolCallId, string $reasoningContent): void
    {
        $cache = self::getCacheDriver();
        if ($cache === null) {
            return;
        }
        $key = self::getCacheKey($toolCallId);
        $cache->set($key, $reasoningContent, self::CACHE_TTL);
    }

    /**
     * Retrieve reasoning content for a tool call.
     *
     * @param string $toolCallId The tool call ID
     * @return null|string The reasoning content, or null if not found
     */
    public static function get(string $toolCallId): ?string
    {
        $cache = self::getCacheDriver();
        if ($cache === null) {
            return null;
        }
        $key = self::getCacheKey($toolCallId);
        $content = $cache->get($key);
        return is_string($content) ? $content : null;
    }

    /**
     * Delete reasoning content for a tool call.
     *
     * @param string $toolCallId The tool call ID
     */
    public static function delete(string $toolCallId): void
    {
        $cache = self::getCacheDriver();
        if ($cache === null) {
            return;
        }
        $key = self::getCacheKey($toolCallId);
        $cache->delete($key);
    }

    /**
     * Get cache key with prefix.
     */
    private static function getCacheKey(string $toolCallId): string
    {
        return self::CACHE_PREFIX . $toolCallId;
    }

    /**
     * Get cache driver instance.
     *
     * @return null|CacheInterface Returns null if cache is not available
     */
    private static function getCacheDriver(): ?CacheInterface
    {
        try {
            if (! ApplicationContext::hasContainer()) {
                return null;
            }
            $cache = ApplicationContext::getContainer()->get(CacheInterface::class);
            return $cache instanceof CacheInterface ? $cache : null;
        } catch (Throwable) {
            return null;
        }
    }
}
