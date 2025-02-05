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

namespace HyperfTest\Odin\Cases\Api\OpenAI;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Hyperf\Odin\Api\OpenAI\Client;
use Hyperf\Odin\Api\OpenAI\OpenAIConfig;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use HyperfTest\Odin\Cases\AbstractTestCase;

use function Hyperf\Support\env;

/**
 * @internal
 * @coversNothing
 */
class ClientTest extends AbstractTestCase
{
    private string $model;

    private OpenAIConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = env('OPENAI_MODEL');
        $this->config = new OpenAIConfig(
            apiKey: env('OPENAI_API_KEY'),
            baseUrl: env('OPENAI_HOST'),
        );
    }

    public function testChat()
    {
        $client = new Client($this->config, new Logger());

        $guzzleClientMock = $this->createMock(GuzzleClient::class);
        $response = new Response(
            200,
            [],
            <<<'JSON'
{
    "choices": [
        {
            "content_filter_results": {
                "hate": {
                    "filtered": false,
                    "severity": "safe"
                },
                "self_harm": {
                    "filtered": false,
                    "severity": "safe"
                },
                "sexual": {
                    "filtered": false,
                    "severity": "safe"
                },
                "violence": {
                    "filtered": false,
                    "severity": "safe"
                }
            },
            "finish_reason": "stop",
            "index": 0,
            "logprobs": null,
            "message": {
                "content": "Hello! How can I assist you today?",
                "refusal": null,
                "role": "assistant"
            }
        }
    ],
    "created": 1736846202,
    "id": "chatcmpl-ApXKMLCwroGSJnFICgA6nhYZ7OQfO",
    "model": "gpt-4o-2024-08-06",
    "object": "chat.completion",
    "prompt_filter_results": [
        {
            "prompt_index": 0,
            "content_filter_results": {}
        }
    ],
    "system_fingerprint": "fp_f3927aa00d",
    "usage": {
        "completion_tokens": 92,
        "completion_tokens_details": {
            "accepted_prediction_tokens": 0,
            "audio_tokens": 0,
            "reasoning_tokens": 0,
            "rejected_prediction_tokens": 0
        },
        "prompt_tokens": 61,
        "prompt_tokens_details": {
            "audio_tokens": 0,
            "cached_tokens": 0
        },
        "total_tokens": 153
    }
}
JSON
        );
        $guzzleClientMock->method('post')->willReturn($response);
        $this->setNonpublicPropertyValue($client, 'client', $guzzleClientMock);

        $messages = [
            new SystemMessage(''),
            new UserMessage('hello'),
        ];
        $result = $client->chat(messages: $messages, model: $this->model);

        $this->assertInstanceOf(ChatCompletionResponse::class, $result);
        var_dump((string) $result);
        $this->assertNotEmpty($result->getChoices()[0]->getMessage()->getContent());

        $this->assertNotEmpty($result->getId());
        $this->assertNotEmpty($result->getModel());
        $this->assertNotEmpty($result->getObject());
    }

    public function testChatStream()
    {
        $client = new Client($this->config, new Logger());

        $guzzleClientMock = $this->createMock(GuzzleClient::class);
        $list = [
            <<<'JSON'
{"choices":[],"created":0,"id":"","model":"","object":"","prompt_filter_results":[{"prompt_index":0,"content_filter_results":{"hate":{"filtered":false,"severity":"safe"},"jailbreak":{"filtered":false,"detected":false},"self_harm":{"filtered":false,"severity":"safe"},"sexual":{"filtered":false,"severity":"safe"},"violence":{"filtered":false,"severity":"safe"}}}]}
JSON,
            <<<'JSON'
{"choices":[{"content_filter_results":{},"delta":{"content":"","role":"assistant"},"finish_reason":null,"index":0,"logprobs":null}],"created":1736906666,"id":"chatcmpl-Apn3aJ0BPybkQFIi3YO2XHweNLS8W","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_04751d0b65"}
JSON,
            <<<'JSON'
{"choices":[{"content_filter_results":{"hate":{"filtered":false,"severity":"safe"},"protected_material_code":{"filtered":false,"detected":false},"protected_material_text":{"filtered":false,"detected":false},"self_harm":{"filtered":false,"severity":"safe"},"sexual":{"filtered":false,"severity":"safe"},"violence":{"filtered":false,"severity":"safe"}},"delta":{"content":"Hello! "},"finish_reason":null,"index":0,"logprobs":null}],"created":1736906666,"id":"chatcmpl-Apn3aJ0BPybkQFIi3YO2XHweNLS8W","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_04751d0b65"}
JSON,
            <<<'JSON'
{"choices":[{"content_filter_results":{"hate":{"filtered":false,"severity":"safe"},"protected_material_code":{"filtered":false,"detected":false},"protected_material_text":{"filtered":false,"detected":false},"self_harm":{"filtered":false,"severity":"safe"},"sexual":{"filtered":false,"severity":"safe"},"violence":{"filtered":false,"severity":"safe"}},"delta":{"content":"How can I assist you today?"},"finish_reason":null,"index":0,"logprobs":null}],"created":1736906666,"id":"chatcmpl-Apn3aJ0BPybkQFIi3YO2XHweNLS8W","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_04751d0b65"}
JSON,

            <<<'JSON'
{"choices":[{"content_filter_results":{},"delta":{},"finish_reason":"stop","index":0,"logprobs":null}],"created":1736906666,"id":"chatcmpl-Apn3aJ0BPybkQFIi3YO2XHweNLS8W","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_04751d0b65"}
JSON,
        ];
        $chunkedBody = [];
        foreach ($list as $item) {
            $chunkedBody[] = 'data:' . $item;
        }
        $chunkedBody[] = 'data:[DONE]';
        $stream = Utils::streamFor(implode("\r\n", $chunkedBody));
        $response = new Response(200, ['Transfer-Encoding' => 'chunked'], $stream);
        $guzzleClientMock->method('post')->willReturn($response);
        $this->setNonpublicPropertyValue($client, 'client', $guzzleClientMock);

        $messages = [
            new SystemMessage(''),
            new UserMessage('hello'),
        ];
        $result = $client->chat($messages, $this->model, stream: true);
        $this->assertInstanceOf(ChatCompletionResponse::class, $result);

        $content = '';
        foreach ($result->getStreamIterator() as $choice) {
            $content .= $choice->getMessage()?->getContent() ?: '';
        }
        $content = trim($content);
        var_dump($content);
        $this->assertNotEmpty($content);
        $this->assertNotEmpty($result->getId());
        $this->assertNotEmpty($result->getModel());
        $this->assertNotEmpty($result->getObject());
    }
}
