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
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Logger;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\GeminiModel;
use Hyperf\Odin\Model\ModelOptions;
use Hyperf\Odin\Tool\Definition\ToolDefinition;
use Hyperf\Odin\Tool\Definition\ToolParameters;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

$model = new GeminiModel(
    'gemini-3-pro-preview',
    [
        'api_key' => env('GOOGLE_GEMINI_API_KEY'),
        'base_url' => env('GOOGLE_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],
    new Logger(),
);
$model->setModelOptions(new ModelOptions([
    'function_call' => true,
]));
$model->setApiRequestOptions(new ApiOptions([
    // Add proxy if needed
    'proxy' => env('HTTP_CLIENT_PROXY'),
]));

$mindTool = new ToolDefinition(
    name: 'generate-mind-map',
    description: 'MCP server [Xmind 思维导图]',
    parameters: ToolParameters::fromArray(json_decode(
        <<<'JSON'
{
    "type": "object",
    "properties": {
        "output_file_path": {
            "default": "",
            "description": "工具结果输出到文件的路径，最好具有优雅的目录结构，文件必须是 json 格式。用于将工具的执行结果保存到指定文件中，避免大结果输出。建议在需要保留详细执行结果或结果可能很大时使用此参数，如 mysql 没有使用 WHERE 或 LIMIT 时可能返回上万行数据、查询文章详情等。如果不指定（为空），但工具结果很大时，系统会自动保存结果到工作区下。",
            "type": "string"
        },
        "topics": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "title": {
                        "type": "string",
                        "description": "The title of the topic"
                    },
                    "ref": {
                        "type": "string",
                        "description": "Optional reference ID for the topic"
                    },
                    "note": {
                        "type": "string",
                        "description": "Optional note for the topic"
                    },
                    "labels": {
                        "type": "array",
                        "items": {
                            "type": "string"
                        },
                        "description": "Optional array of labels for the topic"
                    },
                    "markers": {
                        "type": "array",
                        "items": {
                            "type": "string"
                        },
                        "description": "Optional array of markers for the topic (format: \"Category.name\", e.g., \"Arrow.refresh\")"
                    },
                    "children": {
                        "type": "array",
                        "items": {
                            "$ref": "#/properties/topics/items"
                        },
                        "description": "Optional array of child topics"
                    },
                    "relationships": {
                        "type": "array",
                        "items": {
                            "type": "object",
                            "properties": {
                                "title": {
                                    "type": "string",
                                    "description": "The title of the relationship"
                                },
                                "from": {
                                    "type": "string",
                                    "description": "The reference ID of the source topic"
                                },
                                "to": {
                                    "type": "string",
                                    "description": "The reference ID of the target topic"
                                }
                            },
                            "required": [
                                "title",
                                "from",
                                "to"
                            ],
                            "additionalProperties": false
                        },
                        "description": "Optional array of relationships for the topic"
                    }
                },
                "required": [
                    "title"
                ],
                "additionalProperties": false
            },
            "description": "Array of topics to include in the mind map"
        },
        "filename": {
            "type": "string",
            "description": "The filename for the XMind file (without path or extension)"
        },
        "outputPath": {
            "type": "string",
            "description": "Optional custom output path for the XMind file. If not provided, the file will be created in the temporary directory."
        },
        "relationships": {
            "type": "array",
            "items": {
                "$ref": "#/properties/topics/items/properties/relationships/items"
            },
            "description": "Optional array of relationships between topics"
        },
        "title": {
            "type": "string",
            "description": "The title of the mind map (root topic)"
        }
    },
    "required": [
        "title",
        "topics",
        "filename"
    ]
}
JSON,
        true
    )),
    toolHandler: function ($params) {
        return ['success' => 'ok'];
    }
);

$toolMessages = [
    new SystemMessage('你是一位 mind 高手。'),
    new UserMessage('使用 generate-mind-map 生成一份简单的 mind。'),
];

$start = microtime(true);

// Use tool for API call
$response = $model->chat($toolMessages, 0.7, 0, [], [$mindTool]);

// Output complete response
$message = $response->getFirstChoice()->getMessage();
echo $message;
echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;
