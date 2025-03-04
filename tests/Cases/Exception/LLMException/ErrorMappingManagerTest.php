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
use Hyperf\Odin\Exception\LLMException\ErrorCode;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Hyperf\Odin\Exception\LLMException\LLMNetworkException;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\ErrorMappingManager
 */
class ErrorMappingManagerTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试构造函数.
     */
    public function testConstructor()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        $customMappingRules = ['test' => 'rule'];
        $manager = new ErrorMappingManager($logger, $customMappingRules);

        $this->assertInstanceOf(ErrorMappingManager::class, $manager);
        // 验证自定义规则是否被设置
        $customRules = $this->getNonpublicProperty($manager, 'customMappingRules');
        $this->assertArrayHasKey('test', $customRules);
        $this->assertEquals('rule', $customRules['test']);
    }

    /**
     * 测试添加映射规则.
     */
    public function testAddMappingRules()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        $manager = new ErrorMappingManager($logger);

        $rules = ['test' => 'new_rule'];
        $manager->addMappingRules($rules);

        // 验证规则是否被添加
        $updatedRules = $this->getNonpublicProperty($manager, 'customMappingRules');
        $this->assertArrayHasKey('test', $updatedRules);
        $this->assertEquals('new_rule', $updatedRules['test']);
    }

    /**
     * 测试异常映射 - 通用异常.
     */
    public function testMapExceptionGeneric()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        $manager = new ErrorMappingManager($logger);

        $exception = new RuntimeException('测试异常');
        $result = $manager->mapException($exception);

        $this->assertInstanceOf(LLMException::class, $result);
        $this->assertEquals('LLM调用错误: 测试异常', $result->getMessage());
    }

    /**
     * 测试映射GuzzleHTTP客户端异常.
     */
    public function testMapClientException()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        $manager = new ErrorMappingManager($logger);

        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(400, [], '{"error": {"message": "Bad request"}}');
        $exception = new ClientException('Bad request', $request, $response);

        $result = $manager->mapException($exception);

        $this->assertInstanceOf(LLMApiException::class, $result);
        $this->assertEquals(ErrorCode::API_INVALID_REQUEST, $result->getErrorCode());
    }

    /**
     * 测试映射服务器异常.
     */
    public function testMapServerException()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        $manager = new ErrorMappingManager($logger);

        $request = new Request('GET', 'https://api.example.com');
        $response = new Response(500, [], '{"error": {"message": "Server error"}}');
        $exception = new ServerException('Server error', $request, $response);

        $result = $manager->mapException($exception);

        $this->assertInstanceOf(LLMApiException::class, $result);
        $this->assertEquals(ErrorCode::API_SERVER_ERROR, $result->getErrorCode());
    }

    /**
     * 测试映射连接异常.
     */
    public function testMapConnectException()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        $manager = new ErrorMappingManager($logger);

        $request = new Request('GET', 'https://api.example.com');
        $exception = new ConnectException('连接错误', $request);

        $result = $manager->mapException($exception);

        $this->assertInstanceOf(LLMNetworkException::class, $result);
    }

    /**
     * 测试映射请求异常.
     */
    public function testMapRequestException()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        $manager = new ErrorMappingManager($logger);

        $request = new Request('GET', 'https://api.example.com');
        $exception = new RequestException('请求错误', $request);

        $result = $manager->mapException($exception);

        $this->assertInstanceOf(LLMException::class, $result);
    }

    /**
     * 测试自定义映射规则.
     */
    public function testCustomMappingRules()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug')->zeroOrMoreTimes()->withAnyArgs();

        // 定义自定义规则
        $customRules = [
            'customRule' => [
                'pattern' => '/test pattern/',
                'code' => ErrorCode::API_RATE_LIMIT,
                'factory' => function ($e) {
                    return new LLMApiException('自定义错误', ErrorCode::API_RATE_LIMIT);
                },
            ],
        ];

        $manager = new ErrorMappingManager($logger, $customRules);

        // 创建一个会匹配自定义规则的异常
        $exception = new RuntimeException('test pattern matched');
        $result = $manager->mapException($exception);

        $this->assertInstanceOf(LLMException::class, $result);
    }
}
