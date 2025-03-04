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

namespace Hyperf\Odin\Contract\Api;

interface ConfigInterface
{
    /**
     * 获取API密钥.
     */
    public function getApiKey(): string;

    /**
     * 获取基础URL.
     */
    public function getBaseUrl(): string;

    public function toArray(): array;
}
