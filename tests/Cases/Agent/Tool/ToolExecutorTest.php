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

namespace HyperfTest\Odin\Cases\Agent\Tool;

use Error;
use Hyperf\Coroutine\Parallel;
use Hyperf\Odin\Agent\Tool\ToolExecutor;
use HyperfTest\Odin\Cases\AbstractTestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * 测试 ToolExecutor 类的功能.
 * @internal
 * @coversNothing
 */
class ToolExecutorTest extends AbstractTestCase
{
    /**
     * 测试 add 方法能正确添加工具回调.
     */
    public function testAdd()
    {
        $executor = new ToolExecutor();

        // 添加一个简单的回调函数
        $result = $executor->add(function () {
            return 'test1';
        });

        // 验证返回值是否是当前对象（支持链式调用）
        $this->assertSame($executor, $result);

        // 验证工具回调是否被正确添加
        $tools = $this->getNonpublicProperty($executor, 'tools');
        $this->assertCount(1, $tools);
        $this->assertIsCallable($tools[0]);

        // 测试添加多个回调函数
        $executor->add(function () {
            return 'test2';
        });

        // 验证工具回调是否被正确添加
        $tools = $this->getNonpublicProperty($executor, 'tools');
        $this->assertCount(2, $tools);
        $this->assertIsCallable($tools[0]);
        $this->assertIsCallable($tools[1]);

        // 测试链式调用添加
        $executor
            ->add(function () {
                return 'test3';
            })
            ->add(function () {
                return 'test4';
            });

        // 验证工具回调是否被正确添加
        $tools = $this->getNonpublicProperty($executor, 'tools');
        $this->assertCount(4, $tools);
    }

    /**
     * 测试 setParallel 方法能正确设置并行执行状态.
     */
    public function testSetParallel()
    {
        $executor = new ToolExecutor();

        // 默认情况下应该是并行的
        $parallel = $this->getNonpublicProperty($executor, 'parallel');
        $this->assertTrue($parallel, '默认应为并行模式');

        // 测试设置为串行
        $result = $executor->setParallel(false);

        // 验证返回值是否是当前对象（支持链式调用）
        $this->assertSame($executor, $result);

        // 验证 parallel 属性是否被正确设置
        $parallel = $this->getNonpublicProperty($executor, 'parallel');
        $this->assertFalse($parallel, '应被设置为串行模式');

        // 测试设置回并行
        $executor->setParallel(true);

        // 验证 parallel 属性是否被正确设置
        $parallel = $this->getNonpublicProperty($executor, 'parallel');
        $this->assertTrue($parallel, '应被设置回并行模式');

        // 测试链式调用
        $executor
            ->setParallel(false)
            ->add(function () {
                return 'test';
            });

        // 验证属性是否被正确设置
        $parallel = $this->getNonpublicProperty($executor, 'parallel');
        $this->assertFalse($parallel, '链式调用后应保持为串行模式');
    }

    /**
     * 测试 run 方法在串行模式下的执行.
     */
    public function testRunInSerialMode()
    {
        $executor = new ToolExecutor();

        // 设置为串行模式
        $executor->setParallel(false);

        // 空工具列表执行结果
        $results = $executor->run();
        $this->assertEmpty($results, '空工具列表应返回空数组');

        // 添加多个回调函数
        $executor
            ->add(function () {
                return 'result1';
            })
            ->add(function () {
                return ['key' => 'value2'];
            })
            ->add(function () {
                return (object) ['name' => 'object3'];
            });

        // 执行并检查结果
        $results = $executor->run();

        // 验证结果数量和顺序
        $this->assertCount(3, $results, '应该包含三个结果');
        $this->assertEquals('result1', $results[0], '第一个结果应该是字符串');
        $this->assertEquals(['key' => 'value2'], $results[1], '第二个结果应该是数组');
        $this->assertEquals((object) ['name' => 'object3'], $results[2], '第三个结果应该是对象');
    }

