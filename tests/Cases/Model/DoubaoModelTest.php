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

use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\DoubaoModel;
use HyperfTest\Odin\Cases\AbstractTestCase;

use function Hyperf\Support\env;

/**
 * @internal
 * @coversNothing
 */
class DoubaoModelTest extends AbstractTestCase
{
    private array $config;

    private string $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = env('SKYLARK_PRO_32K_ENDPOINT');
        $this->config = [
            'api_key' => env('SKYLARK_API_KEY'),
            'base_url' => env('SKYLARK_HOST'),
            'model' => env('SKYLARK_PRO_32K_ENDPOINT'),
        ];
    }

    public function testChat()
    {
        $this->markTestSkipped('Difficulties to mock');

        $skylarkModel = new DoubaoModel($this->model, $this->config);

        $messages = [
            new SystemMessage(''),
            new UserMessage('hello'),
        ];
        $response = $skylarkModel->chat($messages);
        var_dump($response->getFirstChoice()->getMessage()->getContent());
        $this->assertNotEmpty($response->getFirstChoice()->getMessage()->getContent());
    }

    public function testDeepSeek()
    {
        $this->markTestSkipped('Difficulties to mock');

        $model = new DoubaoModel(
            env('DEEPSPEEK_R1_ENDPOINT'),
            [
                'api_key' => env('SKYLARK_API_KEY'),
                'base_url' => env('SKYLARK_HOST'),
                'model' => env('DEEPSPEEK_R1_ENDPOINT'), ]
        );

        $messages = [
            new SystemMessage(''),
            new UserMessage('你知道海龟汤是什么吗'),
        ];
        $response = $model->chat($messages);
        var_dump($response->getFirstChoice()->getMessage());

        /** @var AssistantMessage $message */
        $message = $response->getFirstChoice()->getMessage();
        $this->assertNotEmpty($message->getReasoningContent());
        $this->assertNotEmpty($message->getContent());
    }

    public function testChatStream()
    {
        $this->markTestSkipped('Difficulties to mock');

        $skylarkModel = new DoubaoModel($this->model, $this->config);

        $messages = [
            new SystemMessage(''),
            new UserMessage('hello'),
        ];
        $response = $skylarkModel->chat($messages, stream: true);
        $this->assertTrue($response->isChunked());
        $content = '';
        foreach ($response->getStreamIterator() as $choice) {
            $content .= $choice->getMessage()?->getContent() ?: '';
        }
        var_dump($content);
        $this->assertNotEmpty($content);
    }
}
