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

namespace HyperfTest\Odin\Cases\Api\Skylark;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Hyperf\Odin\Api\OpenAI\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\OpenAI\Response\ToolCall;
use Hyperf\Odin\Api\Skylark\Client;
use Hyperf\Odin\Api\Skylark\SkylarkConfig;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use Hyperf\Odin\Tool\AbstractTool;
use HyperfTest\Odin\Cases\AbstractTestCase;

use function Hyperf\Support\env;

/**
 * @internal
 * @coversNothing
 */
class ClientTest extends AbstractTestCase
{
    private string $model;

    private SkylarkConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = env('SKYLARK_PRO_32K_ENDPOINT');
        $this->config = new SkylarkConfig(
            apiKey: env('SKYLARK_API_KEY'),
            baseUrl: env('SKYLARK_HOST'),
            model: $this->model
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

    public function testChatWithTool()
    {
        $client = new Client($this->config, new Logger());

        $guzzleClientMock = $this->createMock(GuzzleClient::class);
        $response = new Response(
            200,
            [],
            <<<'JSON'
{"choices":[{"finish_reason":"tool_calls","index":0,"logprobs":null,"message":{"content":"\n当前提供了 1 个工具，分别是[\"get_rand_string\"]，需要生成 slat 为 hello 的随机字符串，调用 get_rand_string。","role":"assistant","tool_calls":[{"function":{"arguments":"{\"slat\": \"hello\"}","name":"get_rand_string"},"id":"call_xgax2gfar3s7tuvsw7kib0wk","type":"function"}]}}],"created":1736934023,"id":"021736934021876a67222095253345647102ee3d39e5e9b67365c","model":"doubao-pro-32k-240515","object":"chat.completion","usage":{"completion_tokens":73,"prompt_tokens":95,"total_tokens":168,"prompt_tokens_details":{"cached_tokens":0}}}
JSON
        );
        $guzzleClientMock->method('post')->willReturn($response);
        $this->setNonpublicPropertyValue($client, 'client', $guzzleClientMock);

        $messages = [
            new SystemMessage('你可以为用户生成随机字符串，调用 get_rand_string 工具来完成'),
            new UserMessage('帮我生成 1 个随机字符串，其中 slat 为 hello'),
        ];
        $tool = [
            new class extends AbstractTool {
                public string $name = 'get_rand_string';

                public string $description = '生成随机字符串';

                public array $parameters = [
                    'slat' => [
                        'type' => 'string',
                        'description' => '盐值',
                    ],
                ];

                public function invoke($args): ?array
                {
                    var_dump($args);
                    return [
                        uniqid(),
                    ];
                }
            },
        ];
        $result = $client->chat(messages: $messages, model: $this->model, tools: $tool);

        $this->assertInstanceOf(ChatCompletionResponse::class, $result);
        $this->assertTrue($result->getFirstChoice()->isFinishedByToolCall());
        $this->assertInstanceOf(AssistantMessage::class, $result->getFirstChoice()->getMessage());
        /** @var AssistantMessage $message */
        $message = $result->getFirstChoice()->getMessage();
        $this->assertSame('get_rand_string', $message->getToolCalls()[0]->getName());
        $this->assertSame(['slat' => 'hello'], $message->getToolCalls()[0]->getArguments());
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

    public function testChatStreamWithTool()
    {
        $client = new Client($this->config, new Logger());

        $guzzleClientMock = $this->createMock(GuzzleClient::class);
        $list = [
            <<<'JSON'
{"choices":[{"content_filter_results":{},"delta":{"tool_calls":[{"function":{"arguments":"","name":"get_rand_string"},"id":"call_sJFuv3SdQtxoaCccGBPiPY53","index":0,"type":"function"}]},"finish_reason":null,"index":0,"logprobs":null}],"created":1736912972,"id":"chatcmpl-ApohIw9hJ9OVHrr7P75dYmwSvFiS6","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_f3927aa00d"}
JSON,
            <<<'JSON'
{"choices":[{"content_filter_results":{},"delta":{"tool_calls":[{"function":{"arguments":"{\"slat\": \"hello\"}"},"index":0}]},"finish_reason":null,"index":0,"logprobs":null}],"created":1736912972,"id":"chatcmpl-ApohIw9hJ9OVHrr7P75dYmwSvFiS6","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_f3927aa00d"}
JSON,
            <<<'JSON'
{"choices":[{"content_filter_results":{},"delta":{"tool_calls":[{"function":{"arguments":"","name":"get_rand_string"},"id":"call_RleVQfBwjkh4jez8JowsMSE1","index":1,"type":"function"}]},"finish_reason":null,"index":0,"logprobs":null}],"created":1736912972,"id":"chatcmpl-ApohIw9hJ9OVHrr7P75dYmwSvFiS6","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_f3927aa00d"}
JSON,
            <<<'JSON'
{"choices":[{"content_filter_results":{},"delta":{"tool_calls":[{"function":{"arguments":"{\"slat\": \"hi\"}"},"index":1}]},"finish_reason":null,"index":0,"logprobs":null}],"created":1736912972,"id":"chatcmpl-ApohIw9hJ9OVHrr7P75dYmwSvFiS6","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_f3927aa00d"}
JSON,
            <<<'JSON'
{"choices":[{"content_filter_results":{},"delta":{},"finish_reason":"tool_calls","index":0,"logprobs":null}],"created":1736912972,"id":"chatcmpl-ApohIw9hJ9OVHrr7P75dYmwSvFiS6","model":"gpt-4o-2024-08-06","object":"chat.completion.chunk","system_fingerprint":"fp_f3927aa00d"}
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
            new SystemMessage('你可以为用户生成随机字符串，调用 get_rand_string 工具来完成'),
            new UserMessage('帮我生成 1 个随机字符串， slat 为 hello'),
        ];
        $tool = [
            new class extends AbstractTool {
                public string $name = 'get_rand_string';

                public string $description = '生成随机字符串';

                public array $parameters = [
                    'slat' => [
                        'type' => 'string',
                        'description' => '盐值',
                    ],
                ];

                public function invoke($args): ?array
                {
                    var_dump($args);
                    return [
                        uniqid(),
                    ];
                }
            },
        ];
        $result = $client->chat(messages: $messages, model: $this->model, tools: $tool, stream: true);

        $this->assertInstanceOf(ChatCompletionResponse::class, $result);

        $toolCalls = [];
        $content = '';
        // 在 stream 模式下，需要自行维护 ToolCall 的组装
        foreach ($result->getStreamIterator() as $choice) {
            /** @var AssistantMessage $message */
            $message = $choice->getMessage();
            foreach ($message->getToolCalls() as $toolCall) {
                if ($toolCall->getId()) {
                    $toolCalls[] = new ToolCall($toolCall->getName(), [], $toolCall->getId(), $toolCall->getType(), $toolCall->getStreamArguments());
                } else {
                    /** @var ToolCall $lastToolCall */
                    $lastToolCall = end($toolCalls);
                    $lastToolCall->appendStreamArguments($toolCall->getStreamArguments());
                }
            }
            $content .= $choice->getMessage()?->getContent() ?: '';
        }
        $content = trim($content);
        $this->assertIsString($content);
        $this->assertSame('get_rand_string', $toolCalls[0]->getName());
        $this->assertSame(['slat' => 'hello'], $toolCalls[0]->getArguments());
    }

    public function testChatVision()
    {
        $this->markTestSkipped('豆包需要视觉模型');
        $client = new Client($this->config, new Logger());

        $guzzleClientMock = $this->createMock(GuzzleClient::class);
        $response = new Response(
            200,
            [],
            <<<'JSON'
{"choices":[{"content_filter_results":{"hate":{"filtered":false,"severity":"safe"},"self_harm":{"filtered":false,"severity":"safe"},"sexual":{"filtered":false,"severity":"safe"},"violence":{"filtered":false,"severity":"safe"}},"finish_reason":"stop","index":0,"logprobs":null,"message":{"content":"图片中展示了一家人围坐在餐桌前，共同享用丰盛的中国菜肴。桌上摆满了各式各样的美食，包括整条烤鱼、炒虾、蔬菜拼盘、炒饭、汤等。每个人面前都有一副筷子，他们正享用着这顿美味的晚餐。桌上还有饮料，如橙汁，整个场景呈现出一种温馨祥和的家庭聚餐氛围。背景中可以看到传统的中式装修风格。","refusal":null,"role":"assistant"}}],"created":1736939844,"id":"chatcmpl-ApvgijBkXxrnuOdJmkcIkHJp10eSD","model":"gpt-4o-2024-08-06","object":"chat.completion","prompt_filter_results":[{"prompt_index":0,"content_filter_result":{"sexual":{"filtered":false,"severity":"safe"},"violence":{"filtered":false,"severity":"safe"},"hate":{"filtered":false,"severity":"safe"},"self_harm":{"filtered":false,"severity":"safe"},"jailbreak":{"filtered":false,"detected":false}}},{"prompt_index":2,"content_filter_result":{"sexual":{"filtered":false,"severity":"safe"},"violence":{"filtered":false,"severity":"safe"},"hate":{"filtered":false,"severity":"safe"},"self_harm":{"filtered":false,"severity":"safe"}}}],"system_fingerprint":"fp_f3927aa00d","usage":{"completion_tokens":114,"completion_tokens_details":{"accepted_prediction_tokens":0,"audio_tokens":0,"reasoning_tokens":0,"rejected_prediction_tokens":0},"prompt_tokens":1301,"prompt_tokens_details":{"audio_tokens":0,"cached_tokens":0},"total_tokens":1415}}
JSON
        );
        $guzzleClientMock->method('post')->willReturn($response);
        $this->setNonpublicPropertyValue($client, 'client', $guzzleClientMock);
        $userMessage = new UserMessage();
        $userMessage->addContent(UserMessageContent::text('这个图片里面有什么'));
        $userMessage->addContent(UserMessageContent::imageUrl(base64_decode('aHR0cHM6Ly92Y2cwMi5jZnAuY24vY3JlYXRpdmUvdmNnLzgwMC9uZXcvVkNHMjExMjU4OTAwOTQwLmpwZw==')));

        $messages = [
            new SystemMessage(''),
            $userMessage,
        ];
        $result = $client->chat(messages: $messages, model: $this->model);

        $this->assertInstanceOf(ChatCompletionResponse::class, $result);
        var_dump((string) $result);
        $this->assertNotEmpty($result->getChoices()[0]->getMessage()->getContent());

        $this->assertNotEmpty($result->getId());
        $this->assertNotEmpty($result->getModel());
        $this->assertNotEmpty($result->getObject());
    }
}
