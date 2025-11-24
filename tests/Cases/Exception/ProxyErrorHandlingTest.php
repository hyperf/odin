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

namespace HyperfTest\Odin\Cases\Exception;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Exception\LLMException\Api\LLMRateLimitException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\LLMErrorHandler;
use Hyperf\Odin\Exception\LLMException\Model\LLMContentFilterException;
use Hyperf\Odin\Exception\LLMException\Model\LLMContextLengthException;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * Test error handling in proxy scenarios.
 *
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\ErrorMappingManager
 * @covers \Hyperf\Odin\Exception\LLMException\LLMErrorHandler
 */
class ProxyErrorHandlingTest extends AbstractTestCase
{
    /**
     * Test handling proxy error with nested error structure (OpenAI format).
     */
    public function testProxyErrorWithNestedStructure()
    {
        $errorResponse = json_encode([
            'error' => [
                'message' => 'Context length exceeds model limit',
                'code' => 4002,
                'request_id' => '838816451070042112',
            ],
        ]);

        $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
        $response = new Response(400, [], $errorResponse);
        $exception = new RequestException('Client error', $request, $response);

        $errorHandler = new LLMErrorHandler();
        $mappedException = $errorHandler->handle($exception);

        $this->assertInstanceOf(LLMContextLengthException::class, $mappedException);
        $this->assertStringContainsString('Context length exceeds model limit', $mappedException->getMessage());
        $this->assertEquals(4002, $mappedException->getErrorCode());
    }

    /**
     * Test handling proxy error with flat structure.
     */
    public function testProxyErrorWithFlatStructure()
    {
        $errorResponse = json_encode([
            'code' => 4002,
            'message' => 'Context length exceeds model limit',
        ]);

        $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
        $response = new Response(400, [], $errorResponse);
        $exception = new RequestException('Client error', $request, $response);

        $errorHandler = new LLMErrorHandler();
        $mappedException = $errorHandler->handle($exception);

        $this->assertInstanceOf(LLMContextLengthException::class, $mappedException);
        $this->assertStringContainsString('Context length exceeds model limit', $mappedException->getMessage());
    }

    /**
     * Test handling proxy rate limit error.
     */
    public function testProxyRateLimitError()
    {
        $errorResponse = json_encode([
            'error' => [
                'message' => 'API rate limit exceeded',
                'code' => 3001,
                'request_id' => '838816451070042113',
            ],
        ]);

        $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
        $response = new Response(429, ['Retry-After' => '60'], $errorResponse);
        $exception = new RequestException('Too many requests', $request, $response);

        $errorHandler = new LLMErrorHandler();
        $mappedException = $errorHandler->handle($exception);

        $this->assertInstanceOf(LLMRateLimitException::class, $mappedException);
        $this->assertStringContainsString('API rate limit exceeded', $mappedException->getMessage());

        /** @var LLMRateLimitException $mappedException */
        $this->assertEquals(60, $mappedException->getRetryAfter());
    }

    /**
     * Test handling proxy content filter error.
     */
    public function testProxyContentFilterError()
    {
        $errorResponse = json_encode([
            'error' => [
                'message' => 'Content filtered by safety system',
                'code' => 4001,
                'request_id' => '838816451070042114',
            ],
        ]);

        $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
        $response = new Response(400, [], $errorResponse);
        $exception = new RequestException('Bad request', $request, $response);

        $errorHandler = new LLMErrorHandler();
        $mappedException = $errorHandler->handle($exception);

        $this->assertInstanceOf(LLMContentFilterException::class, $mappedException);
        $this->assertStringContainsString('Content filtered by safety system', $mappedException->getMessage());
    }

