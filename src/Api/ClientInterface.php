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

namespace Hyperf\Odin\Api;

use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;

interface ClientInterface
{
    /**
     * 暂时按 OpenAI 的返回类型作为标准，后续再考虑是否需要抽象。
     */
    public function chat(
        array $messages,
        string $model,
        float $temperature = 0.9,
        int $maxTokens = 1000,
        array $stop = [],
        array $tools = [],
        bool $stream = false,
    ): ChatCompletionResponse;
}
