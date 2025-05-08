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

namespace HyperfTest\Odin\Mock;

use DateInterval;
use DateTime;
use Psr\SimpleCache\CacheInterface;

class Cache implements CacheInterface
{
    /**
     * 内存存储数组.
     */
    private array $storage = [];

    /**
     * 过期时间存储.
     */
    private array $expires = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->storage[$key];
        }

        return $default;
    }

    public function set(string $key, mixed $value, null|DateInterval|int $ttl = null): bool
    {
        $this->storage[$key] = $value;

        if ($ttl !== null) {
            $expiration = time();
            if ($ttl instanceof DateInterval) {
                $expiration += (new DateTime())->add($ttl)->getTimestamp() - time();
            } else {
                $expiration += (int) $ttl;
            }
            $this->expires[$key] = $expiration;
        } else {
            // 不设置过期时间
            $this->expires[$key] = null;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key], $this->expires[$key]);
            return true;
        }

        return false;
    }

    public function clear(): bool
    {
        $this->storage = [];
        $this->expires = [];

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

    public function setMultiple(iterable $values, null|DateInterval|int $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                $key = (string) $key;
            }
            $success = $success && $this->set($key, $value, $ttl);
        }

        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $success && $this->delete($key);
        }

        return $success;
    }

    public function has(string $key): bool
    {
        if (! isset($this->storage[$key])) {
            return false;
        }

        // 检查是否过期
        if (isset($this->expires[$key]) && $this->expires[$key] !== null && $this->expires[$key] < time()) {
            // 自动删除过期数据
            $this->delete($key);
            return false;
        }

        return true;
    }
}
