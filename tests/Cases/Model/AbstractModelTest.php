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

namespace HyperfTest\Odin\Cases\Model;

use Hyperf\Odin\Api\Request\ChatCompletionRequest;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionResponse;
use Hyperf\Odin\Api\Response\ChatCompletionStreamResponse;
use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Exception\LLMException\ErrorHandlerInterface;
use Hyperf\Odin\Exception\LLMException\LLMErrorHandler;
use Hyperf\Odin\Exception\LLMException\Model\LLMFunctionCallNotSupportedException;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AbstractModel;
use Hyperf\Odin\Model\ModelOptions;
use HyperfTest\Odin\Cases\AbstractTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * 测试用的具体 AbstractModel 实现.
 */
class TestModel extends AbstractModel
{
    public function getClient(): ClientInterface
    {
        // 在测试中我们不需要真正的实现
        return new class implements ClientInterface {
            public function chatCompletions(ChatCompletionRequest $chatRequest): ChatCompletionResponse
            {
                // 创建模拟的 HTTP 响应对象
                $stream = Mockery::mock(StreamInterface::class);
                $stream->shouldReceive('getContents')->andReturn('{}');

                $response = Mockery::mock(ResponseInterface::class);
                $response->shouldReceive('getStatusCode')->andReturn(200);
                $response->shouldReceive('getBody')->andReturn($stream);

                return new ChatCompletionResponse($response);
            }

            public function chatCompletionsStream(ChatCompletionRequest $chatRequest): ChatCompletionStreamResponse
            {
                // 创建模拟的 HTTP 响应对象
                $stream = Mockery::mock(StreamInterface::class);
                $stream->shouldReceive('getContents')->andReturn('{}');

                $response = Mockery::mock(ResponseInterface::class);
                $response->shouldReceive('getStatusCode')->andReturn(200);
                $response->shouldReceive('getBody')->andReturn($stream);

                return new ChatCompletionStreamResponse($response);
            }
        };
    }
}

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(AbstractModel::class)]
class AbstractModelTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试构造函数初始化.
     */
    public function testConstructor()
    {
        $model = new TestModel('test-model', ['api_key' => 'test-key']);

        $this->assertEquals('test-model', $this->getNonpublicProperty($model, 'model'));
        $this->assertEquals(['api_key' => 'test-key'], $this->getNonpublicProperty($model, 'config'));
        $this->assertInstanceOf(ModelOptions::class, $this->getNonpublicProperty($model, 'modelOptions'));
        $this->assertInstanceOf(ApiOptions::class, $this->getNonpublicProperty($model, 'apiRequestOptions'));
    }

    /**
     * 测试 getApiRequestOptions 方法.
     */
    public function testGetApiRequestOptions()
    {
        $model = new TestModel('test-model', []);
        $apiOptions = new ApiOptions();

        $this->setNonpublicPropertyValue($model, 'apiRequestOptions', $apiOptions);
        $this->assertSame($apiOptions, $model->getApiRequestOptions());
    }

    /**
     * 测试 setApiRequestOptions 方法.
     */
    public function testSetApiRequestOptions()
    {
        $model = new TestModel('test-model', []);
        $apiOptions = new ApiOptions();

        $result = $this->callNonpublicMethod($model, 'setApiRequestOptions', $apiOptions);

        $this->assertSame($model, $result);
        $this->assertSame($apiOptions, $this->getNonpublicProperty($model, 'apiRequestOptions'));
    }

    /**
     * 测试 setModelOptions 方法.
     */
    public function testSetModelOptions()
    {
        $model = new TestModel('test-model', []);
        $modelOptions = new ModelOptions(['chat' => false]);

        $result = $this->callNonpublicMethod($model, 'setModelOptions', $modelOptions);

        $this->assertSame($model, $result);
        $this->assertSame($modelOptions, $this->getNonpublicProperty($model, 'modelOptions'));
    }

    /**
     * 测试 getErrorHandler 方法.
     */
    public function testGetErrorHandler()
    {
        $model = new TestModel('test-model', []);

        $errorHandler = $this->callNonpublicMethod($model, 'getErrorHandler');

        $this->assertInstanceOf(ErrorHandlerInterface::class, $errorHandler);
        $this->assertInstanceOf(LLMErrorHandler::class, $errorHandler);
    }

    /**
     * 测试创建错误上下文方法.
     */
    public function testCreateErrorContext()
    {
        $model = new TestModel('test-model', ['api_key' => 'test-key']);

        $messages = [new UserMessage('Test')];
        $temperature = 0.7;
        $maxTokens = 100;
        $stop = ['stop'];
        $tools = [];
        $isStream = true;

        $params = [
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stop' => $stop,
            'tools' => $tools,
            'is_stream' => $isStream
        ];

        $context = $this->callNonpublicMethod(
            $model,
            'createErrorContext',
            $params
        );

        $this->assertIsArray($context);
        $this->assertEquals('test-model', $context['model']);
        $this->assertArrayHasKey('messages', $context);
        $this->assertEquals($temperature, $context['temperature']);
        $this->assertEquals($maxTokens, $context['max_tokens']);
        $this->assertEquals($stop, $context['stop']);
        $this->assertEquals($tools, $context['tools']);
        $this->assertEquals($isStream, $context['is_stream']);
        $this->assertEquals(['api_key' => 'test-key'], $context['config']);
    }

    /**
     * 测试 checkFunctionCallSupport 方法（抛出异常的情况）.
     */
    public function testCheckFunctionCallSupportThrowsException()
    {
        $model = new TestModel('test-model', []);

        // 设置 ModelOptions 的 functionCall 为 false
        $modelOptions = new ModelOptions(['function_call' => false]);
        $this->setNonpublicPropertyValue($model, 'modelOptions', $modelOptions);

        // 准备工具数组
        $tools = [['type' => 'function', 'function' => ['name' => 'test_function']]];

        $this->expectException(LLMFunctionCallNotSupportedException::class);
        $this->callNonpublicMethod($model, 'checkFunctionCallSupport', $tools);
    }

    /**
     * 测试 checkFunctionCallSupport 方法（不抛出异常的情况）.
     */
    public function testCheckFunctionCallSupportNoException()
    {
        $model = new TestModel('test-model', []);

        // 设置 ModelOptions 的 functionCall 为 true
        $modelOptions = new ModelOptions(['function_call' => true]);
        $this->setNonpublicPropertyValue($model, 'modelOptions', $modelOptions);

        // 准备工具数组
        $tools = [['type' => 'function', 'function' => ['name' => 'test_function']]];

        // 应该不会抛出异常
        $this->callNonpublicMethod($model, 'checkFunctionCallSupport', $tools);
        $this->assertTrue(true); // 断言测试通过
    }
}
