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

namespace Hyperf\Odin;

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIClientFactory;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\AzureOpenAI\DeploymentMapper;
use Hyperf\Odin\Apis\AzureOpenAI\DeploymentMapperFactory;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAIClientFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for odin.',
                    'source' => __DIR__ . '/../publish/odin.php',
                    'destination' => BASE_PATH . '/config/autoload/odin.php',
                ],
            ],
            'dependencies' => [
                OpenAIClient::class => OpenAIClientFactory::class,
                AzureOpenAIClient::class => AzureOpenAIClientFactory::class,
                DeploymentMapper::class => DeploymentMapperFactory::class,
            ],
            'commands' => [],
            'annotations' => [],
        ];
    }
}
