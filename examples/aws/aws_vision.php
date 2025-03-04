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
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Message\UserMessageContent;
use Hyperf\Odin\Model\AwsBedrockModel;
use Hyperf\Odin\Model\ModelOptions;

use function Hyperf\Support\env;

ClassLoader::init();

$container = ApplicationContext::setContainer(new Container((new DefinitionSourceFactory())()));

// 创建 AWS Bedrock 模型实例
// 使用 Claude 3 Sonnet 模型 ID
$model = new AwsBedrockModel(
    'us.anthropic.claude-3-7-sonnet-20250219-v1:0',
    [
        'access_key' => env('AWS_ACCESS_KEY'),
        'secret_key' => env('AWS_SECRET_KEY'),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],
    new Logger(),
);
$model->setModelOptions(new ModelOptions([
    'multi_modal' => true,
]));
$model->setApiRequestOptions(new ApiOptions([
    // 如果你的环境不需要代码，那就不用
    'proxy' => env('HTTP_CLIENT_PROXY'),
]));

echo '=== AWS Bedrock Claude 多模态测试 ===' . PHP_EOL;
echo '支持图像分析功能' . PHP_EOL . PHP_EOL;

// 使用本地文件测试并转换为 base64
$imagePath = __DIR__ . '/vision_test.jpeg'; // 替换为实际图像路径
if (file_exists($imagePath)) {
    // 图像存在，进行测试
    $imageData = file_get_contents($imagePath);
    $base64Image = base64_encode($imageData);
    $imageType = mime_content_type($imagePath);
    $dataUrl = "data:{$imageType};base64,{$base64Image}";

    echo '已将图像转换为 base64 格式' . PHP_EOL;

    // 创建包含图像的消息
    $userMessage = new UserMessage();
    $userMessage->addContent(UserMessageContent::text('分析一下这张图片里有什么内容？什么颜色最多？'));
    $userMessage->addContent(UserMessageContent::imageUrl($dataUrl));

    $multiModalMessages = [
        new SystemMessage('你是一位专业的图像分析专家，请详细描述图像内容。'),
        $userMessage,
    ];

    $start = microtime(true);

    // 使用非流式API调用
    $response = $model->chat($multiModalMessages);

    // 输出完整响应
    $message = $response->getFirstChoice()->getMessage();
    if ($message instanceof AssistantMessage) {
        echo $message->getContent();
    }

    echo PHP_EOL;
    echo '耗时' . (microtime(true) - $start) . '秒' . PHP_EOL;
} else {
    echo "测试图像 {$imagePath} 不存在，跳过多模态测试" . PHP_EOL;
    echo '请在当前目录下放置一个名为 test_image.jpg 的测试图像文件' . PHP_EOL;
}

// 注意：AWS Bedrock Claude 要求图像必须是 base64 编码格式
echo PHP_EOL . '注意：此实现仅支持 base64 编码的图像，外部 URL 链接不被支持' . PHP_EOL;
echo '如需使用外部图像，请在应用层面先下载并转换为 base64 格式' . PHP_EOL;
