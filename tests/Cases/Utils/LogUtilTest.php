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

use Hyperf\Odin\Utils\LogUtil;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Hyperf\Odin\Utils\LogUtil
 */
class LogUtilTest extends TestCase
{
    public function testFormatLongTextWithNormalData()
    {
        $data = [
            'model_id' => 'gpt-4o',
            'duration_ms' => 1500,
            'content' => 'This is normal content',
        ];

        $result = LogUtil::formatLongText($data);

        $this->assertIsArray($result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);
        $this->assertEquals('This is normal content', $result['content']);
    }

    public function testFormatLongTextWithLongString()
    {
        $longText = str_repeat('a', 1500); // > 1000 characters
        $data = [
            'model_id' => 'gpt-4o',
            'content' => $longText,
        ];

        $result = LogUtil::formatLongText($data);

        $this->assertIsArray($result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals('[Long Text]', $result['content']);
    }

    public function testFormatLongTextWithBinaryData()
    {
        $binaryData = "\x00\x01\x02\x03"; // binary data
        $data = [
            'model_id' => 'gpt-4o',
            'binary' => $binaryData,
        ];

        $result = LogUtil::formatLongText($data);

        $this->assertIsArray($result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals('[Binary Data]', $result['binary']);
    }

    public function testFormatLongTextWithBase64Image()
    {
        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $data = [
            'model_id' => 'gpt-4o',
            'image' => $base64Image,
        ];

        $result = LogUtil::formatLongText($data);

        $this->assertIsArray($result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals('[Base64 Image]', $result['image']);
    }

    public function testFilterAndFormatLogDataWithoutWhitelist()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['key' => 'value'],
            'duration_ms' => 1500,
            'usage' => ['input_tokens' => 100],
            'content' => 'response content',
            'sensitive_info' => 'secret data',
        ];

        // Test with whitelist disabled
        $result = LogUtil::filterAndFormatLogData($logData, [], false);

        $this->assertIsArray($result);
        $this->assertCount(6, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(['key' => 'value'], $result['args']);
        $this->assertEquals(1500, $result['duration_ms']);
        $this->assertEquals(['input_tokens' => 100], $result['usage']);
        $this->assertEquals('response content', $result['content']);
        $this->assertEquals('secret data', $result['sensitive_info']);
    }

    public function testFilterAndFormatLogDataWithWhitelist()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['key' => 'value'],
            'duration_ms' => 1500,
            'usage' => ['input_tokens' => 100],
            'content' => 'response content',
            'sensitive_info' => 'secret data',
        ];
        $whitelistFields = ['model_id', 'duration_ms', 'content'];

        // Test with whitelist enabled
        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(1500, $result['duration_ms']);
        $this->assertEquals('response content', $result['content']);
        $this->assertArrayNotHasKey('args', $result);
        $this->assertArrayNotHasKey('usage', $result);
        $this->assertArrayNotHasKey('sensitive_info', $result);
    }

