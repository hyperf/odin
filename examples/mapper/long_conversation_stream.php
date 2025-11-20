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
! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 2));

require_once dirname(__FILE__, 3) . '/vendor/autoload.php';

use Hyperf\Context\ApplicationContext;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Odin\Agent\Tool\ToolUseAgent;
use Hyperf\Odin\Api\Response\ChatCompletionChoice;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Memory\MemoryManager;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\ModelMapper;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

ClassLoader::init();
$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建日志记录器
$logger = new Logger();

// 初始化模型（通过 ModelMapper，模型配置在配置文件中）
$modelId = \Hyperf\Support\env('MODEL_MAPPER_TEST_MODEL_ID', '');
$modelMapper = $container->get(ModelMapper::class);
$model = $modelMapper->getModel($modelId);

// 定义系统消息（真实、详细的系统提示词，确保达到缓存阈值）
$systemPrompt = '你是一位资深的AI技术顾问和问题解决专家，拥有超过10年的软件开发和人工智能领域经验。你的专业领域包括但不限于：机器学习、深度学习、自然语言处理、计算机视觉、软件架构设计、系统优化、性能调优、代码审查、技术选型、团队协作和项目管理。

## 核心能力
1. **技术咨询**：能够深入分析技术问题，提供多角度的解决方案，并评估各种方案的优缺点。
2. **代码审查**：具备敏锐的代码嗅觉，能够识别潜在的性能问题、安全漏洞和设计缺陷。
3. **架构设计**：擅长设计可扩展、可维护、高性能的系统架构，熟悉微服务、分布式系统、云原生架构等。
4. **问题诊断**：能够快速定位复杂技术问题的根本原因，并提供系统性的解决方案。
5. **知识传递**：善于用通俗易懂的语言解释复杂的技术概念，帮助团队成员提升技术水平。

## 工作原则
- **准确性优先**：确保提供的信息准确可靠，对于不确定的内容会明确说明。
- **深入思考**：在回答问题前会充分思考，考虑各种可能性和边界情况。
- **实用导向**：提供的建议和方案都基于实际项目经验，具有可操作性。
- **持续学习**：保持对新技术和行业趋势的关注，不断更新知识库。
- **用户友好**：用清晰、结构化的方式组织回答，便于理解和执行。

## 回答风格
- 使用结构化的格式（如列表、代码块、表格）来组织信息。
- 提供具体的代码示例和最佳实践。
- 解释技术决策背后的原因和考量。
- 在适当的时候提供相关的参考资料和延伸阅读。
- 对于复杂问题，会分步骤详细说明。

## 专业领域深度
在机器学习领域，你熟悉监督学习、无监督学习、强化学习等各类算法，了解神经网络、决策树、支持向量机、聚类算法等的原理和应用场景。在深度学习方面，你精通卷积神经网络、循环神经网络、Transformer架构、注意力机制等前沿技术。

在软件工程方面，你熟悉敏捷开发、DevOps、CI/CD、容器化、Kubernetes、服务网格等现代软件开发实践。你了解各种编程语言的特性和适用场景，包括Python、Java、Go、Rust、JavaScript等。

在系统设计方面，你能够设计高可用、高并发、低延迟的分布式系统，熟悉负载均衡、缓存策略、数据库优化、消息队列、分布式事务等技术。

请始终以专业、负责、友好的态度回答用户的问题，帮助用户解决实际的技术挑战。当需要使用工具时，请明确指出工具的作用和使用步骤。';

// 初始化内存管理器
$memory = new MemoryManager();
$memory->addSystemMessage(new SystemMessage($systemPrompt));

