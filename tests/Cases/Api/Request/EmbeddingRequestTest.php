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

namespace HyperfTest\Odin\Cases\Api\Request;

use GuzzleHttp\RequestOptions;
use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Exception\InvalidArgumentException;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Api\Request\EmbeddingRequest
 */
class EmbeddingRequestTest extends AbstractTestCase
{
    /**
     * 测试嵌入请求的构造和基本属性.
     */
    public function testEmbeddingRequestConstructionAndProperties()
    {
        // 创建一个字符串输入的嵌入请求
        $request1 = new EmbeddingRequest(
            input: '这是一段测试文本',
            model: 'text-embedding-ada-002',
            encoding_format: 'float',
            user: 'test-user'
        );

        // 验证属性
        $this->assertEquals('这是一段测试文本', $request1->getInput());
        $this->assertEquals('text-embedding-ada-002', $request1->getModel());
        $this->assertEquals('float', $request1->getEncodingFormat());
        $this->assertEquals('test-user', $request1->getUser());
        $this->assertNull($request1->getDimensions());

        // 创建一个数组输入的嵌入请求，带有维度参数
        $request2 = new EmbeddingRequest(
            input: ['这是第一段文本', '这是第二段文本'],
            model: 'text-embedding-ada-002',
            encoding_format: 'base64',
            dimensions: [1536]
        );

        // 验证属性
        $this->assertEquals(['这是第一段文本', '这是第二段文本'], $request2->getInput());
        $this->assertEquals('text-embedding-ada-002', $request2->getModel());
        $this->assertEquals('base64', $request2->getEncodingFormat());
        $this->assertNull($request2->getUser());
        $this->assertEquals([1536], $request2->getDimensions());
    }

    /**
     * 测试验证方法.
     */
    public function testValidateMethod()
    {
        // 有效请求应该通过验证
        $validRequest = new EmbeddingRequest(
            input: '这是一段测试文本',
            model: 'text-embedding-ada-002'
        );
        $validRequest->validate(); // 不应抛出异常

        // 测试空模型
        $invalidModelRequest = new EmbeddingRequest(
            input: '这是一段测试文本',
            model: ''
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model is required');
        $invalidModelRequest->validate();
    }

    /**
     * 测试空输入验证.
     */
    public function testEmptyInputValidation()
    {
        // 测试空输入
        $invalidInputRequest = new EmbeddingRequest(
            input: '',
            model: 'text-embedding-ada-002'
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input is required');
        $invalidInputRequest->validate();
    }

    /**
     * 测试无效的编码格式验证.
     */
    public function testInvalidEncodingFormatValidation()
    {
        // 测试无效的编码格式
        $invalidFormatRequest = new EmbeddingRequest(
            input: '这是一段测试文本',
            model: 'text-embedding-ada-002',
            encoding_format: 'invalid-format'
        );
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Encoding format must be either float or base64');
        $invalidFormatRequest->validate();
    }

    /**
     * 测试创建请求选项方法.
     */
    public function testCreateOptions()
    {
        // 创建基本请求
        $request = new EmbeddingRequest(
            input: '这是一段测试文本',
            model: 'text-embedding-ada-002'
        );

        // 获取请求选项
        $options = $request->createOptions();

        // 验证选项格式
        $this->assertArrayHasKey(RequestOptions::JSON, $options);
        $this->assertArrayHasKey('input', $options[RequestOptions::JSON]);
        $this->assertArrayHasKey('model', $options[RequestOptions::JSON]);
        $this->assertArrayHasKey('encoding_format', $options[RequestOptions::JSON]);

        // 验证值
        $this->assertEquals('这是一段测试文本', $options[RequestOptions::JSON]['input']);
        $this->assertEquals('text-embedding-ada-002', $options[RequestOptions::JSON]['model']);
        $this->assertEquals('float', $options[RequestOptions::JSON]['encoding_format']);

        // 创建包含所有可选参数的请求
        $fullRequest = new EmbeddingRequest(
            input: ['这是第一段文本', '这是第二段文本'],
            model: 'text-embedding-ada-002',
            encoding_format: 'base64',
            user: 'test-user',
            dimensions: [1536]
        );

        // 获取请求选项
        $fullOptions = $fullRequest->createOptions();

        // 验证选项格式
        $this->assertArrayHasKey('user', $fullOptions[RequestOptions::JSON]);
        $this->assertArrayHasKey('dimensions', $fullOptions[RequestOptions::JSON]);

        // 验证值
        $this->assertEquals(['这是第一段文本', '这是第二段文本'], $fullOptions[RequestOptions::JSON]['input']);
        $this->assertEquals('test-user', $fullOptions[RequestOptions::JSON]['user']);
        $this->assertEquals([1536], $fullOptions[RequestOptions::JSON]['dimensions']);
    }
}
