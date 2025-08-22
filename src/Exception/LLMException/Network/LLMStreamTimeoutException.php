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

namespace Hyperf\Odin\Exception\LLMException\Network;

use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Throwable;

/**
 * 流式响应超时异常.
 */
class LLMStreamTimeoutException extends LLMNetworkException
{
    /**
     * 错误码，基于网络错误基数.
     */
    private const ERROR_CODE = 3;

    /**
     * 超时类型.
     */
    protected string $timeoutType;

    /**
     * 创建一个新的流式响应超时异常实例.
     */
    public function __construct(
        string $message = '流式响应超时',
        ?Throwable $previous = null,
        string $timeoutType = 'total',
        ?float $timeoutSeconds = null,
        int $statusCode = 408
    ) {
        $this->timeoutType = $timeoutType;

        if ($timeoutSeconds !== null) {
            $message = sprintf('%s，超时类型: %s，已等待: %.2f秒', $message, $timeoutType, $timeoutSeconds);
        } else {
            $message = sprintf('%s，超时类型: %s', $message, $timeoutType);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, 0, $statusCode);
    }

    /**
     * 获取超时类型.
     */
    public function getTimeoutType(): string
    {
        return $this->timeoutType;
    }
}
