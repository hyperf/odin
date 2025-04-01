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

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\Api\LLMRateLimitException;
use Hyperf\Odin\Exception\LLMException\ErrorCode;
use Hyperf\Odin\Exception\LLMException\ErrorMapping;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use Hyperf\Odin\Exception\LLMException\Model\LLMContentFilterException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\ErrorMapping
 */
class ErrorMappingTest extends AbstractTestCase
{
    /**
     * @var ErrorMappingManager
     */
    protected $mapper;

    /**
     * 设置测试环境.
     */
    protected function setUp(): void
    {
        parent::setUp();
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();
        $this->mapper = new ErrorMappingManager($logger);
    }

    /**
     * 清理测试环境.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试基本异常映射.
     */
    public function testMapException()
    {
        $exception = new RuntimeException('测试异常');
        $result = $this->mapper->mapException($exception);

        $this->assertInstanceOf(LLMException::class, $result);
        $this->assertEquals('LLM调用错误: 测试异常', $result->getMessage());
    }

    /**
     * 测试GuzzleHttp客户端异常映射.
     */
    public function testMapClientException()
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(400, [], '{"error": {"message": "请求无效"}}');
        $exception = new ClientException('客户端错误', $request, $response);

        $result = $this->mapper->mapException($exception);

        $this->assertInstanceOf(LLMApiException::class, $result);
        $this->assertEquals(ErrorCode::API_INVALID_REQUEST, $result->getErrorCode());
    }

    /**
     * 测试GuzzleHttp服务器异常映射.
     */
    public function testMapServerException()
    {
        $request = new Request('GET', 'https://example.com');
        $response = new Response(500, [], '{"error": {"message": "服务器错误"}}');
        $exception = new ServerException('服务器错误', $request, $response);

        $result = $this->mapper->mapException($exception);

        $this->assertInstanceOf(LLMApiException::class, $result);
        $this->assertEquals(ErrorCode::API_SERVER_ERROR, $result->getErrorCode());
    }

    /**
     * 测试GuzzleHttp连接异常映射.
     */
    public function testMapConnectException()
    {
        $request = new Request('GET', 'https://example.com');
        $exception = new ConnectException('连接错误', $request);

        $result = $this->mapper->mapException($exception);

        $this->assertInstanceOf(LLMNetworkException::class, $result);
        $this->assertEquals(ErrorCode::NETWORK_CONNECTION_ERROR, $result->getErrorCode());
    }

    /**
     * 测试GuzzleHttp请求异常映射.
     */
    public function testMapRequestException()
    {
        $request = new Request('GET', 'https://example.com');
        $exception = new RequestException('请求错误', $request);

        $result = $this->mapper->mapException($exception);

        $this->assertInstanceOf(LLMNetworkException::class, $result);
        $this->assertEquals(ErrorCode::NETWORK_CONNECTION_ERROR, $result->getErrorCode());
    }

    /**
     * 测试OpenAI特定错误映射 - 速率限制.
     */
    public function testMapOpenAIRateLimitError()
    {
        $request = new Request('GET', 'https://api.openai.com/v1/chat/completions');
        $response = new Response(429, [], '{"error": {"message": "Rate limit exceeded", "type": "rate_limit_error"}}');
        $exception = new ClientException('Rate limit exceeded', $request, $response);

        $result = $this->mapper->mapException($exception);

        $this->assertInstanceOf(LLMRateLimitException::class, $result);
        $this->assertEquals(ErrorCode::API_RATE_LIMIT, $result->getErrorCode());
    }

    /**
     * 测试OpenAI特定错误映射 - 内容过滤.
     */
    public function testMapOpenAIContentFilterError()
    {
        $request = new Request('GET', 'https://api.openai.com/v1/chat/completions');
        $response = new Response(400, [], '{"error": {"message": "Your prompt was filtered", "type": "content_filter"}}');
        $exception = new ClientException('Content filter', $request, $response);

        $result = $this->mapper->mapException($exception);

        $this->assertInstanceOf(LLMContentFilterException::class, $result);
        $this->assertEquals(ErrorCode::MODEL_CONTENT_FILTER, $result->getErrorCode());
    }

    /**
     * 测试默认映射规则.
     */
    public function testGetDefaultMapping()
    {
        $defaultRules = ErrorMapping::getDefaultMapping();
        $this->assertIsArray($defaultRules);
        $this->assertNotEmpty($defaultRules);

        // 检查是否包含常见异常类型
        $this->assertArrayHasKey(ConnectException::class, $defaultRules);
        $this->assertArrayHasKey(RequestException::class, $defaultRules);
    }
}
