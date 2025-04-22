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

namespace Hyperf\Odin\Message;

/**
 * @document https://docs.aws.amazon.com/zh_cn/bedrock/latest/userguide/prompt-caching.html.
 */
class CachePoint
{
    /**
     * 缓存点类型
     * default: 默认类型
     * ephemeral: 短暂类型，不会创建实际的缓存点.
     */
    private string $type;

    public function __construct(string $type = 'default')
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
        ];
    }
}