// 定义工具 - 代码分析工具
$codeAnalyzerTool = new ToolDefinition(
    name: 'code_analyzer',
    description: '分析代码质量，检测潜在的性能问题、安全漏洞和设计缺陷',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'code' => [
                'type' => 'string',
                'description' => '要分析的代码片段',
            ],
            'language' => [
                'type' => 'string',
                'enum' => ['python', 'java', 'javascript', 'php', 'go', 'rust'],
                'description' => '编程语言',
            ],
            'analysis_type' => [
                'type' => 'string',
                'enum' => ['performance', 'security', 'design', 'all'],
                'description' => '分析类型：性能、安全、设计或全部',
                'default' => 'all',
            ],
        ],
        'required' => ['code', 'language'],
    ]),
    toolHandler: function ($params) {
        $code = $params['code'];
        $language = $params['language'];
        $analysisType = $params['analysis_type'] ?? 'all';

        // 模拟代码分析结果
        $issues = [];

        if ($analysisType === 'all' || $analysisType === 'performance') {
            $issues[] = [
                'type' => 'performance',
                'severity' => 'medium',
                'message' => '检测到可能的性能问题：循环中频繁字符串拼接',
                'suggestion' => '考虑使用 StringBuilder 或类似机制优化',
            ];
        }

        if ($analysisType === 'all' || $analysisType === 'security') {
            $issues[] = [
                'type' => 'security',
                'severity' => 'high',
                'message' => '检测到潜在的安全漏洞：SQL注入风险',
                'suggestion' => '使用参数化查询或ORM框架',
            ];
        }

        if ($analysisType === 'all' || $analysisType === 'design') {
            $issues[] = [
                'type' => 'design',
                'severity' => 'low',
                'message' => '设计建议：考虑使用设计模式提高代码可维护性',
                'suggestion' => '可以引入策略模式或工厂模式',
            ];
        }

        return [
            'language' => $language,
            'analysis_type' => $analysisType,
            'issues_found' => count($issues),
            'issues' => $issues,
            'score' => 75,
        ];
    }
);

// 定义工具 - 技术选型建议工具
$techSelectionTool = new ToolDefinition(
    name: 'tech_selection',
    description: '根据项目需求提供技术选型建议，包括框架、库、工具等的推荐',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'project_type' => [
                'type' => 'string',
                'enum' => ['web', 'mobile', 'api', 'microservice', 'data_processing', 'ml'],
                'description' => '项目类型',
            ],
            'requirements' => [
                'type' => 'string',
                'description' => '项目需求和约束条件，如性能要求、团队规模、预算等',
            ],
            'preferred_language' => [
                'type' => 'string',
                'enum' => ['python', 'java', 'javascript', 'php', 'go', 'rust', 'any'],
                'description' => '首选编程语言，或 any 表示不限',
                'default' => 'any',
            ],
        ],
        'required' => ['project_type', 'requirements'],
    ]),
    toolHandler: function ($params) {
        $projectType = $params['project_type'];
        $requirements = $params['requirements'];
        $preferredLanguage = $params['preferred_language'] ?? 'any';

        // 模拟技术选型建议
        $recommendations = [
            'web' => [
                'framework' => 'React/Vue.js',
                'backend' => 'Node.js/Express 或 Python/Django',
                'database' => 'PostgreSQL + Redis',
                'deployment' => 'Docker + Kubernetes',
            ],
            'api' => [
                'framework' => 'FastAPI (Python) 或 Spring Boot (Java)',
                'database' => 'PostgreSQL',
                'cache' => 'Redis',
                'message_queue' => 'RabbitMQ 或 Kafka',
            ],
            'microservice' => [
                'framework' => 'Go/Gin 或 Java/Spring Cloud',
                'service_mesh' => 'Istio',
                'registry' => 'Consul 或 Eureka',
                'gateway' => 'Kong 或 Zuul',
            ],
        ];

        $baseRecommendations = $recommendations[$projectType] ?? [
            'framework' => '根据具体需求选择',
            'database' => 'PostgreSQL',
        ];

        return [
            'project_type' => $projectType,
            'recommendations' => $baseRecommendations,
            'reasoning' => "基于项目类型 {$projectType} 和需求 {$requirements} 的推荐",
            'alternatives' => [
                '如果团队熟悉 Java，可以考虑 Spring Boot',
                '如果追求极致性能，可以考虑 Go 或 Rust',
            ],
        ];
    }
);