    public function testFilterAndFormatLogDataWithEmptyWhitelist()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['key' => 'value'],
            'duration_ms' => 1500,
        ];

        // Test with empty whitelist but enabled - should show all fields
        $result = LogUtil::filterAndFormatLogData($logData, [], true);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(['key' => 'value'], $result['args']);
        $this->assertEquals(1500, $result['duration_ms']);
    }

    public function testFilterAndFormatLogDataWithNonexistentWhitelistFields()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'duration_ms' => 1500,
        ];
        $whitelistFields = ['model_id', 'nonexistent_field', 'another_missing_field'];

        // Test with whitelist containing non-existent fields
        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertArrayNotHasKey('nonexistent_field', $result);
        $this->assertArrayNotHasKey('another_missing_field', $result);
        $this->assertArrayNotHasKey('duration_ms', $result); // not in whitelist
    }

    public function testFilterAndFormatLogDataWithComplexData()
    {
        $longText = str_repeat('x', 1500);
        $binaryData = "\x00\x01\x02\x03";

        $logData = [
            'model_id' => 'gpt-4o',
            'long_content' => $longText,
            'binary_data' => $binaryData,
            'nested_array' => [
                'key1' => 'value1',
                'key2' => $longText,
            ],
            'duration_ms' => 1500,
        ];
        $whitelistFields = ['model_id', 'long_content', 'nested_array'];

        // Test with whitelist and complex data formatting
        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals('[Long Text]', $result['long_content']);
        $this->assertIsArray($result['nested_array']);
        $this->assertEquals('value1', $result['nested_array']['key1']);
        $this->assertEquals('[Long Text]', $result['nested_array']['key2']);
        $this->assertArrayNotHasKey('binary_data', $result);
        $this->assertArrayNotHasKey('duration_ms', $result);
    }

    public function testFilterAndFormatLogDataWithNestedFieldsBasic()
    {
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
        $whitelistFields = ['model_id', 'args.messages', 'usage.input_tokens'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

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

    public function testFilterAndFormatLogDataWithDeepNestedFields()
    {
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
        $whitelistFields = ['user.profile.name', 'user.profile.settings.theme', 'session.id'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

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

    public function testFilterAndFormatLogDataWithMixedNestedAndRegularFields()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'args' => [
                'messages' => [['role' => 'user', 'content' => 'Hello']],
                'temperature' => 0.7,
            ],
            'duration_ms' => 1500,
            'status' => 'success',
        ];
        $whitelistFields = ['model_id', 'args.messages', 'duration_ms'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

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

    public function testFilterAndFormatLogDataWithNonexistentNestedFields()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'args' => [
                'messages' => [['role' => 'user', 'content' => 'Hello']],
            ],
            'duration_ms' => 1500,
        ];
        $whitelistFields = [
            'model_id',
            'args.messages',
            'args.nonexistent',         // 不存在的嵌套字段
            'completely.missing.path',   // 完全不存在的路径
            'usage.tokens',             // 父级不存在
        ];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

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

    public function testFilterAndFormatLogDataWithNestedFieldsAndFormatting()
    {
        $longText = str_repeat('x', 1500);
        $binaryData = "\x00\x01\x02\x03";

        $logData = [
            'model_id' => 'gpt-4o',
            'args' => [
                'messages' => [
                    ['role' => 'user', 'content' => $longText],
                ],
                'metadata' => [
                    'binary_info' => $binaryData,
                    'image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==',
                ],
            ],
            'duration_ms' => 1500,
        ];
        $whitelistFields = ['model_id', 'args.messages', 'args.metadata.binary_info', 'args.metadata.image'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

        $this->assertIsArray($result);

        // 检查基本字段
        $this->assertEquals('gpt-4o', $result['model_id']);

        // 检查长文本被格式化
        $this->assertEquals('[Long Text]', $result['args']['messages'][0]['content']);

        // 检查二进制数据被格式化
        $this->assertEquals('[Binary Data]', $result['args']['metadata']['binary_info']);

        // 检查Base64图片被格式化
        $this->assertEquals('[Base64 Image]', $result['args']['metadata']['image']);

        // 检查不应该存在的字段
        $this->assertArrayNotHasKey('duration_ms', $result);
    }

    public function testFilterAndFormatLogDataWithEmptyNestedValues()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'args' => [
                'messages' => [],
                'empty_nested' => [
                    'value' => null,
                ],
            ],
            'usage' => null,
        ];
        $whitelistFields = ['model_id', 'args.messages', 'args.empty_nested.value', 'usage'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

        $this->assertIsArray($result);

        // 空数组应该被保留
        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertArrayHasKey('args', $result);
        $this->assertArrayHasKey('messages', $result['args']);
        $this->assertEquals([], $result['args']['messages']);

        // null 值应该被保留
        $this->assertArrayHasKey('empty_nested', $result['args']);
        $this->assertArrayHasKey('value', $result['args']['empty_nested']);
        $this->assertNull($result['args']['empty_nested']['value']);

        // 顶级 null 值也应该被保留
        $this->assertArrayHasKey('usage', $result);
        $this->assertNull($result['usage']);
    }

    public function testFilterAndFormatLogDataWithResponseHeaders()
    {
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
        $whitelistFields = ['model_id', 'duration_ms'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

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

    public function testFilterAndFormatLogDataWithHeaders()
    {
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
        $whitelistFields = ['model_id'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

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

    public function testFilterAndFormatLogDataWithBothHeaderTypes()
    {
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
        $whitelistFields = ['model_id'];

        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, true);

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

    public function testFilterAndFormatLogDataWithHeadersInWhitelistDisabled()
    {
        $logData = [
            'model_id' => 'gpt-4o',
            'args' => ['temperature' => 0.7],
            'headers' => [
                'authorization' => 'Bearer xxx',
            ],
            'response_headers' => [
                'content-type' => 'application/json',
            ],
            'duration_ms' => 1500,
        ];
        $whitelistFields = ['model_id'];

        // 测试白名单未启用时，所有字段都应该被保留
        $result = LogUtil::filterAndFormatLogData($logData, $whitelistFields, false);

        $this->assertIsArray($result);
        $this->assertCount(5, $result); // 所有字段都应该存在

        $this->assertEquals('gpt-4o', $result['model_id']);
        $this->assertEquals(['temperature' => 0.7], $result['args']);
        $this->assertEquals(['authorization' => 'Bearer xxx'], $result['headers']);
        $this->assertEquals(['content-type' => 'application/json'], $result['response_headers']);
        $this->assertEquals(1500, $result['duration_ms']);
    }
}
