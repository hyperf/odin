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

use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\OpenAIModel;
use HyperfTest\Odin\Cases\AbstractTestCase;

use function Hyperf\Support\env;

/**
 * @internal
 * @coversNothing
 */
class OpenAIModelTest extends AbstractTestCase
{
    private array $config;

    private string $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = env('OPENAI_MODEL');
        $this->config = [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_HOST'),
        ];
    }

    public function testChat()
    {
        $this->markTestSkipped('Difficulties to mock');

        $model = new OpenAIModel($this->model, $this->config);

        $messages = [
            new SystemMessage(''),
            new UserMessage('hello'),
        ];
        $response = $model->chat($messages);
        var_dump($response->getFirstChoice()->getMessage()->getContent());
        $this->assertNotEmpty($response->getFirstChoice()->getMessage()->getContent());
    }

    public function testChatStream()
    {
        $this->markTestSkipped('Difficulties to mock');

        $model = new OpenAIModel($this->model, $this->config);

        $messages = [
            new SystemMessage(''),
            new UserMessage('hello'),
        ];
        $response = $model->chat($messages, stream: true);
        $this->assertTrue($response->isChunked());
        $content = '';
        foreach ($response->getStreamIterator() as $choice) {
            $content .= $choice->getMessage()?->getContent() ?: '';
        }
        var_dump($content);
        $this->assertNotEmpty($content);
    }
}
