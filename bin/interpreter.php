<?php

use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAI;
use Hyperf\Odin\Apis\AzureOpenAI\AzureOpenAIConfig;
use Hyperf\Odin\Apis\AzureOpenAI\Client as AzureOpenAIClient;
use Hyperf\Odin\Apis\OpenAI\Client as OpenAIClient;
use Hyperf\Odin\Apis\OpenAI\OpenAI;
use Hyperf\Odin\Apis\OpenAI\OpenAIConfig;
use Hyperf\Odin\Apis\OpenAI\Response\ChatCompletionChoice;
use Hyperf\Odin\Interpreter\CodeRunner;
use Hyperf\Odin\Memory\AbstractMemory;
use Hyperf\Odin\Memory\MessageHistory;
use Hyperf\Odin\Message\AssistantMessage;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Prompt\InterpreterPromptTemplate;
use function Hyperf\Support\env as env;

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

\Hyperf\Di\ClassLoader::init();

class LLM
{

    public string $model = 'gpt-3.5-turbo';
    protected int $times = 0;

    public function chat(array $messages, float $temperature = 0.9, array $functions = []): string
    {
        $client = $this->getAzureOpenAIClient();
        $client->setDebug(false);
        $response = $client->chat($messages, $this->model, $temperature, 3000, [], $functions);
        $choice = $response->getChoices()[0];
        try {
            if ($choice instanceof ChatCompletionChoice && $choice->isFinishedByFunctionCall()) {
                $message = $choice->getMessage();
                if ($message instanceof AssistantMessage) {
                    echo 'AI: ' . $message->getContent() . PHP_EOL;
                    $functionCall = $message->getFunctionCall();
                    $functionName = $functionCall['name'];
                    $functionParameters = json_decode($functionCall['arguments'], true);
                    $functionCallResult = match ($functionName) {
                        'run_code' => function () use ($functions, $temperature, $messages, $functionParameters) {
                            if ($this->times > 3) {
                                return 'No result';
                            }
                            if (! isset($functionParameters['language']) || ! isset($functionParameters['code'])) {
                                $this->times++;
                                echo '[DEBUG] Invalid function parameters' . PHP_EOL;
                                return $this->chat($messages, $temperature, $functions);
                            }

                            $language = $functionParameters['language'];
                            $code = $functionParameters['code'];
                            return (new CodeRunner())->runCode($language, $code);
                        },
                    };
                    $result = $functionCallResult();
                    if (! $result) {
                        $result = 'No result';
                    }
                    $response = $result;
                }
            }
        } catch (\Throwable $e) {
            echo $e->getMessage() . PHP_EOL;
        }
        return $response;
    }

    function getOpenAIClient(): OpenAIClient
    {
        $openAI = new OpenAI();
        $config = new OpenAIConfig(env('OPENAI_API_KEY'),);
        return $openAI->getClient($config);
    }

    function getAzureOpenAIClient(): AzureOpenAIClient
    {
        $openAI = new AzureOpenAI();
        $config = new AzureOpenAIConfig(apiKey: env('AZURE_OPENAI_API_KEY'), baseUrl: env('AZURE_OPENAI_API_BASE'), apiVersion: env('AZURE_OPENAI_API_VERSION'), deploymentName: env('AZURE_OPENAI_DEPLOYMENT_NAME'),);
        return $openAI->getClient($config);
    }
}

function chat(string $message, AbstractMemory $memory = null, string $conversationId = null): string
{
    $name = get_current_user();
    $os = PHP_OS_FAMILY;
    $cwd = getcwd();
    $functions = [
        [
            'name' => 'run_code',
            'description' => 'Executes code and returns the output.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'language' => [
                        'type' => 'string',
                        'description' => 'The programming language',
                        'enum' => [
                            'php',
                            'shell'
                        ]
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'The code to execute',
                    ],
                ],
                'required' => [
                    'language',
                    'code'
                ],
            ],
        ]
    ];
    $llm = new LLM();
    if ($memory) {
        $message = $memory->buildPrompt($message, $conversationId);
    }
    $messages = [
        'system' => new SystemMessage((new InterpreterPromptTemplate())->buildSystemPrompt($name, $cwd, $os)),
        'user' => new UserMessage($message),
    ];
    var_dump($messages['user']->getContent());
    $response = $llm->chat($messages, temperature: 0, functions: $functions);
    $memory?->addHumanMessage($message, $conversationId);
    $memory?->addAIMessage($response, $conversationId);
    return $response;
}

$conversionId = uniqid();
$memory = new MessageHistory();
while (true) {
    echo 'Human: ';
    $input = trim(fgets(STDIN, 1024));
    $response = trim(chat($input, $memory, $conversionId));
    echo 'AI: ' . $response . PHP_EOL;
}