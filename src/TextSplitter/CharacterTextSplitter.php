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

namespace Hyperf\Odin\TextSplitter;

class CharacterTextSplitter
{
    protected ?string $separator;

    protected bool $isSeparatorRegex;

    public function __construct(?string $separator = "\n\n", bool $isSeparatorRegex = false)
    {
        $this->separator = $separator;
        $this->isSeparatorRegex = $isSeparatorRegex;
    }

    public function splitText(string $text): array|bool
    {
        $separator = $this->isSeparatorRegex ? $this->separator : preg_quote($this->separator, '/');
        $splits = preg_split("/({$separator})/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        return array_filter($splits, function ($value) {
            return $value !== '';
        });
    }
}