// 定义工具 - 性能优化建议工具
$performanceOptimizerTool = new ToolDefinition(
    name: 'performance_optimizer',
    description: '提供系统性能优化建议，包括数据库优化、缓存策略、代码优化等',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'component' => [
                'type' => 'string',
                'enum' => ['database', 'cache', 'api', 'frontend', 'infrastructure'],
                'description' => '需要优化的组件',
            ],
            'current_metrics' => [
                'type' => 'string',
                'description' => '当前性能指标，如响应时间、吞吐量、错误率等',
            ],
            'target_metrics' => [
                'type' => 'string',
                'description' => '目标性能指标',
            ],
        ],
        'required' => ['component', 'current_metrics'],
    ]),
    toolHandler: function ($params) {
        $component = $params['component'];
        $currentMetrics = $params['current_metrics'];
        $targetMetrics = $params['target_metrics'] ?? '';

        // 模拟性能优化建议
        $optimizations = [
            'database' => [
                '添加适当的索引',
                '优化查询语句，避免全表扫描',
                '考虑使用读写分离',
                '实施连接池管理',
                '定期进行数据库维护和清理',
            ],
            'cache' => [
                '实施多级缓存策略（L1/L2/L3）',
                '设置合理的缓存过期时间',
                '使用缓存预热机制',
                '监控缓存命中率',
                '考虑使用分布式缓存',
            ],
            'api' => [
                '实施请求限流和熔断',
                '使用异步处理非关键路径',
                '优化序列化/反序列化',
                '实施API版本控制',
                '使用CDN加速静态资源',
            ],
        ];

        return [
            'component' => $component,
            'current_metrics' => $currentMetrics,
            'target_metrics' => $targetMetrics,
            'optimizations' => $optimizations[$component] ?? ['根据具体情况分析'],
            'priority' => 'high',
            'estimated_impact' => '预计可提升性能 30-50%',
        ];
    }
);

// 定义工具 - 架构评估工具
$architectureEvaluatorTool = new ToolDefinition(
    name: 'architecture_evaluator',
    description: '评估系统架构设计，提供可扩展性、可维护性、可靠性等方面的建议',
    parameters: ToolParameters::fromArray([
        'type' => 'object',
        'properties' => [
            'architecture_type' => [
                'type' => 'string',
                'enum' => ['monolith', 'microservices', 'serverless', 'event_driven', 'layered'],
                'description' => '架构类型',
            ],
            'scale_requirement' => [
                'type' => 'string',
                'description' => '规模要求，如用户量、并发量、数据量等',
            ],
            'team_size' => [
                'type' => 'integer',
                'description' => '团队规模',
            ],
        ],
        'required' => ['architecture_type', 'scale_requirement'],
    ]),
    toolHandler: function ($params) {
        $architectureType = $params['architecture_type'];
        $scaleRequirement = $params['scale_requirement'];
        $teamSize = $params['team_size'] ?? 5;

        // 模拟架构评估结果
        return [
            'architecture_type' => $architectureType,
            'scalability_score' => 85,
            'maintainability_score' => 80,
            'reliability_score' => 90,
            'cost_score' => 75,
            'recommendations' => [
                '考虑引入服务网格以提高可观测性',
                '实施完善的监控和告警机制',
                '建立清晰的API契约和版本管理策略',
                '考虑使用事件驱动架构提高解耦度',
            ],
            'risks' => [
                '分布式事务管理复杂度较高',
                '需要完善的DevOps基础设施',
                '团队需要具备微服务开发经验',
            ],
        ];
    }
);

// 创建带有所有工具的代理
$agent = new ToolUseAgent(
    model: $model,
    memory: $memory,
    tools: [
        $codeAnalyzerTool->getName() => $codeAnalyzerTool,
        $techSelectionTool->getName() => $techSelectionTool,
        $performanceOptimizerTool->getName() => $performanceOptimizerTool,
        $architectureEvaluatorTool->getName() => $architectureEvaluatorTool,
    ],
    temperature: 0.6,
    logger: $logger
);

// 第一轮对话 - 创建缓存（流式）
echo "===== 第一轮对话（创建缓存 - 流式）=====\n";
$start1 = microtime(true);

