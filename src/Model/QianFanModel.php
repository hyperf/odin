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

namespace Hyperf\Odin\Model;

use Hyperf\Odin\Api\Request\EmbeddingRequest;
use Hyperf\Odin\Api\Response\EmbeddingResponse;
use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Factory\ClientFactory;
use Throwable;

class QianFanModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    public function embeddings(array|string $input, ?string $encoding_format = 'float', ?string $user = null, array $businessParams = []): EmbeddingResponse
    {
        try {
            // 检查模型是否支持嵌入功能
            $this->checkEmbeddingSupport();

            if (is_string($input)) {
                $input = [$input];
            }

            $client = $this->getClient();
            $embeddingRequest = new EmbeddingRequest(
                input: $input,
                model: $this->model
            );
            $embeddingRequest->setBusinessParams($businessParams);
            $embeddingRequest->setIncludeBusinessParams($this->includeBusinessParams);

            return $client->embeddings($embeddingRequest);
        } catch (Throwable $e) {
            $context = [
                'model' => $this->model,
                'input' => $input,
            ];
            throw $this->handleException($e, $context);
        }
    }

    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        // 使用ClientFactory创建OpenAI客户端
        return ClientFactory::createOpenAIClient(
            $config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }

    protected function getApiVersionPath(): string
    {
        return 'v2';
    }
}
