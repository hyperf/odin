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

use Hyperf\Odin\Agent\Tool\UsedTool;
use PHPUnit\Framework\TestCase;

/**
 * 测试 UsedTool 类的功能.
 * @internal
 * @coversNothing
 */
class UsedToolTest extends TestCase
{
    /**
     * 测试 __construct 方法能正确初始化对象属性.
     */
    public function testConstructor()
    {
        $elapsedTime = 0.5;
        $success = true;
        $id = 'test-id';
        $name = 'test-tool';
        $arguments = ['arg1' => 'value1'];
        $result = 'test-result';
        $errorMessage = '';

        $usedTool = new UsedTool(
            $elapsedTime,
            $success,
            $id,
            $name,
            $arguments,
            $result,
            $errorMessage
        );

        $this->assertEquals($elapsedTime, $usedTool->getElapsedTime());
        $this->assertEquals($success, $usedTool->isSuccess());
        $this->assertEquals($id, $usedTool->getId());
        $this->assertEquals($name, $usedTool->getName());
        $this->assertEquals($arguments, $usedTool->getArguments());
        $this->assertEquals($result, $usedTool->getResult());
        $this->assertEquals($errorMessage, $usedTool->getErrorMessage());
    }

    /**
     * 测试 toArray 方法返回的数组格式正确.
     */
    public function testToArray()
    {
        $elapsedTime = 0.5;
        $success = true;
        $id = 'test-id';
        $name = 'test-tool';
        $arguments = ['arg1' => 'value1'];
        $result = 'test-result';
        $errorMessage = '';

        $usedTool = new UsedTool(
            $elapsedTime,
            $success,
            $id,
            $name,
            $arguments,
            $result,
            $errorMessage
        );

        $expected = [
            'elapsed_time' => $elapsedTime,
            'success' => $success,
            'id' => $id,
            'name' => $name,
            'arguments' => $arguments,
            'result' => $result,
            'error_message' => $errorMessage,
        ];

        $this->assertEquals($expected, $usedTool->toArray());
    }

    /**
     * 测试 getElapsedTime 方法返回值正确.
     */
    public function testGetElapsedTime()
    {
        $elapsedTime = 1.23;
        $usedTool = new UsedTool(
            $elapsedTime,
            true,
            'id',
            'name',
            [],
            null
        );

        $this->assertEquals($elapsedTime, $usedTool->getElapsedTime());
    }

    /**
     * 测试 isSuccess 方法返回值正确.
     */
    public function testIsSuccess()
    {
        // 测试成功的情况
        $usedTool = new UsedTool(
            0.1,
            true,
            'id',
            'name',
            [],
            null
        );
        $this->assertTrue($usedTool->isSuccess());

        // 测试失败的情况
        $usedTool = new UsedTool(
            0.1,
            false,
            'id',
            'name',
            [],
            null,
            'Error occurred'
        );
        $this->assertFalse($usedTool->isSuccess());
    }

    /**
     * 测试 getId 方法返回值正确.
     */
    public function testGetId()
    {
        $id = 'test-tool-id-123';
        $usedTool = new UsedTool(
            0.1,
            true,
            $id,
            'name',
            [],
            null
        );

        $this->assertEquals($id, $usedTool->getId());
    }

    /**
     * 测试 getName 方法返回值正确.
     */
    public function testGetName()
    {
        $name = 'test-tool-name';
        $usedTool = new UsedTool(
            0.1,
            true,
            'id',
            $name,
            [],
            null
        );

        $this->assertEquals($name, $usedTool->getName());
    }

    /**
     * 测试 getArguments 方法返回值正确.
     */
    public function testGetArguments()
    {
        $arguments = [
            'arg1' => 'value1',
            'arg2' => 123,
            'arg3' => ['nested' => true],
        ];
        $usedTool = new UsedTool(
            0.1,
            true,
            'id',
            'name',
            $arguments,
            null
        );

        $this->assertEquals($arguments, $usedTool->getArguments());
    }

    /**
     * 测试 getResult 方法返回值正确.
     */
    public function testGetResult()
    {
        // 测试字符串结果
        $result = 'string result';
        $usedTool = new UsedTool(
            0.1,
            true,
            'id',
            'name',
            [],
            $result
        );
        $this->assertEquals($result, $usedTool->getResult());

        // 测试数组结果
        $result = ['key' => 'value', 'nested' => ['data' => true]];
        $usedTool = new UsedTool(
            0.1,
            true,
            'id',
            'name',
            [],
            $result
        );
        $this->assertEquals($result, $usedTool->getResult());

        // 测试对象结果
        $result = (object) ['property' => 'value'];
        $usedTool = new UsedTool(
            0.1,
            true,
            'id',
            'name',
            [],
            $result
        );
        $this->assertEquals($result, $usedTool->getResult());
    }

    /**
     * 测试 getErrorMessage 方法返回值正确.
     */
    public function testGetErrorMessage()
    {
        // 测试无错误信息
        $usedTool = new UsedTool(
            0.1,
            true,
            'id',
            'name',
            [],
            null
        );
        $this->assertEquals('', $usedTool->getErrorMessage());

        // 测试有错误信息
        $errorMessage = 'This is an error message';
        $usedTool = new UsedTool(
            0.1,
            false,
            'id',
            'name',
            [],
            null,
            $errorMessage
        );
        $this->assertEquals($errorMessage, $usedTool->getErrorMessage());
    }
}
