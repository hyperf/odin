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

namespace Hyperf\Odin\Knowledge;

use Hyperf\Odin\Document\Document;
use Hyperf\Odin\Message\SystemMessage;
use Hyperf\Odin\Message\UserMessage;
use Hyperf\Odin\Model\EmbeddingInterface;
use Hyperf\Odin\Model\ModelInterface;
use Hyperf\Odin\Prompt\Prompt;
use Hyperf\Odin\VectorStore\Qdrant\Qdrant;
use Hyperf\Qdrant\Struct\Points\Point\Record;
use Hyperf\Qdrant\Struct\UpdateResult;
use RuntimeException;

class Knowledge
{
    public $vectorSizeMap
        = [
            'qwen:32b-chat' => 5120,
            'text-embedding-ada-002' => 1536,
        ];

    public function __construct(
        public EmbeddingInterface $embeddingModel,
        protected Qdrant $qdrant,
        public ?ModelInterface $llm = null
    ) {
    }

    public function similaritySearch(string $query, string $collection, int $limit = 5, float $score = 0.1): array
    {
        return $this->qdrant->search(query: $query, collectionName: $collection, model: $this->embeddingModel, limit: $limit, score: $score);
    }

    public function upsert(string $collection, Document $document): ?UpdateResult
    {
        $this->qdrant->getOrCreateCollection($collection, $this->vectorSizeMap[$this->embeddingModel->getModelName()]);
        return $this->qdrant->upsertPointsByDocument($collection, $document, $this->embeddingModel);
    }

    public function autoQA(string $fromCollectionName, string $toCollectionName, Document $document)
    {
        if (! $this->llm) {
            throw new RuntimeException('LLM model is required for auto QA.');
        }
        $points = $this->qdrant->scrollPoints($fromCollectionName);
        foreach ($points['points'] as $point) {
            if (! $point instanceof Record) {
                continue;
            }
            $messages = [
                new SystemMessage(Prompt::getPrompt('KnowledgeAutoQA')),
                new UserMessage($point->payload['__content__']),
            ];
            $response = $this->llm->chat($messages);
            $qas = [];
            $assistantMessage = $response->getFirstChoice()->getMessage()->getContent();
            // Extract ```json ``` content
            preg_match_all('/```json(.*?)```/s', $assistantMessage, $matches);
            foreach ($matches[1] as $match) {
                $json = json_decode($match, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $qas = $json;
                }
            }
            $docQas = '';
            foreach ($qas as $key => $qa) {
                $qas[$key] = [
                    'question' => $qa['q'],
                    'answer' => $qa['a'],
                ];
                $docQas = json_encode($qas[$key], JSON_UNESCAPED_UNICODE) . PHP_EOL;
                $this->upsert($toCollectionName, new Document($docQas, array_merge($qas[$key], [])));
            }

            // 将 qas 单独追加保存到 BASE_PATH/data/qa.json 文件中，question: question, answer: answer 格式，不要读取再保存，直接追加
            $qaFile = BASE_PATH . '/data/qa.jsons';
            $fp = fopen($qaFile, 'a');
            foreach ($qas as $qa) {
                if (isset($qa['question'], $qa['answer']) && $qa['question'] && $qa['answer']) {
                    fwrite($fp, json_encode($qa, JSON_UNESCAPED_UNICODE) . PHP_EOL);
                }
            }
            fclose($fp);
        }

    }
}
