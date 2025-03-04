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

use Hyperf\Odin\Exception\LLMException;
use Hyperf\Odin\Exception\LLMException\ErrorHandlerInterface;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Exception\LLMException\ErrorHandlerInterface
 */
class ErrorHandlerInterfaceTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试接口方法实现.
     */
    public function testInterfaceImplementation()
    {
        /** @var ErrorHandlerInterface&MockInterface $handler */
        $handler = Mockery::mock(ErrorHandlerInterface::class);

        $exception = new RuntimeException('测试异常');
        $context = ['test' => 'context'];
        $llmException = new LLMException('LLM异常');

        $handler->shouldReceive('handle')
            ->once()
            ->with($exception, $context)
            ->andReturn($llmException);

        $result = $handler->handle($exception, $context);

        $this->assertInstanceOf(LLMException::class, $result);
        $this->assertEquals('LLM异常', $result->getMessage());
    }

    /**
     * 测试生成错误报告方法.
     */
    public function testGenerateErrorReport()
    {
        /** @var ErrorHandlerInterface&MockInterface $handler */
        $handler = Mockery::mock(ErrorHandlerInterface::class);

        $exception = new LLMException('测试异常');
        $context = ['test' => 'context'];
        $report = ['error' => ['message' => '测试异常']];

        $handler->shouldReceive('generateErrorReport')
            ->once()
            ->with($exception, $context)
            ->andReturn($report);

        $result = $handler->generateErrorReport($exception, $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('测试异常', $result['error']['message']);
    }
}
