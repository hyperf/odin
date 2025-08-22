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

namespace Hyperf\Odin\Exception\LLMException\Configuration;

use Hyperf\Odin\Exception\LLMException\LLMConfigurationException;
use Throwable;

/**
 * 无效终端点URL异常.
 */
class LLMInvalidEndpointException extends LLMConfigurationException
{
    /**
     * 错误码，基于配置错误基数.
     */
    private const ERROR_CODE = 2;

    /**
     * 终端点URL.
     */
    protected ?string $endpoint = null;

    /**
     * 创建一个新的无效终端点异常实例.
     */
    public function __construct(string $message = '无效的API终端点URL', ?Throwable $previous = null, ?string $endpoint = null, int $statusCode = 400)
    {
        $this->endpoint = $endpoint;

        if ($endpoint) {
            $message = sprintf('%s: %s', $message, $endpoint);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, 0, $statusCode);
    }

    /**
     * 获取终端点URL.
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }
}
