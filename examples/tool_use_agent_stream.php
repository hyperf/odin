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
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(__FILE__, 2) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Factory\ModelFactory;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\AzureOpenAIModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

// 初始化模型
$model = ModelFactory::create(
    implementation: AzureOpenAIModel::class,
    modelName: 'gpt-4o-global',
    config: [
        'api_key' => env('AZURE_OPENAI_4O_API_KEY'),
        'api_base' => env('AZURE_OPENAI_4O_API_BASE'),
        'api_version' => env('AZURE_OPENAI_4O_API_VERSION'),
        'deployment_name' => env('AZURE_OPENAI_4O_DEPLOYMENT_NAME'),
    ],
    modelOptions: ModelOptions::fromArray([
        'chat' => true,
        'function_call' => true,
        'embedding' => false,
        'multi_modal' => true,
        'vector_size' => 0,
    ]),
    apiOptions: ApiOptions::fromArray([
        'timeout' => [
            'connection' => 5.0,  // 连接超时（秒）
            'write' => 10.0,      // 写入超时（秒）
            'read' => 300.0,      // 读取超时（秒）
            'total' => 350.0,     // 总体超时（秒）
            'thinking' => 120.0,  // 思考超时（秒）
            'stream_chunk' => 30.0, // 流式块间超时（秒）
            'stream_first' => 60.0, // 首个流式块超时（秒）
        ],
        'custom_error_mapping_rules' => [],
    ]),
    logger: $logger
);

// 初始化内存管理器
$memory = new MemoryManager();
$memory->addSystemMessage(new SystemMessage('你是一个能够使用工具的AI助手，当需要使用工具时，请明确指出工具的作用和使用步骤。'));

// 定义多个工具
// 计算器工具
$calculatorTool = new ToolDefinition(
    name: 'calculator',
    description: '用于执行基本数学运算的计算器工具',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'operation' => [
                'type' => 'string',
                'enum' => ['add', 'subtract', 'multiply', 'divide', 'power'],
                'description' => '要执行的数学运算类型',
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
    ]),
    toolHandler: function ($params) {
        $a = $params['a'];
        $b = $params['b'];
        switch ($params['operation']) {
            case 'add':
                return ['result' => $a + $b];
            case 'subtract':
                return ['result' => $a - $b];
            case 'multiply':
                return ['result' => $a * $b];
            case 'divide':
                if ($b == 0) {
                    return ['error' => '除数不能为零'];
                }
                return ['result' => $a / $b];
            case 'power':
                return ['result' => pow($a, $b)];
            default:
                return ['error' => '未知操作'];
        }
    }
);

// 数据库查询工具 (模拟)
$databaseTool = new ToolDefinition(
    name: 'database',
    description: '查询数据库中的信息',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'table' => [
                'type' => 'string',
                'enum' => ['users', 'products', 'orders'],
                'description' => '要查询的数据表',
            ],
            'id' => [
                'type' => 'integer',
                'description' => '记录ID',
            ],
        ],
        'required' => ['table', 'id'],
    ]),
    toolHandler: function ($params) {
        $table = $params['table'];
        $id = $params['id'];

        // 模拟数据库表
        $database = [
            'users' => [
                1 => ['name' => '张三', 'age' => 28, 'email' => 'zhangsan@example.com'],
                2 => ['name' => '李四', 'age' => 32, 'email' => 'lisi@example.com'],
                3 => ['name' => '王五', 'age' => 45, 'email' => 'wangwu@example.com'],
            ],
            'products' => [
                1 => ['name' => '笔记本电脑', 'price' => 6999, 'stock' => 50],
                2 => ['name' => '智能手机', 'price' => 3999, 'stock' => 100],
                3 => ['name' => '平板电脑', 'price' => 2999, 'stock' => 75],
            ],
            'orders' => [
                1 => ['user_id' => 1, 'product_id' => 2, 'quantity' => 1, 'total' => 3999],
                2 => ['user_id' => 2, 'product_id' => 1, 'quantity' => 2, 'total' => 13998],
                3 => ['user_id' => 3, 'product_id' => 3, 'quantity' => 1, 'total' => 2999],
            ],
        ];

        if (isset($database[$table][$id])) {
            return ['data' => $database[$table][$id]];
        }

        return ['error' => "在表 {$table} 中未找到ID为 {$id} 的记录"];
    }
);

