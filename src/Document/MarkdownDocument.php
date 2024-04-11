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

use Hyperf\Odin\TextSplitter\MarkdownSplitter;

class MarkdownDocument extends Document
{
    public function split(): array
    {
        $recursiveCharacterTextSplitter = new MarkdownSplitter();
        return $recursiveCharacterTextSplitter->splitText($this->getContent());
    }

}
