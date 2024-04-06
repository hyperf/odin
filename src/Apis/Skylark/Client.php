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

namespace Hyperf\Odin\Apis\Skylark;

use GuzzleHttp\Client as GuzzleClient;
use Hyperf\Codec\Json;
use Hyperf\Odin\Apis\ClientInterface;
use Hyperf\Odin\Apis\OpenAI\Request\ToolDefinition;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Message\MessageInterface;
use Hyperf\Odin\Tools\ToolInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Client implements ClientInterface
{
    protected GuzzleClient $client;

    protected SkylarkConfig $config;

    protected ?LoggerInterface $logger;

    protected bool $debug = false;

    public function __construct(SkylarkConfig $config, LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->initConfig($config);
    }

    protected function initConfig(SkylarkConfig $config): static
    {
        $this->validConfig($config);
        $this->client = new GuzzleClient([
            'base_uri' => $config->getHost(),
            'timeout' => 120.0,
        ]);
        $this->config = $config;
        return $this;
    }

    private function validConfig(SkylarkConfig $config): void
    {
        if (! $config->getHost()) {
            throw new InvalidArgumentException('The host of Skylark is required.');
        }
        if (! $config->getAk()) {
            throw new InvalidArgumentException('The ak of Skylark is required.');
        }
        if (! $config->getSk()) {
            throw new InvalidArgumentException('The sk of Skylark is required.');
        }
        if (! $config->getRegion()) {
            throw new InvalidArgumentException('The region of Skylark is required.');
        }
        if (! $config->getEndpoint()) {
            throw new InvalidArgumentException('The endpoint of Skylark is required.');
        }
    }

    public function chat(
        array $messages,
        string $model,
        float $temperature = 0.9,
        int $maxTokens = 1000,
        array $stop = [],
        array $tools = [],
        bool $stream = false,
    ): ChatCompletionResponse {
        $messagesArr = [];
        foreach ($messages as $message) {
            if ($message instanceof MessageInterface) {
                $messagesArr[] = $message->toArray();
            }
        }
        $json = [
            'stream' => $stream,
            'messages' => $messagesArr,
            'parameters' => [
                'temperature' => $temperature,
            ],
        ];
        if ($maxTokens) {
            $json['parameters']['max_tokens'] = $maxTokens;
        }
        if (! empty($tools)) {
            $toolsArray = [];
            foreach ($tools as $tool) {
                if ($tool instanceof ToolInterface) {
                    $toolsArray[] = $tool->toToolDefinition()->toArray();
                } elseif ($tool instanceof ToolDefinition) {
                    $toolsArray[] = $tool->toArray();
                } else {
                    $toolsArray[] = $tool;
                }
            }
            if (! empty($toolsArray)) {
                $json['tools'] = $toolsArray;
            }
        }
        if ($stop) {
            $json['stop'] = $stop;
        }
        $this->debug && $this->logger?->debug(sprintf("Send Messages: %s\nTools: %s", json_encode($messagesArr, JSON_UNESCAPED_UNICODE), json_encode($tools, JSON_UNESCAPED_UNICODE)));
        $response = $this->request(method: 'POST', path: sprintf('/api/v2/endpoint/%s/chat', $this->config->getEndpoint()), body: Json::encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE),);
        $chatCompletionResponse = new ChatCompletionResponse($response);
        $this->debug && $this->logger?->debug('Receive: ' . $chatCompletionResponse);
        return $chatCompletionResponse;
    }

    protected function request(
        string $method,
        string $path,
        array $headers = [],
        array $query = [],
        string $action = '',
        string $body = ''
    ): ResponseInterface {
        $credential = [
            'accessKeyId' => $this->config->getAk(),
            'secretKeyId' => $this->config->getSk(),
            'service' => $this->config->getService(),
            'region' => $this->config->getRegion(),
        ];
        // 初始化签名结构体
        $query = array_merge($query, [
            'Action' => $action,
            'Version' => '',
        ]);
        ksort($query);
        $requestParam = [
            // body是http请求需要的原生body
            'body' => $body,
            'host' => $this->config->getHost(false),
            'path' => $path,
            'method' => $method,
            'contentType' => 'application/json',
            'date' => gmdate('Ymd\THis\Z'),
            'query' => $query,
        ];
        // 第三步：接下来开始计算签名。在计算签名前，先准备好用于接收签算结果的 signResult 变量，并设置一些参数。
        // 初始化签名结果的结构体
        $xDate = $requestParam['date'];
        $shortXDate = substr($xDate, 0, 8);
        $xContentSha256 = hash('sha256', $requestParam['body']);
        $signResult = [
            'Host' => $requestParam['host'],
            'X-Content-Sha256' => $xContentSha256,
            'X-Date' => $xDate,
            'Content-Type' => $requestParam['contentType'],
        ];
        // 第四步：计算 Signature 签名。
        $signedHeaderStr = join(';', ['content-type', 'host', 'x-content-sha256', 'x-date']);
        $canonicalRequestStr = join("\n", [
            $requestParam['method'],
            $requestParam['path'],
            http_build_query($requestParam['query']),
            join("\n", [
                'content-type:' . $requestParam['contentType'],
                'host:' . $requestParam['host'],
                'x-content-sha256:' . $xContentSha256,
                'x-date:' . $xDate
            ]),
            '',
            $signedHeaderStr,
            $xContentSha256,
        ]);
        $hashedCanonicalRequest = hash('sha256', $canonicalRequestStr);
        $credentialScope = join('/', [$shortXDate, $credential['region'], $credential['service'], 'request']);
        $stringToSign = join("\n", ['HMAC-SHA256', $xDate, $credentialScope, $hashedCanonicalRequest]);
        $kDate = hash_hmac('sha256', $shortXDate, $credential['secretKeyId'], true);
        $kRegion = hash_hmac('sha256', $credential['region'], $kDate, true);
        $kService = hash_hmac('sha256', $credential['service'], $kRegion, true);
        $kSigning = hash_hmac('sha256', 'request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        $signResult['Authorization'] = sprintf('HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s', $credential['accessKeyId'] . '/' . $credentialScope, $signedHeaderStr, $signature);
        $headers = array_merge($headers, $signResult);
        return $this->client->request($method, $path, [
            'headers' => $headers,
            'query' => $query,
            'body' => $body,
            'verify' => false,
            'http_errors' => false,
        ]);
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;
        return $this;
    }
}
