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

namespace Hyperf\Odin\Api\Chatglm;

class ChatglmConfig
{
    public function __construct(
        public string $apiKey,
        public string $host = 'https://open.bigmodel.cn',
    ) {
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getHost(bool $withSchema = true): string
    {
        if ($withSchema) {
            return $this->host;
        }
        return str_replace(['http://', 'https://'], '', $this->host);
    }
}
