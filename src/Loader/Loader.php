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

namespace Hyperf\Odin\Loader;

use Hyperf\Odin\Document\MarkdownDocument;

class Loader
{
    public function loadMarkdownByFilePath(string $path): MarkdownDocument
    {
        $content = file_get_contents($path);
        $fileInfo = pathinfo($path);
        return new MarkdownDocument($content, [
            'file_name' => $fileInfo['filename'],
            'file_extension' => $fileInfo['extension'],
            'file_hash' => md5($fileInfo['basename'] . $content),
            'create_time' => $currentTime = date('Y-m-d H:i:s'),
            'update_time' => $currentTime,
        ]);
    }
}
