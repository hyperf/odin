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
use Throwable;

/**
 * 思考阶段流式响应超时异常.
 */
class LLMThinkingStreamTimeoutException extends LLMStreamTimeoutException
{
    /**
     * 创建一个新的思考阶段流式响应超时异常实例.
     */
    public function __construct(
        string $message = ErrorMessage::FIRST_CHUNK_TIMEOUT,
        ?Throwable $previous = null,
        ?float $timeoutSeconds = null,
        int $statusCode = 408
    ) {
        parent::__construct($message, $previous, 'initial_response', $timeoutSeconds, $statusCode);
    }
}