    /**
     * 测试工具执行时出现异常的处理情况.
     */
    public function testExceptionHandling()
    {
        $executor = new ToolExecutor();

        // 添加一个会抛出异常的回调
        $executor->add(function () {
            throw new RuntimeException('测试异常');
            return 'unreachable';
        });

        // 添加一个正常的回调
        $executor->add(function () {
            return 'normal result';
        });

        // 设置为串行模式以便于测试
        $executor->setParallel(false);

        // 执行并检查结果
        $results = $executor->run();

        // 验证结果
        $this->assertCount(2, $results, '应该包含两个结果');
        $this->assertIsArray($results[0], '异常结果应该被转换为数组');
        $this->assertArrayHasKey('error', $results[0], '异常结果应该包含 error 键');
        $this->assertEquals('测试异常', $results[0]['error'], '应该包含异常消息');
        $this->assertEquals('normal result', $results[1], '正常回调应该正常执行');
    }

    /**
     * 直接测试 executeToolSafely 方法对异常的捕获处理.
     */
    public function testExecuteToolSafely()
    {
        $executor = new ToolExecutor();

        // 测试正常回调
        $normalCallback = function () {
            return 'success';
        };
        $result = $this->callNonpublicMethod($executor, 'executeToolSafely', $normalCallback);
        $this->assertEquals('success', $result, '正常回调应返回预期结果');

        // 测试抛出异常的回调
        $errorCallback = function () {
            throw new InvalidArgumentException('参数无效');
            return 'unreachable';
        };
        $result = $this->callNonpublicMethod($executor, 'executeToolSafely', $errorCallback);
        $this->assertIsArray($result, '异常结果应该被转换为数组');
        $this->assertArrayHasKey('error', $result, '异常结果应该包含 error 键');
        $this->assertEquals('参数无效', $result['error'], '应该包含异常消息');

        // 测试抛出其他类型异常的回调
        $fatalCallback = function () {
            throw new Error('致命错误');
            return 'unreachable';
        };
        $result = $this->callNonpublicMethod($executor, 'executeToolSafely', $fatalCallback);
        $this->assertIsArray($result, 'Error 也应该被捕获并转换为数组');
        $this->assertArrayHasKey('error', $result, '异常结果应该包含 error 键');
        $this->assertEquals('致命错误', $result['error'], '应该包含错误消息');
    }

    /**
     * 测试 isParallelAvailable 方法的判断逻辑.
     */
    public function testIsParallelAvailable()
    {
        $executor = new ToolExecutor();

        // Parallel 类应该存在于项目中
        $result = $this->callNonpublicMethod($executor, 'isParallelAvailable');

        if (class_exists(Parallel::class)) {
            $this->assertTrue($result, 'Parallel 类存在时应返回 true');
        } else {
            $this->assertFalse($result, 'Parallel 类不存在时应返回 false');
        }
    }

    /**
     * 测试 createParallel 方法的创建逻辑.
     */
    public function testCreateParallel()
    {
        $this->markTestSkipped('协程环境下并行执行测试暂时跳过');
        $executor = new ToolExecutor();

        $parallel = $this->callNonpublicMethod($executor, 'createParallel');

        if (class_exists(Parallel::class)) {
            $this->assertInstanceOf(Parallel::class, $parallel, '应返回 Parallel 实例');
        } else {
            $this->assertNull($parallel, 'Parallel 类不存在时应返回 null');
        }
    }

    /**
     * 测试 run 方法在并行模式下的执行.
     */
    public function testRunInParallelMode()
    {
        $this->markTestSkipped('协程环境下并行执行测试暂时跳过');
        // 如果 Parallel 类不存在，跳过此测试
        if (! class_exists(Parallel::class)) {
            $this->markTestSkipped('Parallel 类不存在，跳过测试');
        }

        $executor = new ToolExecutor();

        // 确保使用并行模式
        $executor->setParallel(true);

        // 添加多个回调函数
        $executor
            ->add(function () {
                return 'result1';
            })
            ->add(function () {
                return ['key' => 'value2'];
            })
            ->add(function () {
                return (object) ['name' => 'object3'];
            });

        // 执行并检查结果
        $results = $executor->run();

        // 验证结果数量和顺序
        $this->assertCount(3, $results, '应该包含三个结果');
        $this->assertEquals('result1', $results[0], '第一个结果应该是字符串');
        $this->assertEquals(['key' => 'value2'], $results[1], '第二个结果应该是数组');
        $this->assertEquals((object) ['name' => 'object3'], $results[2], '第三个结果应该是对象');
    }
}
