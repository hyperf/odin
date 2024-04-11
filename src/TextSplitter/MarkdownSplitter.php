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

class MarkdownSplitter extends RecursiveCharacterTextSplitter
{
    public function __construct(
        $separators = null,
        $isSeparatorRegex = false,
        $chunkSize = 4000,
        $chunkOverlap = 200,
        $keepSeparator = false,
        $addStartIndex = false,
        $stripWhitespace = true
    ) {
        if (! $separators) {
            $separators = [
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
            ];
        }
        parent::__construct($separators, $isSeparatorRegex, $chunkSize, $chunkOverlap, $keepSeparator, $addStartIndex, $stripWhitespace);
    }
}
