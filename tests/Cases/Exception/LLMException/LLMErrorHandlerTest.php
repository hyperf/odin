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

use Exception;
use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\ErrorCode;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;
use Hyperf\Odin\Exception\LLMException\LLMErrorHandler;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\LLMErrorHandler
 */
class LLMErrorHandlerTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试LLMErrorHandler构造函数.
     */
    public function testConstructor()
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $customRules = ['test' => 'rule'];
        $handler = new LLMErrorHandler($logger, $customRules, true);

        $this->assertInstanceOf(LLMErrorHandler::class, $handler);
        $this->assertEquals($logger, $this->getNonpublicProperty($handler, 'logger'));
        $this->assertTrue($this->getNonpublicProperty($handler, 'logErrors'));
    }

    /**
     * 测试异常处理.
     */
    public function testHandle()
    {
        $exception = new RuntimeException('测试异常');
        $context = ['test' => 'value'];

        /** @var ErrorMappingManager&MockInterface $errorMappingManager */
        $errorMappingManager = Mockery::mock(ErrorMappingManager::class);
        $errorMappingManager->shouldReceive('mapException')
            ->once()
            ->with($exception, $context)
            ->andThrow(new Exception('映射异常失败')); // 模拟映射过程抛出异常

        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->zeroOrMoreTimes()
            ->withAnyArgs();

        $handler = new LLMErrorHandler($logger);
        $this->setNonpublicPropertyValue($handler, 'errorMappingManager', $errorMappingManager);

        $result = $handler->handle($exception, $context);

        $this->assertInstanceOf(LLMException::class, $result);
        $this->assertEquals('处理LLM错误时发生异常: 测试异常', $result->getMessage());
    }

    /**
     * 测试生成错误报告.
     */
    public function testGenerateErrorReport()
    {
        $exception = new LLMException('测试异常', 123, null, ErrorCode::API_RATE_LIMIT);
        $context = ['request' => ['data' => 'test']];

        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        $handler = new LLMErrorHandler($logger);
        $report = $handler->generateErrorReport($exception, $context);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('error', $report);
        $this->assertEquals('测试异常', $report['error']['message']);
        $this->assertEquals(123, $report['error']['code']);
        $this->assertEquals(ErrorCode::API_RATE_LIMIT, $report['error']['error_code']);
        $this->assertEquals(LLMException::class, $report['error']['type']);

        // 验证上下文是否被包含
        $this->assertArrayHasKey('context', $report);
        $this->assertEquals(['request' => ['data' => 'test']], $report['context']);
    }

    /**
     * 测试记录错误.
     */
    public function testLogError()
    {
        $exception = new LLMException('测试异常', 123, null, ErrorCode::API_RATE_LIMIT);
        $context = ['request' => ['data' => 'test']];

        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log')
            ->once()
            ->withArgs(function ($level, $message, $logContext) {
                return $level === 'warning'
                    && str_contains($message, 'LLM错误: 测试异常')
                    && isset($logContext['error_code'])
                    && $logContext['error_code'] === ErrorCode::API_RATE_LIMIT;
            });

        $handler = new LLMErrorHandler($logger);
        $handler->logError($exception, $context);

        // 添加断言
        $this->assertTrue(true, '日志记录执行成功');
    }

    /**
     * 测试添加映射规则.
     */
    public function testAddMappingRules()
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $errorMappingManager = Mockery::mock(ErrorMappingManager::class);
        $errorMappingManager->shouldReceive('addMappingRules')
            ->once()
            ->with(['test' => 'rule'])
            ->andReturn(null);

        $handler = new LLMErrorHandler($logger);
        $this->setNonpublicPropertyValue($handler, 'errorMappingManager', $errorMappingManager);

        $handler->addMappingRules(['test' => 'rule']);

        // 添加断言
        $this->assertTrue(true, '规则添加成功');
    }

    /**
     * 测试设置是否记录错误.
     */
    public function testSetLogErrors()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        $handler = new LLMErrorHandler($logger, [], true);
        $this->assertTrue($this->getNonpublicProperty($handler, 'logErrors'));

        $handler->setLogErrors(false);
        $this->assertFalse($this->getNonpublicProperty($handler, 'logErrors'));
    }

    /**
     * 测试设置是否生成详细的错误上下文.
     */
    public function testSetVerboseErrorContext()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        $handler = new LLMErrorHandler($logger);
        $this->assertTrue($this->getNonpublicProperty($handler, 'verboseErrorContext'));

        $handler->setVerboseErrorContext(false);
        $this->assertFalse($this->getNonpublicProperty($handler, 'verboseErrorContext'));
    }

    /**
     * 测试确定日志级别.
     */
    public function testDetermineLogLevel()
    {
        /** @var LoggerInterface&MockInterface $logger */
        $logger = Mockery::mock(LoggerInterface::class);

        $handler = new LLMErrorHandler($logger);

        // 测试配置错误（1000系列）
        $configException = new LLMException('配置错误', 0, null, 1500);
        $configLogLevel = $this->callNonpublicMethod($handler, 'determineLogLevel', $configException);
        $this->assertEquals('warning', $configLogLevel);

        // 测试网络错误（2000系列）
        $networkException = new LLMException('网络错误', 0, null, 2500);
        $networkLogLevel = $this->callNonpublicMethod($handler, 'determineLogLevel', $networkException);
        $this->assertEquals('error', $networkLogLevel);

        // 测试API错误-速率限制（3000系列）
        $apiRateLimitException = new LLMException('速率限制', 0, null, ErrorCode::API_RATE_LIMIT);
        $apiRateLimitLogLevel = $this->callNonpublicMethod($handler, 'determineLogLevel', $apiRateLimitException);
        $this->assertEquals('warning', $apiRateLimitLogLevel);

        // 测试模型错误-内容过滤（4000系列）
        $modelContentFilterException = new LLMException('内容过滤', 0, null, ErrorCode::MODEL_CONTENT_FILTER);
        $modelContentFilterLogLevel = $this->callNonpublicMethod($handler, 'determineLogLevel', $modelContentFilterException);
        $this->assertEquals('warning', $modelContentFilterLogLevel);
    }
}
