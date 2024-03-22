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

namespace Hyperf\Odin\Tools;

use Hyperf\Odin\Wrapper\TavilySearchApiWrapper;
use InvalidArgumentException;
use Throwable;

class TavilySearchResults extends AbstractTool
{
    public string $name = 'tavily_search_results_json';

    public string $description
        = <<<'EOF'
A search engine optimized for comprehensive, accurate, and trusted results.
Useful for when you need to answer questions about current events.
Input should be a search query.
EOF;

    public array $parameters
        = [
            'query' => [
                'type' => 'string',
                'description' => 'The search query to use, the min query length is 5 characters, use Simplified Chinese as much as possible.',
                'required' => true,
            ],
        ];

    public function __construct(
        public TavilySearchApiWrapper $apiWrapper,
        public string $searchDepth = 'basic',
        public int $maxResults = 5,
        public bool $useAnswerDirectly = false,
    ) {
    }

    public function setMaxResults(int $maxResults): static
    {
        $this->maxResults = $maxResults;
        return $this;
    }

    public function getMaxResults(): int
    {
        return $this->maxResults;
    }

    public function getSearchDepth(): string
    {
        return $this->searchDepth;
    }

    public function isUseAnswerDirectly(): bool
    {
        return $this->useAnswerDirectly;
    }

    public function setUseAnswerDirectly(bool $useAnswerDirectly): static
    {
        $this->useAnswerDirectly = $useAnswerDirectly;
        return $this;
    }

    public function setSearchDepth(string $searchDepth): static
    {
        if (! in_array($searchDepth, ['basic', 'advanced'])) {
            throw new InvalidArgumentException('Invalid search depth. Must be one of: basic, advanced');
        }
        $this->searchDepth = $searchDepth;
        return $this;
    }

    public function invoke(string $query, bool $throwException = false): array
    {
        try {
            if ($this->isValidQuery($query)) {
                $result = $this->apiWrapper->results($query, $this->getMaxResults(), $this->getSearchDepth(), $this->isUseAnswerDirectly());
                if ($this->isUseAnswerDirectly()) {
                    $answer = $result['answer'] ?? '';
                    return ['answer' => $answer];
                }
                return $this->cleanResults($result);
            }
            return [];
        } catch (Throwable $e) {
            if ($throwException) {
                throw $e;
            }
            return [];
        }
    }

    protected function isValidQuery(string $query): bool
    {
        // Query is too short. Min query length is 5 characters.
        if (strlen($query) < 5) {
            throw new InvalidArgumentException('Query is too short. Min query length is 5 characters.');
        }
        return true;
    }

    protected function cleanResults(array $result): array
    {
        $filteredResult = [];
        foreach ($result['results'] ?? [] as $item) {
            $filteredResult[] = [
                'title' => $item['title'],
                'content' => $item['content'],
                'url' => $item['url'],
            ];
        }
        return $filteredResult;
    }
}
