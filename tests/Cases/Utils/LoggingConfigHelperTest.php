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

namespace HyperfTest\Odin\Cases\Utils;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Odin\Utils\LoggingConfigHelper;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @internal
 * @covers \Hyperf\Odin\Utils\LoggingConfigHelper
 */
class LoggingConfigHelperTest extends TestCase
{
    private ContainerInterface $originalContainer;

    protected function setUp(): void
    {
        parent::setUp();

        // 保存原始容器
        if (ApplicationContext::hasContainer()) {
            $this->originalContainer = ApplicationContext::getContainer();
        }
    }

    protected function tearDown(): void
    {
        // 恢复原始容器
        if (isset($this->originalContainer)) {
            ApplicationContext::setContainer($this->originalContainer);
        }

        parent::tearDown();
    }

    public function testGetWhitelistFieldsWithConfiguredFields()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id', 'duration_ms', 'content'],
        ]);
        $this->setMockContainer($mockConfig);

        $fields = LoggingConfigHelper::getWhitelistFields();

        $this->assertIsArray($fields);
        $this->assertCount(3, $fields);
        $this->assertEquals(['model_id', 'duration_ms', 'content'], $fields);
    }

    public function testGetWhitelistFieldsWithEmptyConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => [],
        ]);
        $this->setMockContainer($mockConfig);

        $fields = LoggingConfigHelper::getWhitelistFields();

        $this->assertIsArray($fields);
        $this->assertCount(0, $fields);
        $this->assertEquals([], $fields);
    }

    public function testGetWhitelistFieldsWithoutConfig()
    {
        $mockConfig = $this->createMockConfig([]);
        $this->setMockContainer($mockConfig);

        $fields = LoggingConfigHelper::getWhitelistFields();

        $this->assertIsArray($fields);
        $this->assertCount(0, $fields);
        $this->assertEquals([], $fields);
    }

    public function testGetWhitelistFieldsWithConfigException()
    {
        $mockContainer = $this->createMock(ContainerInterface::class);
        $mockContainer->method('get')
            ->with(ConfigInterface::class)
            ->willThrowException(new RuntimeException('Config not available'));

        ApplicationContext::setContainer($mockContainer);

        $fields = LoggingConfigHelper::getWhitelistFields();

        $this->assertIsArray($fields);
        $this->assertCount(0, $fields);
        $this->assertEquals([], $fields);
    }

    public function testIsWhitelistEnabledWithTrueConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $enabled = LoggingConfigHelper::isWhitelistEnabled();

        $this->assertTrue($enabled);
    }

    public function testIsWhitelistEnabledWithFalseConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.enable_whitelist' => false,
        ]);
        $this->setMockContainer($mockConfig);

        $enabled = LoggingConfigHelper::isWhitelistEnabled();

        $this->assertFalse($enabled);
    }

    public function testIsWhitelistEnabledWithoutConfig()
    {
        $mockConfig = $this->createMockConfig([]);
        $this->setMockContainer($mockConfig);

        $enabled = LoggingConfigHelper::isWhitelistEnabled();

        $this->assertFalse($enabled);
    }

    public function testIsWhitelistEnabledWithStringTrueConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.enable_whitelist' => '1',
        ]);
        $this->setMockContainer($mockConfig);

        $enabled = LoggingConfigHelper::isWhitelistEnabled();

        $this->assertTrue($enabled);
    }

    public function testIsWhitelistEnabledWithConfigException()
    {
        $mockContainer = $this->createMock(ContainerInterface::class);
        $mockContainer->method('get')
            ->with(ConfigInterface::class)
            ->willThrowException(new RuntimeException('Config not available'));

        ApplicationContext::setContainer($mockContainer);

        $enabled = LoggingConfigHelper::isWhitelistEnabled();

        $this->assertFalse($enabled);
    }

    public function testFilterAndFormatLogDataWithEnabledWhitelist()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id', 'duration_ms'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['key' => 'value'],
            'duration_ms' => 1500,
            'content' => 'response content',
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);
        $this->assertArrayNotHasKey('args', $result);
        $this->assertArrayNotHasKey('content', $result);
    }

    public function testFilterAndFormatLogDataWithDisabledWhitelist()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id', 'duration_ms'],
            'odin.llm.general_api_options.logging.enable_whitelist' => false,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['key' => 'value'],
            'duration_ms' => 1500,
            'content' => 'response content',
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(4, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(['key' => 'value'], $result['args']);
        $this->assertEquals(1500, $result['duration_ms']);
        $this->assertEquals('response content', $result['content']);
    }

    public function testFilterAndFormatLogDataWithEmptyWhitelistFields()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => [],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'duration_ms' => 1500,
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);
    }

    public function testFilterAndFormatLogDataWithComplexDataAndFormatting()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id', 'long_content'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $longText = str_repeat('x', 1500); // > 1000 characters
        $logData = [
            'model_id' => 'gpt-4o',
            'long_content' => $longText,
            'args' => ['key' => 'value'],
            'duration_ms' => 1500,
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals('[Long Text]', $result['long_content']);
        $this->assertArrayNotHasKey('args', $result);
        $this->assertArrayNotHasKey('duration_ms', $result);
    }

    public function testFilterAndFormatLogDataWithConfigException()
    {
        $mockContainer = $this->createMock(ContainerInterface::class);
        $mockContainer->method('get')
            ->with(ConfigInterface::class)
            ->willThrowException(new RuntimeException('Config not available'));

        ApplicationContext::setContainer($mockContainer);

        $logData = [
            'model_id' => 'gpt-4o',
            'duration_ms' => 1500,
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        // Should return all data when config is not available
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);
    }

    public function testFilterAndFormatLogDataWithNestedFieldsConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id', 'args.messages', 'usage.input_tokens'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => [
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                    ['role' => 'assistant', 'content' => 'Hi there!'],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
            ],
            'duration_ms' => 1500,
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        // 检查顶级字段
        $this->assertEquals('gpt-4o', $result['model_id']);

        // 检查嵌套字段 args.messages
        $this->assertArrayHasKey('args', $result);
        $this->assertArrayHasKey('messages', $result['args']);
        $this->assertCount(2, $result['args']['messages']);
        $this->assertEquals('user', $result['args']['messages'][0]['role']);
        $this->assertEquals('Hello', $result['args']['messages'][0]['content']);

        // 检查不应该存在的字段
        $this->assertArrayNotHasKey('temperature', $result['args']);
        $this->assertArrayNotHasKey('max_tokens', $result['args']);

        // 检查嵌套字段 usage.input_tokens
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('input_tokens', $result['usage']);
        $this->assertEquals(100, $result['usage']['input_tokens']);

        // 检查不应该存在的字段
        $this->assertArrayNotHasKey('output_tokens', $result['usage']);
        $this->assertArrayNotHasKey('duration_ms', $result);
    }

    public function testFilterAndFormatLogDataWithDeepNestedFieldsConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['user.profile.name', 'user.profile.settings.theme', 'session.id'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'user' => [
                'profile' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'settings' => [
                        'theme' => 'dark',
                        'language' => 'en',
                    ],
                ],
                'permissions' => ['read', 'write'],
            ],
            'session' => [
                'id' => 'sess_123',
                'expires_at' => '2024-01-01T00:00:00Z',
            ],
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);

        // 检查深层嵌套字段
        $this->assertEquals('John Doe', $result['user']['profile']['name']);
        $this->assertEquals('dark', $result['user']['profile']['settings']['theme']);
        $this->assertEquals('sess_123', $result['session']['id']);

        // 检查不应该存在的字段
        $this->assertArrayNotHasKey('email', $result['user']['profile']);
        $this->assertArrayNotHasKey('language', $result['user']['profile']['settings']);
        $this->assertArrayNotHasKey('permissions', $result['user']);
        $this->assertArrayNotHasKey('expires_at', $result['session']);
    }

    public function testFilterAndFormatLogDataWithMixedNestedAndRegularFieldsConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id', 'args.messages', 'duration_ms'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => [
                'messages' => [['role' => 'user', 'content' => 'Hello']],
                'temperature' => 0.7,
            ],
            'duration_ms' => 1500,
            'status' => 'success',
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        // 检查常规字段
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);

        // 检查嵌套字段
        $this->assertArrayHasKey('args', $result);
        $this->assertArrayHasKey('messages', $result['args']);
        $this->assertArrayNotHasKey('temperature', $result['args']);

        // 检查不应该存在的字段
        $this->assertArrayNotHasKey('status', $result);
    }

    public function testFilterAndFormatLogDataWithNonexistentNestedFieldsConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => [
                'model_id',
                'args.messages',
                'args.nonexistent',         // 不存在的嵌套字段
                'completely.missing.path',   // 完全不存在的路径
                'usage.tokens',             // 父级不存在
            ],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => [
                'messages' => [['role' => 'user', 'content' => 'Hello']],
            ],
            'duration_ms' => 1500,
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // 只有存在的字段

        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertArrayHasKey('args', $result);
        $this->assertArrayHasKey('messages', $result['args']);

        // 检查不存在的字段确实不在结果中
        $this->assertArrayNotHasKey('nonexistent', $result['args']);
        $this->assertArrayNotHasKey('completely', $result);
        $this->assertArrayNotHasKey('usage', $result);
    }

    public function testFilterAndFormatLogDataWithResponseHeadersConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id', 'duration_ms'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['temperature' => 0.7],
            'duration_ms' => 1500,
            'response_headers' => [
                'content-type' => 'application/json',
                'x-request-id' => 'req_12345',
                'x-ratelimit-remaining' => '99',
            ],
            'sensitive_info' => 'should be filtered out',
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(3, $result); // model_id, duration_ms, response_headers

        // 检查白名单字段
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);

        // 检查响应头总是被保留（不参与白名单过滤）
        $this->assertArrayHasKey('response_headers', $result);
        $this->assertEquals('application/json', $result['response_headers']['content-type']);
        $this->assertEquals('req_12345', $result['response_headers']['x-request-id']);
        $this->assertEquals('99', $result['response_headers']['x-ratelimit-remaining']);

        // 检查不在白名单中的字段被过滤
        $this->assertArrayNotHasKey('args', $result);
        $this->assertArrayNotHasKey('sensitive_info', $result);
    }

    public function testFilterAndFormatLogDataWithHeadersConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['temperature' => 0.7],
            'duration_ms' => 1500,
            'headers' => [
                'authorization' => 'Bearer xxx',
                'user-agent' => 'odin/1.0',
                'content-length' => '1024',
            ],
            'sensitive_info' => 'should be filtered out',
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(2, $result); // model_id, headers

        // 检查白名单字段
        $this->assertEquals('gpt-4o', $result['model_id']);

        // 检查请求头总是被保留（不参与白名单过滤）
        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals('Bearer xxx', $result['headers']['authorization']);
        $this->assertEquals('odin/1.0', $result['headers']['user-agent']);
        $this->assertEquals('1024', $result['headers']['content-length']);

        // 检查不在白名单中的字段被过滤
        $this->assertArrayNotHasKey('args', $result);
        $this->assertArrayNotHasKey('duration_ms', $result);
        $this->assertArrayNotHasKey('sensitive_info', $result);
    }

    public function testFilterAndFormatLogDataWithBothHeaderTypesConfig()
    {
        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => ['model_id'],
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['temperature' => 0.7],
            'headers' => [
                'authorization' => 'Bearer xxx',
                'user-agent' => 'odin/1.0',
            ],
            'response_headers' => [
                'content-type' => 'application/json',
                'x-request-id' => 'req_12345',
            ],
            'sensitive_info' => 'should be filtered out',
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);
        $this->assertCount(3, $result); // model_id, headers, response_headers

        // 检查白名单字段
        $this->assertEquals('gpt-4o', $result['model_id']);

        // 检查两种头信息都被保留
        $this->assertArrayHasKey('headers', $result);
        $this->assertEquals('Bearer xxx', $result['headers']['authorization']);
        $this->assertEquals('odin/1.0', $result['headers']['user-agent']);

        $this->assertArrayHasKey('response_headers', $result);
        $this->assertEquals('application/json', $result['response_headers']['content-type']);
        $this->assertEquals('req_12345', $result['response_headers']['x-request-id']);

        // 检查不在白名单中的字段被过滤
        $this->assertArrayNotHasKey('args', $result);
        $this->assertArrayNotHasKey('sensitive_info', $result);
    }

    public function testFilterAndFormatLogDataWithDefaultOdinConfig()
    {
        // 模拟 odin.php 中的默认配置
        $defaultWhitelistFields = [
            // 基本请求信息
            'model_id',
            'model',
            'duration_ms',
            'url',
            'status_code',

            // 使用量统计
            'usage',
            'usage.input_tokens',
            'usage.output_tokens',
            'usage.total_tokens',

            // 请求参数（排除敏感内容）
            'args.temperature',
            'args.max_tokens',
            'args.top_p',
            'args.top_k',
            'args.frequency_penalty',
            'args.presence_penalty',
            'args.stream',
            'args.stop',
            'args.seed',

            // Token预估信息
            'token_estimate',
            'token_estimate.input_tokens',
            'token_estimate.output_tokens',

            // 响应内容（排除具体内容）
            'choices.0.finish_reason',
            'choices.0.index',

            // 错误信息
            'error',
            'error.type',
            'error.message',

            // 其他元数据
            'created',
            'id',
            'object',
            'system_fingerprint',
        ];

        $mockConfig = $this->createMockConfig([
            'odin.llm.general_api_options.logging.whitelist_fields' => $defaultWhitelistFields,
            'odin.llm.general_api_options.logging.enable_whitelist' => true,
        ]);
        $this->setMockContainer($mockConfig);

        $logData = [
            'model_id' => 'gpt-4o',
            'duration_ms' => 1500,
            'url' => 'https://api.openai.com/v1/chat/completions',
            'args' => [
                'messages' => [
                    ['role' => 'user', 'content' => '这是敏感的用户消息内容'],
                    ['role' => 'assistant', 'content' => '这是敏感的助手响应内容'],
                ],
                'tools' => [
                    ['type' => 'function', 'function' => ['name' => 'get_weather', 'description' => '获取天气信息']],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'stream' => false,
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => '这是敏感的响应内容'],
                    'finish_reason' => 'stop',
                ],
            ],
            'content' => '这是敏感的响应内容',
            'response_headers' => [
                'content-type' => 'application/json',
                'x-request-id' => 'req_12345',
            ],
        ];

        $result = LoggingConfigHelper::filterAndFormatLogData($logData);

        $this->assertIsArray($result);

        // 检查允许的字段被保留
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);
        $this->assertEquals('https://api.openai.com/v1/chat/completions', $result['url']);

        // 检查使用量统计被保留
        $this->assertArrayHasKey('usage', $result);
        $this->assertEquals(100, $result['usage']['input_tokens']);
        $this->assertEquals(50, $result['usage']['output_tokens']);
        $this->assertEquals(150, $result['usage']['total_tokens']);

        // 检查请求参数中的非敏感字段被保留
        $this->assertArrayHasKey('args', $result);
        $this->assertEquals(0.7, $result['args']['temperature']);
        $this->assertEquals(1000, $result['args']['max_tokens']);
        $this->assertFalse($result['args']['stream']);

        // 检查choices中的非敏感字段被保留
        $this->assertArrayHasKey('choices', $result);
        $this->assertArrayHasKey('0', $result['choices']);
        $this->assertEquals(0, $result['choices'][0]['index']);
        $this->assertEquals('stop', $result['choices'][0]['finish_reason']);

        // 检查choices中只有白名单字段被保留
        $this->assertCount(2, $result['choices'][0]); // 只有 index 和 finish_reason

        // 检查响应头被保留（特殊字段）
        $this->assertArrayHasKey('response_headers', $result);
        $this->assertEquals('application/json', $result['response_headers']['content-type']);
        $this->assertEquals('req_12345', $result['response_headers']['x-request-id']);

        // 检查敏感字段被过滤掉
        $this->assertArrayNotHasKey('messages', $result['args']); // 敏感：用户消息
        $this->assertArrayNotHasKey('tools', $result['args']);    // 敏感：工具定义
        $this->assertArrayNotHasKey('message', $result['choices'][0]); // 敏感：响应消息内容
        $this->assertArrayNotHasKey('content', $result);          // 敏感：响应内容

        // 验证被过滤掉的敏感信息确实不存在
        $resultJson = json_encode($result);
        $this->assertStringNotContainsString('这是敏感的用户消息内容', $resultJson);
        $this->assertStringNotContainsString('这是敏感的助手响应内容', $resultJson);
        $this->assertStringNotContainsString('这是敏感的响应内容', $resultJson);
        $this->assertStringNotContainsString('get_weather', $resultJson);
    }

    /**
     * Create a mock config interface.
     */
    private function createMockConfig(array $config): ConfigInterface
    {
        $mockConfig = $this->createMock(ConfigInterface::class);
        $mockConfig->method('get')
            ->willReturnCallback(function (string $key, $default = null) use ($config) {
                return $config[$key] ?? $default;
            });

        return $mockConfig;
    }

    /**
     * Set up mock container with config.
     */
    private function setMockContainer(ConfigInterface $config): void
    {
        $mockContainer = $this->createMock(ContainerInterface::class);
        $mockContainer->method('get')
            ->with(ConfigInterface::class)
            ->willReturn($config);

        ApplicationContext::setContainer($mockContainer);
    }
}
