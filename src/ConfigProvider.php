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

use Hyperf\Odin\Event\EventCallbackListener;
use Hyperf\Odin\VectorStore\Qdrant\Qdrant;
use Hyperf\Odin\VectorStore\Qdrant\QdrantFactory;

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
                Qdrant::class => QdrantFactory::class,
            ],
            'listeners' => [
                EventCallbackListener::class,
            ],
        ];
    }
}