$userMessage1 = new UserMessage('我需要构建一个高并发的API服务，预计日活用户100万，请帮我分析一下技术选型，并评估一下微服务架构是否适合。');
$response1 = $agent->chatStreamed($userMessage1);

$content1 = '';
/** @var ChatCompletionChoice $choice */
foreach ($response1 as $choice) {
    $delta = $choice->getMessage()->getContent();
    if ($delta !== null) {
        echo $delta;
        $content1 .= $delta;
    }
}
$duration1 = microtime(true) - $start1;

// 流式响应完成后，尝试获取 usage 信息
$usage1 = null;
if (method_exists($response1, 'getUsage')) {
    $usage1 = $response1->getUsage();
}
$inputTokens1 = $usage1?->getPromptTokens() ?? 0;
$outputTokens1 = $usage1?->getCompletionTokens() ?? 0;
$totalTokens1 = $usage1?->getTotalTokens() ?? 0;
$promptDetails1 = $usage1?->getPromptTokensDetails() ?? [];

echo "\n耗时: {$duration1} 秒\n";
if ($inputTokens1 > 0) {
    echo "Input Tokens: {$inputTokens1}, Output Tokens: {$outputTokens1}, Total Tokens: {$totalTokens1}\n";
} else {
    echo "Tokens: N/A (流式响应中 usage 信息可能不可用)\n";
}
echo "\n";

// 第二轮对话 - 使用缓存（对话连续，流式）
echo "===== 第二轮对话（使用缓存 - 流式）=====\n";
$start2 = microtime(true);

$userMessage2 = new UserMessage('基于刚才的建议，如果选择微服务架构，那么数据库应该如何设计？请分析一下性能优化方案。');
$response2 = $agent->chatStreamed($userMessage2);

$content2 = '';
/** @var ChatCompletionChoice $choice */
foreach ($response2 as $choice) {
    $delta = $choice->getMessage()->getContent();
    if ($delta !== null) {
        echo $delta;
        $content2 .= $delta;
    }
}
$duration2 = microtime(true) - $start2;

$usage2 = null;
if (method_exists($response2, 'getUsage')) {
    $usage2 = $response2->getUsage();
}
$inputTokens2 = $usage2?->getPromptTokens() ?? 0;
$outputTokens2 = $usage2?->getCompletionTokens() ?? 0;
$totalTokens2 = $usage2?->getTotalTokens() ?? 0;
$promptDetails2 = $usage2?->getPromptTokensDetails() ?? [];

echo "\n耗时: {$duration2} 秒\n";
if ($inputTokens2 > 0) {
    echo "Input Tokens: {$inputTokens2}, Output Tokens: {$outputTokens2}, Total Tokens: {$totalTokens2}\n";
} else {
    echo "Tokens: N/A (流式响应中 usage 信息可能不可用)\n";
}
echo "\n";

// 第三轮对话 - 继续使用缓存（对话连续，流式）
echo "===== 第三轮对话（继续使用缓存 - 流式）=====\n";
$start3 = microtime(true);

$userMessage3 = new UserMessage('很好，现在请帮我分析一下这段代码的性能问题：function processData(data) { let result = ""; for (let i = 0; i < data.length; i++) { result += data[i]; } return result; }');
$response3 = $agent->chatStreamed($userMessage3);

$content3 = '';
/** @var ChatCompletionChoice $choice */
foreach ($response3 as $choice) {
    $delta = $choice->getMessage()->getContent();
    if ($delta !== null) {
        echo $delta;
        $content3 .= $delta;
    }
}
$duration3 = microtime(true) - $start3;

$usage3 = null;
if (method_exists($response3, 'getUsage')) {
    $usage3 = $response3->getUsage();
}
$inputTokens3 = $usage3?->getPromptTokens() ?? 0;
$outputTokens3 = $usage3?->getCompletionTokens() ?? 0;
$totalTokens3 = $usage3?->getTotalTokens() ?? 0;
$promptDetails3 = $usage3?->getPromptTokensDetails() ?? [];