// 内容推荐工具 (模拟)
$recommendTool = new ToolDefinition(
    name: 'recommend',
    description: '根据用户偏好推荐内容',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'category' => [
                'type' => 'string',
                'enum' => ['电影', '书籍', '音乐', '餐厅'],
                'description' => '推荐类别',
            ],
            'user_preference' => [
                'type' => 'string',
                'description' => '用户偏好关键词',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => '返回推荐数量',
                'default' => 3,
            ],
        ],
        'required' => ['category', 'user_preference'],
    ]),
    toolHandler: function ($params) {
        $category = $params['category'];
        $preference = $params['user_preference'];
        $limit = $params['limit'] ?? 3;

        // 模拟推荐系统
        $recommendations = [
            '电影' => [
                '科幻' => ['星际穿越', '银翼杀手2049', '头号玩家', '火星救援', '黑客帝国'],
                '动作' => ['速度与激情', '碟中谍', '复仇者联盟', '黑暗骑士', '007:幽灵党'],
                '剧情' => ['肖申克的救赎', '阿甘正传', '当幸福来敲门', '楚门的世界', '绿皮书'],
            ],
            '书籍' => [
                '科幻' => ['三体', '基地', '沙丘', '神经漫游者', '火星救援'],
                '小说' => ['百年孤独', '追风筝的人', '活着', '围城', '平凡的世界'],
                '历史' => ['人类简史', '枪炮、病菌与钢铁', '第三帝国的兴亡', '明朝那些事', '万历十五年'],
            ],
            '音乐' => [
                '流行' => ['Bad Guy - Billie Eilish', 'Blinding Lights - The Weeknd', '起风了 - 买辣椒也用券', '锦鲤 - 王俊凯', 'Dynamite - BTS'],
                '摇滚' => ['Numb - Linkin Park', 'Yellow - Coldplay', '不再犹豫 - Beyond', '光辉岁月 - Beyond', 'Bohemian Rhapsody - Queen'],
                '古典' => ['月光奏鸣曲 - 贝多芬', '四季 - 维瓦尔第', '土耳其进行曲 - 莫扎特', '命运交响曲 - 贝多芬', '天鹅湖 - 柴可夫斯基'],
            ],
            '餐厅' => [
                '中餐' => ['鼎泰丰', '外婆家', '海底捞', '眉州东坡', '小龙坎'],
                '西餐' => ['必胜客', '麦当劳', '汉堡王', '赛百味', 'KFC'],
                '日料' => ['吉野家', '松屋', '味千拉面', '寿司郎', '大渔铁板烧'],
            ],
        ];

        $result = [];
        if (isset($recommendations[$category])) {
            foreach ($recommendations[$category] as $key => $items) {
                // 简单模拟：如果偏好词是分类的子集，或者分类是偏好词的子集，就认为匹配
                if (str_contains($key, $preference) || str_contains($preference, $key)) {
                    $result = array_slice($items, 0, $limit);
                    break;
                }
            }

            // 如果没有匹配到分类，返回第一个分类的推荐
            if (empty($result)) {
                $firstCategory = array_key_first($recommendations[$category]);
                $result = array_slice($recommendations[$category][$firstCategory], 0, $limit);
            }

            return ['recommendations' => $result];
        }

        return ['error' => "不支持的推荐类别: {$category}"];
    }
);

// 创建带有所有工具的代理
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        $calculatorTool->getName() => $calculatorTool,
        $databaseTool->getName() => $databaseTool,
        $recommendTool->getName() => $recommendTool,
    ],
    temperature: 0.6,
    logger: $logger
);

// 顺序流式调用示例
echo "===== 顺序流式工具调用示例 =====\n";
$start = microtime(true);

$userMessage = new UserMessage('请计算 7 的 3 次方，然后查询用户ID为2的信息，最后根据查询结果推荐一些科幻电影。请详细说明每一步。');
$response = $agent->chatStreamed($userMessage);

$content = '';
/** @var ChatCompletionChoice $choice */
foreach ($response as $choice) {
    $delta = $choice->getMessage()->getContent();
    if ($delta !== null) {
        echo $delta;
        $content .= $delta;
    }
}

echo "\n";
echo '顺序流式调用耗时：' . (microtime(true) - $start) . '秒' . PHP_EOL;
