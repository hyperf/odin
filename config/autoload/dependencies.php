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
use Hyperf\Odin\Api\AzureOpenAI\AzureOpenAIClientFactory;
use Hyperf\Odin\Api\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Api\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Api\OpenAI\OpenAIClientFactory;

return [
    OpenAIClient::class => OpenAIClientFactory::class,
    AzureOpenAIClient::class => AzureOpenAIClientFactory::class,
];