echo "\n耗时: {$duration3} 秒\n";
if ($inputTokens3 > 0) {
    echo "Input Tokens: {$inputTokens3}, Output Tokens: {$outputTokens3}, Total Tokens: {$totalTokens3}\n";
} else {
    echo "Tokens: N/A (流式响应中 usage 信息可能不可用)\n";
}
echo "\n";

// 总结
echo "===== 缓存效果总结 =====\n";
echo "第一轮（创建缓存）: {$duration1} 秒";
if ($inputTokens1 > 0) {
    echo ", Input Tokens: {$inputTokens1}";
}
echo "\n";
echo "第二轮（使用缓存）: {$duration2} 秒";
if ($inputTokens2 > 0) {
    echo ", Input Tokens: {$inputTokens2}";
}
echo "\n";
echo "第三轮（使用缓存）: {$duration3} 秒";
if ($inputTokens3 > 0) {
    echo ", Input Tokens: {$inputTokens3}";
}
echo "\n\n";

// 分析缓存命中情况（仅在 usage 信息可用时）
if ($inputTokens1 > 0 && ($inputTokens2 > 0 || $inputTokens3 > 0)) {
    echo "===== 缓存命中分析 =====\n";

    // 检查是否有缓存相关的详细信息
    $cacheReadTokens2 = $promptDetails2['cache_read_input_tokens'] ?? $promptDetails2['cached_tokens'] ?? null;
    $cacheReadTokens3 = $promptDetails3['cache_read_input_tokens'] ?? $promptDetails3['cached_tokens'] ?? null;

    if ($cacheReadTokens2 !== null || $cacheReadTokens3 !== null) {
        // 如果有明确的缓存命中信息
        if ($cacheReadTokens2 !== null && $cacheReadTokens2 > 0) {
            echo "第二轮缓存命中: {$cacheReadTokens2} tokens 从缓存读取\n";
        } else {
            echo "第二轮缓存命中: 未命中\n";
        }

        if ($cacheReadTokens3 !== null && $cacheReadTokens3 > 0) {
            echo "第三轮缓存命中: {$cacheReadTokens3} tokens 从缓存读取\n";
        } else {
            echo "第三轮缓存命中: 未命中\n";
        }
    } else {
        // 通过比较 input tokens 来判断缓存命中
        if ($inputTokens1 > 0 && $inputTokens2 > 0) {
            $reduction2 = (($inputTokens1 - $inputTokens2) / $inputTokens1) * 100;
            if ($inputTokens2 < $inputTokens1 * 0.8) {
                $savedTokens2 = $inputTokens1 - $inputTokens2;
                echo "第二轮缓存命中: 通过 Input Tokens 减少判断，节省了 {$savedTokens2} tokens (" . number_format($reduction2, 2) . "%)\n";
            } else {
                echo '第二轮缓存命中: 未命中（Input Tokens 变化: ' . number_format($reduction2, 2) . "%）\n";
            }
        }

        if ($inputTokens1 > 0 && $inputTokens3 > 0) {
            $reduction3 = (($inputTokens1 - $inputTokens3) / $inputTokens1) * 100;
            if ($inputTokens3 < $inputTokens1 * 0.8) {
                $savedTokens3 = $inputTokens1 - $inputTokens3;
                echo "第三轮缓存命中: 通过 Input Tokens 减少判断，节省了 {$savedTokens3} tokens (" . number_format($reduction3, 2) . "%)\n";
            } else {
                echo '第三轮缓存命中: 未命中（Input Tokens 变化: ' . number_format($reduction3, 2) . "%）\n";
            }
        }
    }
    echo "\n";
}

// 性能对比
if ($duration1 > 0) {
    $speedup2 = (($duration1 - $duration2) / $duration1) * 100;
    $speedup3 = (($duration1 - $duration3) / $duration1) * 100;
    echo "===== 性能对比 =====\n";
    echo '第二轮相比第一轮加速: ' . number_format($speedup2, 2) . "%\n";
    echo '第三轮相比第一轮加速: ' . number_format($speedup3, 2) . "%\n";
}
