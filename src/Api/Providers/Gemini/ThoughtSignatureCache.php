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
use Hyperf\Odin\Contract\Message\MessageInterface;
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

    private const CACHE_TTL = 7200;

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
     * Calculate cumulative context hash from message list.
     * This creates a unique hash based on all previous messages in the conversation.
     *
     * @param MessageInterface[] $messages Array of messages
     * @param int $upToIndex Calculate hash up to this index (exclusive)
     * @return string The cumulative context hash
     */
    public static function calculateContextHash(array $messages, int $upToIndex): string
    {
        $hash = '';
        for ($i = 0; $i < $upToIndex && $i < count($messages); ++$i) {
            $message = $messages[$i];
            // Accumulate hash = md5(previous_hash + message_hash)
            // Using getHash() which includes all message properties
            $hash = md5($hash . $message->getHash());
        }
        return $hash;
    }

    /**
     * Generate cache key for normal message thought signature.
     * Uses context hash to ensure uniqueness within a conversation.
     *
     * @param string $contextHash The cumulative context hash
     * @param string $content The message content
     * @return string The cache key
     */
    public static function generateMessageKey(string $contextHash, string $content): string
    {
        return 'msg_' . $contextHash . '_' . md5($content);
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
