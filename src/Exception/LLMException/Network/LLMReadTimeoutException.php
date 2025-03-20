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
 * 读取响应超时异常.
 */
class LLMReadTimeoutException extends LLMNetworkException
{
    /**
     * 错误码，基于网络错误基数.
     */
    private const ERROR_CODE = 2;

    /**
     * 超时时间（秒）.
     */
    protected ?float $timeoutSeconds = null;

    /**
     * 创建一个新的读取超时异常实例.
     */
    public function __construct(string $message = '从LLM服务读取响应超时', ?Throwable $previous = null, ?float $timeoutSeconds = null)
    {
        $this->timeoutSeconds = $timeoutSeconds;

        if ($timeoutSeconds !== null) {
            $message = sprintf('%s，超时时间: %.2f秒', $message, $timeoutSeconds);
        }

        parent::__construct($message, self::ERROR_CODE, $previous);
    }

    /**
     * 获取超时时间（秒）.
     */
    public function getTimeoutSeconds(): ?float
    {
        return $this->timeoutSeconds;
    }
}
