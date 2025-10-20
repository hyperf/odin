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

namespace HyperfTest\Odin\Cases\Exception\LLMException;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Hyperf\Odin\Exception\LLMException\Model\LLMContentFilterException;
use PHPUnit\Framework\TestCase;

/**
 * Azure OpenAI 模型错误测试.
 * @internal
 * @coversNothing
 */
class AzureModelErrorTest extends TestCase
{
    /**
     * 测试 Azure OpenAI model_error 被正确映射为内容过滤异常.
     */
    public function testAzureOpenAIModelErrorMapping(): void
    {
        $errorBody = json_encode([
            'error' => [
                'message' => 'The model produced invalid content. Consider modifying your prompt if you are seeing this error persistently. For more information, please see https://aka.ms/model-error',
                'type' => 'model_error',
                'param' => null,
                'code' => null,
            ],
        ]);

        $request = new Request(
            'POST',
            'https://test-azure-openai.example.com/openai/deployments/test-gpt/chat/completions'
        );

        $response = new Response(500, ['Content-Type' => 'application/json'], $errorBody);

        $requestException = new RequestException(
            'Server error: The model produced invalid content',
            $request,
            $response
        );

        $errorMappingManager = new ErrorMappingManager();
        $mappedException = $errorMappingManager->mapException($requestException);

        // 断言异常类型
        $this->assertInstanceOf(LLMContentFilterException::class, $mappedException);

        // 断言状态码被正确透传
        $this->assertEquals(500, $mappedException->getStatusCode());

        // 断言异常消息包含有用信息
        $this->assertStringContainsString('Model produced invalid content', $mappedException->getMessage());
    }

    /**
     * 测试 Azure OpenAI server_error 被正确处理为可重试的网络错误.
     */
    public function testAzureServerErrorHandling(): void
    {
        $errorBody = json_encode([
            'error' => [
                'message' => 'The server had an error while processing your request. Sorry about that!',
                'type' => 'server_error',
                'param' => null,
                'code' => null,
            ],
        ]);

        $request = new Request('POST', 'https://test-azure-openai.example.com/openai/deployments/test-gpt/chat/completions');
        $response = new Response(500, ['Content-Type' => 'application/json'], $errorBody);

        $requestException = new RequestException(
            'Server error: The server had an error while processing your request',
            $request,
            $response
        );

        $errorMappingManager = new ErrorMappingManager();
        $mappedException = $errorMappingManager->mapException($requestException);

        // 这应该是 LLMNetworkException (可重试的网络错误)，不是 LLMContentFilterException
        $this->assertNotInstanceOf(LLMContentFilterException::class, $mappedException);
        $this->assertInstanceOf(LLMNetworkException::class, $mappedException);

        // 状态码应该是500 (服务端错误)
        $this->assertEquals(500, $mappedException->getStatusCode());

        // 错误消息应该表明这是可重试的服务错误
        $this->assertStringContainsString('Azure OpenAI service temporarily unavailable', $mappedException->getMessage());
        $this->assertStringContainsString('please retry later', $mappedException->getMessage());
    }

    /**
     * 测试 Azure OpenAI server_error 可以参与重试机制.
     */
    public function testAzureServerErrorIsRetryable(): void
    {
        $errorBody = json_encode([
            'error' => [
                'message' => 'The server had an error while processing your request. Sorry about that!',
                'type' => 'server_error',
                'param' => null,
                'code' => null,
            ],
        ]);

        $request = new Request('POST', 'https://test-azure-openai.example.com/openai/deployments/test-gpt/chat/completions');
        $response = new Response(500, ['Content-Type' => 'application/json'], $errorBody);

        $requestException = new RequestException(
            'Server error: The server had an error while processing your request',
            $request,
            $response
        );

        $errorMappingManager = new ErrorMappingManager();
        $mappedException = $errorMappingManager->mapException($requestException);

        // 验证这是网络异常，可以参与重试
        $this->assertInstanceOf(LLMNetworkException::class, $mappedException);

        // 验证在重试逻辑中会被识别为可重试异常
        // 模拟 AbstractModel::callWithNetworkRetry 的检查逻辑
        $isRetryable = $mappedException instanceof LLMNetworkException
            || ($mappedException && $mappedException->getPrevious() instanceof LLMNetworkException);

        $this->assertTrue($isRetryable, 'Azure server_error 应该可以参与重试机制');
    }

    /**
     * 测试非 Azure model_error 不会被误匹配.
     */
    public function testNormalInvalidRequestNotMismatched(): void
    {
        $errorBody = json_encode([
            'error' => [
                'message' => 'Invalid request parameter: temperature must be between 0 and 2',
                'type' => 'invalid_request_error',
                'param' => 'temperature',
                'code' => null,
            ],
        ]);

        $request = new Request('POST', 'https://test-openai-api.example.com/v1/chat/completions');
        $response = new Response(400, ['Content-Type' => 'application/json'], $errorBody);

        $requestException = new RequestException(
            'Client error: Invalid request parameter',
            $request,
            $response
        );

        $errorMappingManager = new ErrorMappingManager();
        $mappedException = $errorMappingManager->mapException($requestException);

        // 这应该不是内容过滤异常，而是API异常
        $this->assertNotInstanceOf(LLMContentFilterException::class, $mappedException);

        // 状态码应该是400
        $this->assertEquals(400, $mappedException->getStatusCode());
    }
}
