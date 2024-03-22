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

namespace Hyperf\Odin\Agent;

use Hyperf\Odin\Prompt\Prompt;

abstract class AbstractAgent
{
    protected string $name = '';

    protected string $prompt = '';

    protected string $defaultPrompt = '';

    public function __construct(string $prompt = '')
    {
        if (! $prompt) {
            if ($this->defaultPrompt) {
                $prompt = $this->defaultPrompt;
            } else {
                $prompt = Prompt::getPrompt($this->name);
            }
        }
        $this->prompt = $prompt;
    }
}
