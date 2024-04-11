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

namespace Hyperf\Odin\Api\AzureOpenAI;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

class AzureOpenAIClientFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $defaultModel = $config->get('odin.llm.default_model', 'gpt-3.5-turbo');
        $azureConfig = new AzureOpenAIConfig($config->get('odin.azure', []));
        return (new AzureOpenAI())->getClient($azureConfig, $defaultModel);
    }
}
