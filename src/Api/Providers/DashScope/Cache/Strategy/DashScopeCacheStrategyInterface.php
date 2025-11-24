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

namespace Hyperf\Odin\Api\Providers\DashScope\Cache\Strategy;

use Hyperf\Odin\Api\Providers\DashScope\Cache\DashScopeAutoCacheConfig;
use Hyperf\Odin\Api\Request\ChatCompletionRequest;

interface DashScopeCacheStrategyInterface
{
    public function apply(DashScopeAutoCacheConfig $config, ChatCompletionRequest $request): void;
}
