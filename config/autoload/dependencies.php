<?php

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIClientFactory;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAIClientFactory;

return [
    OpenAIClient::class => OpenAIClientFactory::class,
    AzureOpenAIClient::class => AzureOpenAIClientFactory::class,
];