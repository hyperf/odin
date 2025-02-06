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

class RecursiveCharacterTextSplitter extends TextSplitter
{
    protected array $langSeparators
        = [
            'markdown' => [
                # First, try to split along Markdown headings (starting with level 2)
                "\n#{1,6} ",
                # Note the alternative syntax for headings (below) is not handled here
                # Heading level 2
                # ---------------
                # End of code block
                "```\n",
                # Horizontal lines
                "\n\\*\\*\\*+\n",
                "\n---+\n",
                "\n___+\n",
                # Note that this splitter doesn't handle horizontal lines defined
                # by *three or more* of ***, ---, or ___, but this is not handled
                "\n\n",
                "\n",
                ' ',
                '',
            ],
        ];

    protected bool $isSeparatorRegex = false;

    private array $separators = [];

    public function __construct(
        $separators = null,
        $isSeparatorRegex = false,
        $chunkSize = 4000,
        $chunkOverlap = 200,
        $keepSeparator = false,
        $addStartIndex = false,
        $stripWhitespace = true
    ) {
        parent::__construct($chunkSize, $chunkOverlap, $keepSeparator, $addStartIndex, $stripWhitespace);
        $this->separators = $separators ?: ["\n\n", "\n", ' ', ''];
        $this->isSeparatorRegex = $isSeparatorRegex;
    }

    public function splitText(string $text): array
    {
        return $this->split($text, $this->separators);
    }

    protected function splitTextWithRegex(string $text, string $separator, bool $keepSeparator): array
    {
        $splits = [];
        if ($separator) {
            if ($keepSeparator) {
                $_splits = preg_split('/(' . $separator . ')/', $text);
                for ($i = 1; $i < count($_splits); $i += 2) {
                    $splits[] = $_splits[$i] . $_splits[$i + 1];
                }
                if (count($_splits) % 2 === 0) {
                    $splits[] = end($_splits);
                }
                array_unshift($splits, $_splits[0]);
            } else {
                $splits = preg_split('/' . $separator . '/', $text);
            }
        } else {
            $splits = str_split($text);
        }
        return array_filter($splits, function ($value) {
            return $value !== '';
        });
    }

    private function split(string $text, array $separators): array
    {
        $finalChunks = [];
        $separator = end($separators);
        $newSeparators = [];

        foreach ($separators as $i => $_s) {
            $_separator = $this->isSeparatorRegex ? $_s : preg_quote($_s, '/');
            if ($_s === '') {
                $separator = $_s;
                break;
            }
            if (preg_match("/{$_separator}/", $text)) {
                $separator = $_s;
                $newSeparators = array_slice($separators, $i + 1);
                break;
            }
        }

        $_separator = $this->isSeparatorRegex ? $separator : preg_quote($separator, '/');
        $splits = $this->splitTextWithRegex($text, $_separator, $this->keepSeparator);
        $goodSplits = [];
        $_separator = $this->keepSeparator ? '' : $separator;
        foreach ($splits as $s) {
            if ($this->lengthFunction($s) < $this->chunkSize) {
                $goodSplits[] = $s;
            } else {
                if ($goodSplits) {
                    $mergedText = $this->mergeSplits($splits, $_separator);
                    $finalChunks[] = $mergedText;
                    $goodSplits = [];
                }
                if (! $newSeparators) {
                    $finalChunks[] = $s;
                } else {
                    $otherInfo = $this->split($s, $newSeparators);
                    $finalChunks = array_merge($finalChunks, $otherInfo);
                }
            }
        }

        if ($goodSplits) {
            $mergedText = $this->mergeSplits($splits, $_separator);
            $finalChunks = array_merge($finalChunks, $mergedText);
        }
        return $finalChunks;
    }
}
