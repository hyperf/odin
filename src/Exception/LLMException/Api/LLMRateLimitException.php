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

namespace Hyperf\Odin\Exception\LLMException\Api;

use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Throwable;

/**
 * API请求速率限制异常.
 */
class LLMRateLimitException extends LLMApiException
{
    /**
     * 错误码，基于API错误基数.
     */
    private const ERROR_CODE = 1;

    /**
     * 建议的重试等待时间（秒）.
     */
    protected ?int $retryAfter = null;

    /**
     * 创建一个新的速率限制异常实例.
     */
    public function __construct(
        string $message = 'API请求频率超出限制',
        ?Throwable $previous = null,
        ?int $statusCode = 429,
        ?int $retryAfter = null
    ) {
        $this->retryAfter = $retryAfter;

        if ($retryAfter !== null) {
            $message = sprintf('%s，建议 %d 秒后重试', $message, $retryAfter);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, 0, $statusCode);
    }

    /**
     * 获取建议的重试等待时间（秒）.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
