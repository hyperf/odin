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

use Hyperf\Odin\Exception\LLMException\ErrorMessage;
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Throwable;

/**
 * 连接超时异常.
 */
class LLMConnectionTimeoutException extends LLMNetworkException
{
    /**
     * 错误码，基于网络错误基数.
     */
    private const ERROR_CODE = 1;

    /**
     * 超时时间（秒）.
     */
    protected ?float $timeoutSeconds = null;

    /**
     * 创建一个新的连接超时异常实例.
     */
    public function __construct(string $message = ErrorMessage::CONNECTION_TIMEOUT, ?Throwable $previous = null, ?float $timeoutSeconds = null, int $statusCode = 408)
    {
        $this->timeoutSeconds = $timeoutSeconds;

        if ($timeoutSeconds !== null) {
            $message = sprintf('%s, timeout: %.2f seconds', $message, $timeoutSeconds);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, 0, $statusCode);
    }

    /**
     * 获取超时时间（秒）.
     */
    public function getTimeoutSeconds(): ?float
    {
        return $this->timeoutSeconds;
    }
}
