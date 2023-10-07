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

class Model
{
    public function __construct(
        public string $name,
        public string $apiType,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getApiType(): string
    {
        return $this->apiType;
    }

    public function setApiType(string $apiType): static
    {
        $this->apiType = $apiType;
        return $this;
    }
}
