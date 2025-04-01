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
        string $message = '等待首个流式响应块超时',
        ?Throwable $previous = null,
        ?float $timeoutSeconds = null
    ) {
        parent::__construct($message, $previous, 'initial_response', $timeoutSeconds);
    }
}
