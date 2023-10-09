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

namespace Hyperf\Odin\Prompt;

use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;

class Prompt
{
    public static function input(string $input): array
    {
        $defaultSystemMessage = self::getPrompt('DefaultSystemMessage');
        return [
            'system' => new SystemMessage($defaultSystemMessage),
            'user' => new UserMessage($input),
        ];
    }

    public static function getPrompt(string $key, array $arguments = []): string
    {
        $prompt = match ($key) {
            'DefaultSystemMessage' => file_get_contents(__DIR__ . '/DefaultSystemMessage.prompt'),
            'CodeInterpreter' => file_get_contents(__DIR__ . '/CodeInterpreter.prompt'),
            'AfterCodeExecuted' => file_get_contents(__DIR__ . '/AfterCodeExecuted.prompt'),
        };
        if ($arguments) {
            foreach ($arguments as $key => $value) {
                if ($value === null) {
                    $value = '';
                }
                $prompt = str_replace("{{{$key}}}", $value, $prompt);
            }
        }
        return $prompt;
    }


}
