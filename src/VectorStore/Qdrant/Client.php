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
namespace Hyperf\Odin\VectorStore\Qdrant;

use GuzzleHttp\ClientTrait;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Client implements ClientInterface
{
    use ClientTrait;

    protected \GuzzleHttp\Client $client;

    public function __construct(protected ConfigInterface $config)
    {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => new Uri($config->getScheme() . '://' . $config->getHost() . ':' . $config->getPort()),
        ]);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->send($request);
    }

    public function request(string $method, $uri, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }

    public function requestAsync(string $method, $uri, array $options = []): PromiseInterface
    {
        throw new RuntimeException('Not implemented.');
    }
}