    /**
     * Test handling proxy authentication error.
     */
    public function testProxyAuthenticationError()
    {
        $errorResponse = json_encode([
            'error' => [
                'message' => 'Invalid or missing API key',
                'code' => 1001,
                'request_id' => '838816451070042115',
            ],
        ]);

        $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
        $response = new Response(401, [], $errorResponse);
        $exception = new RequestException('Unauthorized', $request, $response);

        $errorHandler = new LLMErrorHandler();
        $mappedException = $errorHandler->handle($exception);

        $this->assertInstanceOf(LLMInvalidApiKeyException::class, $mappedException);
        $this->assertStringContainsString('Invalid or missing API key', $mappedException->getMessage());
    }

    /**
     * Test error pattern matching extracts message from response body.
     */
    public function testErrorPatternMatchingWithResponseBody()
    {
        $errorResponse = json_encode([
            'error' => [
                'message' => 'Context length exceeds model limit',
                'code' => 4002,
            ],
        ]);

        $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
        $response = new Response(400, [], $errorResponse);
        $exception = new RequestException('Some generic error', $request, $response);

        $manager = new ErrorMappingManager();
        $mappedException = $manager->mapException($exception);

        // Should match based on the message in the response body, not just the exception message
        $this->assertInstanceOf(LLMContextLengthException::class, $mappedException);
    }

    /**
     * Test handling multiple nested proxy layers.
     */
    public function testMultipleProxyLayers()
    {
        // Simulate an error from a downstream service that's already been formatted by an Odin proxy
        $errorResponse = json_encode([
            'error' => [
                'message' => 'Context length exceeds model limit, current length: 8000, max limit: 4096',
                'code' => 4002,
                'type' => 'context_length_exceeded',
                'request_id' => '838816451070042116',
            ],
        ]);

        $request = new Request('POST', 'https://proxy.example.com/v1/chat/completions');
        $response = new Response(400, [], $errorResponse);
        $exception = new RequestException('Downstream error', $request, $response);

        $errorHandler = new LLMErrorHandler();
        $mappedException = $errorHandler->handle($exception);

        $this->assertInstanceOf(LLMContextLengthException::class, $mappedException);
        $this->assertStringContainsString('Context length exceeds model limit', $mappedException->getMessage());

        // Verify length extraction still works
        /** @var LLMContextLengthException $mappedException */
        $this->assertEquals(8000, $mappedException->getCurrentLength());
        $this->assertEquals(4096, $mappedException->getMaxLength());
    }

    /**
     * Test that both Chinese and English error messages are properly recognized (for backward compatibility).
     */
    public function testChineseAndEnglishErrorMessageRecognition()
    {
        $testCases = [
            [
                'message' => 'Context length exceeds model limit',
                'expectedClass' => LLMContextLengthException::class,
                'statusCode' => 400,
            ],
            [
                'message' => '上下文长度超出模型限制',
                'expectedClass' => LLMContextLengthException::class,
                'statusCode' => 400,
            ],
            [
                'message' => 'API rate limit exceeded',
                'expectedClass' => LLMRateLimitException::class,
                'statusCode' => 429,
            ],
            [
                'message' => 'API请求频率超出限制',
                'expectedClass' => LLMRateLimitException::class,
                'statusCode' => 429,
            ],
            [
                'message' => 'Content filtered by safety system',
                'expectedClass' => LLMContentFilterException::class,
                'statusCode' => 400,
            ],
            [
                'message' => '内容被系统安全过滤',
                'expectedClass' => LLMContentFilterException::class,
                'statusCode' => 400,
            ],
        ];

        foreach ($testCases as $testCase) {
            $errorResponse = json_encode([
                'error' => [
                    'message' => $testCase['message'],
                    'code' => 4000,
                ],
            ]);

            $request = new Request('POST', 'https://api.example.com/v1/chat/completions');
            $response = new Response($testCase['statusCode'], [], $errorResponse);
            $exception = new RequestException('Error', $request, $response);

            $errorHandler = new LLMErrorHandler();
            $mappedException = $errorHandler->handle($exception);

            $this->assertInstanceOf(
                $testCase['expectedClass'],
                $mappedException,
                "Failed to recognize message: {$testCase['message']}"
            );
        }
    }
}
