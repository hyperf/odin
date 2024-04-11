<?php

use Hyperf\Odin\Api\AzureOpenAI\AzureOpenAIClientFactory;
use Hyperf\Odin\Api\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Api\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Api\OpenAI\OpenAIClientFactory;

return [
    OpenAIClient::class => OpenAIClientFactory::class,
    AzureOpenAIClient::class => AzureOpenAIClientFactory::class,
];