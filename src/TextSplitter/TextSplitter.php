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

use Exception;
use Hyperf\Odin\Document\Document;

abstract class TextSplitter
{
    protected $chunkSize;

    protected $chunkOverlap;

    protected $keepSeparator;

    protected $addStartIndex;

    protected $stripWhitespace;

    public function __construct(
        $chunkSize = 4000,
        $chunkOverlap = 200,
        $keepSeparator = false,
        $addStartIndex = false,
        $stripWhitespace = true
    ) {
        if ($chunkOverlap > $chunkSize) {
            throw new Exception("Got a larger chunk overlap ({$chunkOverlap}) than chunk size ({$chunkSize}), should be smaller.");
        }
        $this->chunkSize = $chunkSize;
        $this->chunkOverlap = $chunkOverlap;
        $this->keepSeparator = $keepSeparator;
        $this->addStartIndex = $addStartIndex;
        $this->stripWhitespace = $stripWhitespace;
    }

    public function splitDocuments(array $documents): array
    {
        $texts = [];
        $metadata = [];
        foreach ($documents as $document) {
            if (! $document instanceof Document) {
                continue;
            }
            $texts[] = $document->getContent();
            $metadata[] = $document->getMetadata();
        }
        return $this->createDocuments($texts, $metadata);
    }

    public function createDocuments(array $texts, array $metadata = []): array
    {
        $metadata = $metadata ?: array_fill(0, count($texts), []);
        $documents = [];
        foreach ($texts as $i => $text) {
            $index = 0;
            $previousChunkLen = 0;
            foreach ($this->splitText($text) as $chunk) {
                if ($this->addStartIndex) {
                    $offset = $index + $previousChunkLen - $this->chunkOverlap;
                    $index = strpos($text, $chunk, max(0, $offset));
                    $metadata[$i]['start_index'] = $index;
                    $previousChunkLen = $this->lengthFunction($chunk);
                }
                if (is_array($chunk)) {
                    var_dump($chunk);
                    exit;
                }
                $documents[] = new Document($chunk, $metadata[$i]);
            }
        }
        return $documents;
    }

    abstract public function splitText(string $text): array;

    protected function lengthFunction(string $text): int
    {
        $length = mb_strlen($text, 'UTF-8');
        return $length === false ? 0 : $length;
    }

    protected function mergeSplits(array $splits, string $separator): array
    {
        $separatorLen = $this->lengthFunction($separator);
        $docs = [];
        $currentDoc = [];
        $total = 0;
        foreach ($splits as $d) {
            $len = $this->lengthFunction($d);
            if ($total + $len + (count($currentDoc) > 0 ? $separatorLen : 0) > $this->chunkSize) {
                if ($total > $this->chunkSize) {
                    error_log("Created a chunk of size {$total}, which is longer than the specified {$this->chunkSize}");
                }
                if (count($currentDoc) > 0) {
                    $doc = $this->joinDocs($currentDoc, $separator);
                    if ($doc) {
                        $docs[] = $doc;
                    }
                    while ($total > $this->chunkOverlap || ($total + $len + (count($currentDoc) > 0 ? $separatorLen : 0) > $this->chunkSize && $total > 0)) {
                        $total -= $this->lengthFunction($currentDoc[0]) + (count($currentDoc) > 1 ? $separatorLen : 0);
                        array_shift($currentDoc);
                    }
                }
            }
            $currentDoc[] = $d;
            $total += $len + (count($currentDoc) > 1 ? $separatorLen : 0);
        }
        $doc = $this->joinDocs($currentDoc, $separator);
        if ($doc) {
            $docs[] = $doc;
        }
        return $docs;
    }

    protected function joinDocs(array $docs, string $separator): ?string
    {
        $text = implode($separator, $docs);
        if ($this->stripWhitespace) {
            $text = trim($text);
        }
        return $text === '' ? null : $text;
    }
}
