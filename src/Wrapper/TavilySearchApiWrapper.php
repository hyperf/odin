<?php

namespace Hyperf\Odin\Wrapper;


use GuzzleHttp\Client;
use Hyperf\Contract\ConfigInterface;

class TavilySearchApiWrapper
{

    protected const API_URL = 'https://api.tavily.com';

    protected Client $client;

    protected array $apiKeys;

    public function __construct(Client $client, ConfigInterface $config)
    {
        $this->client = $client;
        $apiKey = $config->get('odin.tavily.api_key');
        $this->apiKeys = explode(',', $apiKey);
    }

    public function results(
        string $query,
        int $maxResults = 5,
        string $searchDepth = 'basic',
        $includeAnswer = false
    ): array {
        return $this->rawResults($query, $maxResults, $searchDepth, includeAnswer: $includeAnswer);
    }

    protected function rawResults(
        string $query,
        int $maxResults = 5,
        string $searchDepth = 'basic',
        array $includeDomains = [],
        array $excludeDomains = [],
        bool $includeAnswer = false,
        bool $includeRawContent = false,
        bool $includeImages = false
    ): array {
        $uri = self::API_URL . '/search';
        $randApiKey = $this->apiKeys[array_rand($this->apiKeys)];
        $response = $this->client->post($uri, [
            'json' => [
                'api_key' => $randApiKey,
                'query' => $query,
                'max_results' => $maxResults,
                'search_depth' => $searchDepth,
                "include_domains" => $includeDomains,
                "exclude_domains" => $excludeDomains,
                "include_answer" => $includeAnswer,
                "include_raw_content" => $includeRawContent,
                "include_images" => $includeImages,
            ],
            'verify' => false,
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Failed to fetch results from Tavily Search API with status code ' . $response->getStatusCode());
        }
        return json_decode($response->getBody()->getContents(), true);
    }

}