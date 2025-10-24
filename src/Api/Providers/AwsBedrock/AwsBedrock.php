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

namespace Hyperf\Odin\Api\Providers\AwsBedrock;

use Hyperf\Odin\Api\Providers\AbstractApi;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidApiKeyException;
use Hyperf\Odin\Exception\LLMException\Configuration\LLMInvalidEndpointException;
use Psr\Log\LoggerInterface;

class AwsBedrock extends AbstractApi
{
    /**
     * @var Client[]|ConverseClient[]|ConverseCustomClient[]
     */
    protected array $clients = [];

    public function getClient(AwsBedrockConfig $config, ?ApiOptions $requestOptions = null, ?LoggerInterface $logger = null): Client|ConverseClient|ConverseCustomClient
    {
        // 检查AWS凭证，必须有访问密钥和密钥
        if (empty($config->accessKey) || empty($config->secretKey)) {
            throw new LLMInvalidApiKeyException('AWS访问密钥和密钥不能为空', null, 'AWS Bedrock');
        }

        // 验证区域设置
        if (empty($config->region)) {
            throw new LLMInvalidEndpointException('AWS区域不能为空', null, $config->region);
        }

        $requestOptions = $requestOptions ?? new ApiOptions();

        $key = md5(json_encode($config->toArray()) . json_encode($requestOptions->toArray()));
        if ($this->clients[$key] ?? null) {
            return $this->clients[$key];
        }

        if ($config->getType() === AwsType::CONVERSE_CUSTOM) {
            // Use custom Converse client without AWS SDK (manual Guzzle + SigV4)
            $client = new ConverseCustomClient($config, $requestOptions, $logger);
        } elseif ($config->getType() === AwsType::CONVERSE) {
            // Use Converse API with AWS SDK
            $client = new ConverseClient($config, $requestOptions, $logger);
        } else {
            // Use InvokeModel API with AWS SDK (default)
            $client = new Client($config, $requestOptions, $logger);
        }

        $this->clients[$key] = $client;
        return $this->clients[$key];
    }
}
