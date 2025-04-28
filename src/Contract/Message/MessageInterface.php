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

namespace Hyperf\Odin\Contract\Message;

use Hyperf\Odin\Message\CachePoint;
use Hyperf\Odin\Message\Role;

/**
 * 消息接口.
 *
 * 定义所有消息类型的通用接口
 */
interface MessageInterface
{
    /**
     * 获取消息角色.
     *
     * @return Role 消息角色
     */
    public function getRole(): Role;

    /**
     * 获取消息内容.
     *
     * @return string 消息内容文本
     */
    public function getContent(): string;

    /**
     * 获取消息唯一标识
     * 非必须，默认为空字符串.
     *
     * @return string 消息唯一标识
     */
    public function getIdentifier(): string;

    /**
     * 设置消息唯一标识
     * 非必须，默认为空字符串.
     *
     * @param string $identifier 唯一标识
     * @return self 支持链式调用
     */
    public function setIdentifier(string $identifier): self;

    /**
     * 业务参数，可用于附加消息内容到后面处理.
     */
    public function getParams(): array;

    public function setParams(array $params): void;

    public function getCachePoint(): ?CachePoint;

    public function setCachePoint(?CachePoint $cachePoint): self;

    public function getTokenEstimate(): ?int;

    public function setTokenEstimate(?int $tokenEstimate): self;

    /**
     * 将消息转换为数组.
     *
     * @return array 消息数组表示
     */
    public function toArray(): array;

    public function getHash(): string;

    public static function fromArray(array $message): self;
}
