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

namespace Hyperf\Odin\Api\RequestOptions;

/**
 * API选项配置.
 * 该类专门用于配置API相关的参数，如超时设置等.
 */
class ApiOptions
{
    /**
     * @var array 超时配置（秒）
     */
    protected array $timeout = [
        'connection' => 5.0,  // 连接超时
        'write' => 10.0,      // 写入超时
        'read' => 300.0,      // 读取超时
        'total' => 350.0,     // 总体超时
        'thinking' => 120.0,  // 思考超时（初始响应前的时间）
        'stream_chunk' => 30.0, // 流式响应块间超时
        'stream_first' => 60.0, // 流式响应首个块超时
    ];

    /**
     * @var array 自定义错误映射规则
     */
    protected array $customErrorMappingRules = [];

    /**
     * @var null|string 代理配置
     */
    protected ?string $proxy = null;

    /**
     * 构造函数.
     *
     * @param array $options 配置选项
     */
    public function __construct(array $options = [])
    {
        if (isset($options['timeout']) && is_array($options['timeout'])) {
            $this->timeout = array_merge($this->timeout, $options['timeout']);
        }

        if (isset($options['custom_error_mapping_rules']) && is_array($options['custom_error_mapping_rules'])) {
            $this->customErrorMappingRules = $options['custom_error_mapping_rules'];
        }

        if (isset($options['proxy'])) {
            $this->proxy = $options['proxy'];
        }
    }

    /**
     * 从配置数组创建实例.
     */
    public static function fromArray(array $options = []): self
    {
        return new self($options);
    }

    /**
     * 将选项转换为数组.
     */
    public function toArray(): array
    {
        return [
            'timeout' => $this->timeout,
            'custom_error_mapping_rules' => $this->customErrorMappingRules,
            'proxy' => $this->proxy,
        ];
    }

    /**
     * 获取超时配置.
     */
    public function getTimeout(): array
    {
        return $this->timeout;
    }

    /**
     * 获取连接超时.
     */
    public function getConnectionTimeout(): float
    {
        return $this->timeout['connection'];
    }

    /**
     * 获取写入超时.
     */
    public function getWriteTimeout(): float
    {
        return $this->timeout['write'];
    }

    /**
     * 获取读取超时.
     */
    public function getReadTimeout(): float
    {
        return $this->timeout['read'];
    }

    /**
     * 获取总体超时.
     */
    public function getTotalTimeout(): float
    {
        return $this->timeout['total'];
    }

    /**
     * 获取思考超时时间（从请求到首次响应）.
     */
    public function getThinkingTimeout(): float
    {
        return $this->timeout['thinking'];
    }

    /**
     * 获取流式响应块间超时.
     */
    public function getStreamChunkTimeout(): float
    {
        return $this->timeout['stream_chunk'];
    }

    /**
     * 获取流式响应首个块超时.
     */
    public function getStreamFirstChunkTimeout(): float
    {
        return $this->timeout['stream_first'];
    }

    /**
     * 获取自定义错误映射规则.
     */
    public function getCustomErrorMappingRules(): array
    {
        return $this->customErrorMappingRules;
    }

    /**
     * 获取代理配置.
     */
    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    /**
     * 检查是否设置了代理.
     */
    public function hasProxy(): bool
    {
        return $this->proxy !== null;
    }
}
