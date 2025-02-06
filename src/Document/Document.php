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

namespace Hyperf\Odin\Document;

use Hyperf\Odin\TextSplitter\RecursiveCharacterTextSplitter;

class Document
{
    public function __construct(protected string $content, protected array $metadata = []) {}

    public function split(): array
    {
        $recursiveCharacterTextSplitter = new RecursiveCharacterTextSplitter();
        return $recursiveCharacterTextSplitter->splitText($this->content);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function appendMetadata(string $key, string $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }
}
