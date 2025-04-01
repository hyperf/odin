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

namespace HyperfTest\Odin\Model\ApiVersionTest;

use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class UrlHelperTest extends AbstractTestCase
{
    /**
     * 测试基本的URL解析.
     */
    public function testUrlParsing()
    {
        $urls = [
            'https://api.example.com' => ['path' => ''],
            'https://api.example.com/' => ['path' => '/'],
            'https://api.example.com/v1' => ['path' => '/v1'],
            'https://api.example.com/api/v3' => ['path' => '/api/v3'],
            'http://localhost:8000/api' => ['path' => '/api'],
            'http://localhost:11434' => ['path' => ''],
        ];

        foreach ($urls as $url => $expected) {
            $parts = parse_url($url);
            $this->assertEquals($expected['path'] ?? null, $parts['path'] ?? null, "URL: {$url}");

            // 测试我们的判断逻辑
            $hasPath = ! empty($parts['path']) && $parts['path'] !== '/';
            $shouldHavePath = ! empty($expected['path']) && $expected['path'] !== '/';
            $this->assertEquals($shouldHavePath, $hasPath, "URL path check for: {$url}");
        }
    }
}
