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

namespace HyperfTest\Odin\Cases\Tool\Implementation;

use Exception;
use Hyperf\Odin\Tool\AbstractTool;
use Hyperf\Odin\Tool\Definition\ToolParameters;
use HyperfTest\Odin\Cases\Tool\ToolBaseTestCase;

/**
 * 常见工具实现测试
 * 测试不同类型的通用工具实现.
 * @internal
 * @coversNothing
 */
class CommonToolsTest extends ToolBaseTestCase
{
    /**
     * 测试天气工具实现.
     */
    public function testWeatherTool(): void
    {
        // 创建天气工具
        $weatherTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $city = $parameters['city'] ?? '北京';
                $date = $parameters['date'] ?? 'today';

                // 模拟天气API结果
                $weatherData = [
                    '北京' => [
                        'today' => ['temp' => '22°C', 'condition' => '晴', 'humidity' => '45%', 'wind' => '东北风3级'],
                        'tomorrow' => ['temp' => '24°C', 'condition' => '多云', 'humidity' => '50%', 'wind' => '东风2级'],
                    ],
                    '上海' => [
                        'today' => ['temp' => '26°C', 'condition' => '多云', 'humidity' => '65%', 'wind' => '东南风4级'],
                        'tomorrow' => ['temp' => '25°C', 'condition' => '小雨', 'humidity' => '70%', 'wind' => '南风3级'],
                    ],
                    '广州' => [
                        'today' => ['temp' => '30°C', 'condition' => '晴', 'humidity' => '80%', 'wind' => '南风2级'],
                        'tomorrow' => ['temp' => '29°C', 'condition' => '雷阵雨', 'humidity' => '85%', 'wind' => '南风4级'],
                    ],
                ];

                if (! isset($weatherData[$city])) {
                    return [
                        'error' => true,
                        'message' => "找不到城市 {$city} 的天气信息",
                    ];
                }

                if (! isset($weatherData[$city][$date])) {
                    return [
                        'error' => true,
                        'message' => "找不到 {$city} 在 {$date} 的天气信息",
                    ];
                }

                return [
                    'city' => $city,
                    'date' => $date,
                    'weather' => $weatherData[$city][$date],
                    'message' => "获取 {$city} {$date} 天气成功",
                ];
            }
        };
        $weatherTool->setName('weather_tool');
        $weatherTool->setDescription('天气查询工具');
        $weatherTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => '城市名称',
                ],
                'date' => [
                    'type' => 'string',
                    'description' => '日期，today 或 tomorrow',
                    'enum' => ['today', 'tomorrow'],
                ],
            ],
            'required' => ['city'],
        ]));

        // 测试默认参数
        $result = $weatherTool->run(['city' => '北京']);
        $this->assertArrayHasKey('weather', $result);
        $this->assertEquals('北京', $result['city']);
        $this->assertEquals('today', $result['date']);
        $this->assertEquals('晴', $result['weather']['condition']);

        // 测试查询明天天气
        $result = $weatherTool->run(['city' => '上海', 'date' => 'tomorrow']);
        $this->assertEquals('上海', $result['city']);
        $this->assertEquals('tomorrow', $result['date']);
        $this->assertEquals('小雨', $result['weather']['condition']);

        // 测试无效城市
        $result = $weatherTool->run(['city' => '纽约']);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('找不到城市', $result['message']);
    }

    /**
     * 测试计算器工具实现.
     */
    public function testCalculatorTool(): void
    {
        // 创建计算器工具
        $calculatorTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $operation = $parameters['operation'] ?? 'add';
                $a = $parameters['a'] ?? 0;
                $b = $parameters['b'] ?? 0;

                switch ($operation) {
                    case 'add':
                        $result = $a + $b;
                        $expression = "{$a} + {$b}";
                        break;
                    case 'subtract':
                        $result = $a - $b;
                        $expression = "{$a} - {$b}";
                        break;
                    case 'multiply':
                        $result = $a * $b;
                        $expression = "{$a} * {$b}";
                        break;
                    case 'divide':
                        if ($b == 0) {
                            return [
                                'error' => true,
                                'message' => '除数不能为零',
                            ];
                        }
                        $result = $a / $b;
                        $expression = "{$a} / {$b}";
                        break;
                    default:
                        return [
                            'error' => true,
                            'message' => "不支持的操作: {$operation}",
                        ];
                }

                return [
                    'operation' => $operation,
                    'expression' => $expression,
                    'result' => $result,
                ];
            }
        };
        $calculatorTool->setName('calculator_tool');
        $calculatorTool->setDescription('计算器工具');
        $calculatorTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => '运算类型',
                    'enum' => ['add', 'subtract', 'multiply', 'divide'],
                ],
                'a' => [
                    'type' => 'number',
                    'description' => '第一个操作数',
                ],
                'b' => [
                    'type' => 'number',
                    'description' => '第二个操作数',
                ],
            ],
            'required' => ['operation', 'a', 'b'],
        ]));

        // 测试加法
        $result = $calculatorTool->run(['operation' => 'add', 'a' => 5, 'b' => 3]);
        $this->assertEquals(8, $result['result']);
        $this->assertEquals('5 + 3', $result['expression']);

        // 测试减法
        $result = $calculatorTool->run(['operation' => 'subtract', 'a' => 10, 'b' => 4]);
        $this->assertEquals(6, $result['result']);
        $this->assertEquals('10 - 4', $result['expression']);

        // 测试乘法
        $result = $calculatorTool->run(['operation' => 'multiply', 'a' => 7, 'b' => 2]);
        $this->assertEquals(14, $result['result']);
        $this->assertEquals('7 * 2', $result['expression']);

        // 测试除法
        $result = $calculatorTool->run(['operation' => 'divide', 'a' => 20, 'b' => 5]);
        $this->assertEquals(4, $result['result']);
        $this->assertEquals('20 / 5', $result['expression']);

        // 测试除以零
        $result = $calculatorTool->run(['operation' => 'divide', 'a' => 10, 'b' => 0]);
        $this->assertTrue($result['error']);
        $this->assertEquals('除数不能为零', $result['message']);

        // 测试浮点数计算
        $result = $calculatorTool->run(['operation' => 'add', 'a' => 2.5, 'b' => 3.7]);
        $this->assertEquals(6.2, $result['result']);
    }

    /**
     * 测试搜索工具实现.
     */
    public function testSearchTool(): void
    {
        // 模拟数据库
        $database = [
            ['id' => 1, 'title' => '如何使用PHP编写API', 'category' => '编程', 'tags' => ['PHP', 'API', '教程']],
            ['id' => 2, 'title' => 'Laravel框架入门指南', 'category' => '编程', 'tags' => ['PHP', 'Laravel', '框架']],
            ['id' => 3, 'title' => '使用Vue构建单页应用', 'category' => '前端', 'tags' => ['JavaScript', 'Vue', 'SPA']],
            ['id' => 4, 'title' => 'MySQL性能优化技巧', 'category' => '数据库', 'tags' => ['MySQL', '优化', '数据库']],
            ['id' => 5, 'title' => '如何进行单元测试', 'category' => '测试', 'tags' => ['测试', 'PHPUnit', '单元测试']],
        ];

        // 创建搜索工具
        $searchTool = new class($database) extends AbstractTool {
            private array $database;

            public function __construct(array $database)
            {
                $this->database = $database;
            }

            protected function handle(array $parameters): array
            {
                $keyword = $parameters['keyword'] ?? '';
                $category = $parameters['category'] ?? null;
                $tag = $parameters['tag'] ?? null;
                $limit = $parameters['limit'] ?? 10;

                if (empty($keyword) && empty($category) && empty($tag)) {
                    return [
                        'error' => true,
                        'message' => '请至少提供一个搜索条件',
                    ];
                }

                $results = [];
                foreach ($this->database as $item) {
                    $match = true;

                    // 关键词搜索
                    if (! empty($keyword) && strpos(strtolower($item['title']), strtolower($keyword)) === false) {
                        $match = false;
                    }

                    // 分类过滤
                    if ($match && ! empty($category) && $item['category'] !== $category) {
                        $match = false;
                    }

                    // 标签过滤
                    if ($match && ! empty($tag) && ! in_array($tag, $item['tags'])) {
                        $match = false;
                    }

                    if ($match) {
                        $results[] = $item;
                    }

                    if (count($results) >= $limit) {
                        break;
                    }
                }

                return [
                    'keyword' => $keyword,
                    'category' => $category,
                    'tag' => $tag,
                    'count' => count($results),
                    'results' => $results,
                ];
            }
        };
        $searchTool->setName('search_tool');
        $searchTool->setDescription('搜索工具');
        $searchTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'keyword' => [
                    'type' => 'string',
                    'description' => '搜索关键词',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => '分类过滤',
                ],
                'tag' => [
                    'type' => 'string',
                    'description' => '标签过滤',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => '结果数量限制',
                    'default' => 10,
                ],
            ],
        ]));

        // 测试关键词搜索
        $result = $searchTool->run(['keyword' => 'PHP']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals('PHP', $result['keyword']);

        // 测试分类过滤
        $result = $searchTool->run(['category' => '编程']);
        $this->assertCount(2, $result['results']);

        // 测试标签过滤
        $result = $searchTool->run(['tag' => 'Vue']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals(3, $result['results'][0]['id']);

        // 测试组合搜索
        $result = $searchTool->run(['keyword' => 'PHP', 'category' => '编程']);
        $this->assertEquals(1, $result['count']);

        // 测试组合搜索 - 更具体
        $result = $searchTool->run(['keyword' => 'API', 'category' => '编程']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals(1, $result['results'][0]['id']);

        // 测试无结果搜索
        $result = $searchTool->run(['keyword' => '不存在的关键词']);
        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['results']);

        // 测试限制数量
        $result = $searchTool->run(['category' => '编程', 'limit' => 1]);
        $this->assertEquals(1, $result['count']);
    }

    /**
     * 测试数据转换工具实现.
     */
    public function testDataConversionTool(): void
    {
        // 创建数据转换工具
        $conversionTool = new class extends AbstractTool {
            protected function handle(array $parameters): array
            {
                $data = $parameters['data'] ?? null;
                $sourceFormat = $parameters['source_format'] ?? 'json';
                $targetFormat = $parameters['target_format'] ?? 'array';

                if (empty($data)) {
                    return [
                        'error' => true,
                        'message' => '数据不能为空',
                    ];
                }

                // 先将数据转换为PHP数组（中间格式）
                $phpArray = [];
                switch ($sourceFormat) {
                    case 'json':
                        if (! is_string($data)) {
                            return [
                                'error' => true,
                                'message' => 'JSON格式的数据必须是字符串',
                            ];
                        }
                        try {
                            $phpArray = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                        } catch (Exception $e) {
                            return [
                                'error' => true,
                                'message' => 'JSON解析错误: ' . $e->getMessage(),
                            ];
                        }
                        break;
                    case 'csv':
                        if (! is_string($data)) {
                            return [
                                'error' => true,
                                'message' => 'CSV格式的数据必须是字符串',
                            ];
                        }
                        $rows = explode("\n", trim($data));
                        if (empty($rows)) {
                            return [
                                'error' => true,
                                'message' => 'CSV数据格式错误',
                            ];
                        }
                        $headers = str_getcsv($rows[0]);
                        $phpArray = [];
                        for ($i = 1; $i < count($rows); ++$i) {
                            $row = str_getcsv($rows[$i]);
                            if (count($row) === count($headers)) {
                                $item = [];
                                foreach ($headers as $index => $header) {
                                    $item[$header] = $row[$index];
                                }
                                $phpArray[] = $item;
                            }
                        }
                        break;
                    case 'array':
                        if (! is_array($data)) {
                            return [
                                'error' => true,
                                'message' => '数据必须是数组',
                            ];
                        }
                        $phpArray = $data;
                        break;
                    default:
                        return [
                            'error' => true,
                            'message' => "不支持的源格式: {$sourceFormat}",
                        ];
                }

                // 从PHP数组转换为目标格式
                switch ($targetFormat) {
                    case 'json':
                        try {
                            $result = json_encode($phpArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                        } catch (Exception $e) {
                            return [
                                'error' => true,
                                'message' => 'JSON编码错误: ' . $e->getMessage(),
                            ];
                        }
                        break;
                    case 'csv':
                        if (empty($phpArray)) {
                            $result = '';
                            break;
                        }
                        $output = fopen('php://temp', 'r+');
                        // 获取列标题
                        $headers = array_keys(reset($phpArray));
                        fputcsv($output, $headers);
                        // 添加数据行
                        foreach ($phpArray as $row) {
                            fputcsv($output, $row);
                        }
                        rewind($output);
                        $result = stream_get_contents($output);
                        fclose($output);
                        break;
                    case 'array':
                        $result = $phpArray;
                        break;
                    default:
                        return [
                            'error' => true,
                            'message' => "不支持的目标格式: {$targetFormat}",
                        ];
                }

                return [
                    'source_format' => $sourceFormat,
                    'target_format' => $targetFormat,
                    'result' => $result,
                ];
            }
        };
        $conversionTool->setName('conversion_tool');
        $conversionTool->setDescription('数据格式转换工具');
        $conversionTool->setParameters(ToolParameters::fromArray([
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => ['string', 'object', 'array'],
                    'description' => '需要转换的数据',
                ],
                'source_format' => [
                    'type' => 'string',
                    'description' => '源数据格式',
                    'enum' => ['json', 'csv', 'array'],
                ],
                'target_format' => [
                    'type' => 'string',
                    'description' => '目标数据格式',
                    'enum' => ['json', 'csv', 'array'],
                ],
            ],
            'required' => ['data', 'source_format', 'target_format'],
        ]));

        // 测试 JSON 到数组的转换
        $jsonData = '{"name": "张三", "age": 30, "skills": ["PHP", "JavaScript"]}';
        $result = $conversionTool->run([
            'data' => $jsonData,
            'source_format' => 'json',
            'target_format' => 'array',
        ]);
        $this->assertEquals('json', $result['source_format']);
        $this->assertEquals('array', $result['target_format']);
        $this->assertEquals('张三', $result['result']['name']);
        $this->assertEquals(30, $result['result']['age']);
        $this->assertEquals(['PHP', 'JavaScript'], $result['result']['skills']);

        // 测试数组到 JSON 的转换
        $arrayData = [
            'name' => '李四',
            'age' => 25,
            'address' => '北京市朝阳区',
        ];
        $result = $conversionTool->run([
            'data' => $arrayData,
            'source_format' => 'array',
            'target_format' => 'json',
        ]);
        $this->assertEquals('array', $result['source_format']);
        $this->assertEquals('json', $result['target_format']);
        $decodedResult = json_decode($result['result'], true);
        $this->assertEquals('李四', $decodedResult['name']);
        $this->assertEquals(25, $decodedResult['age']);
        $this->assertEquals('北京市朝阳区', $decodedResult['address']);

        // 测试 CSV 到数组的转换
        $csvData = "name,age,city\n王五,40,上海\n赵六,35,广州";
        $result = $conversionTool->run([
            'data' => $csvData,
            'source_format' => 'csv',
            'target_format' => 'array',
        ]);
        $this->assertEquals('csv', $result['source_format']);
        $this->assertEquals('array', $result['target_format']);
        $this->assertCount(2, $result['result']);
        $this->assertEquals('王五', $result['result'][0]['name']);
        $this->assertEquals('40', $result['result'][0]['age']);
        $this->assertEquals('上海', $result['result'][0]['city']);

        // 测试错误处理 - 无效JSON
        $result = $conversionTool->run([
            'data' => '{无效的JSON',
            'source_format' => 'json',
            'target_format' => 'array',
        ]);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('JSON解析错误', $result['message']);
    }
}
