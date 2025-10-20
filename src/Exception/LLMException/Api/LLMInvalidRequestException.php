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

namespace Hyperf\Odin\Exception\LLMException\Api;

use Hyperf\Odin\Exception\LLMException\ErrorMessage;
use Hyperf\Odin\Exception\LLMException\LLMApiException;
use Throwable;

/**
 * 无效的API请求异常.
 */
class LLMInvalidRequestException extends LLMApiException
{
    /**
     * 错误码，基于API错误基数.
     */
    private const ERROR_CODE = 2;

    /**
     * 请求中的问题字段.
     */
    protected ?array $invalidFields = null;

    /**
     * 服务商返回的原始错误信息.
     */
    protected ?array $providerErrorDetails = null;

    /**
     * 创建一个新的无效请求异常实例.
     */
    public function __construct(
        string $message = ErrorMessage::INVALID_REQUEST,
        ?Throwable $previous = null,
        ?int $statusCode = 400,
        ?array $invalidFields = null,
        ?array $providerErrorDetails = null
    ) {
        $this->invalidFields = $invalidFields;
        $this->providerErrorDetails = $providerErrorDetails;

        // 构建详细的错误消息
        $detailedMessage = $this->buildDetailedMessage($message, $invalidFields, $providerErrorDetails);

        parent::__construct($detailedMessage, self::ERROR_CODE, $previous, 0, $statusCode);
    }

    /**
     * 获取请求中的问题字段.
     */
    public function getInvalidFields(): ?array
    {
        return $this->invalidFields;
    }

    /**
     * 获取服务商返回的原始错误详情.
     */
    public function getProviderErrorDetails(): ?array
    {
        return $this->providerErrorDetails;
    }

    /**
     * 构建详细的错误消息.
     */
    private function buildDetailedMessage(string $baseMessage, ?array $invalidFields, ?array $providerErrorDetails): string
    {
        $message = $baseMessage;

        // 如果有问题字段，添加到消息中
        if (! empty($invalidFields)) {
            $fieldsStr = implode(', ', array_keys($invalidFields));
            $message = sprintf('%s, invalid fields: %s', $message, $fieldsStr);
        }

        // 如果有服务商详细错误信息，添加到消息中
        if (! empty($providerErrorDetails)) {
            $providerDetails = [];

            if (isset($providerErrorDetails['code'])) {
                $providerDetails[] = sprintf('code: %s', $providerErrorDetails['code']);
            }

            if (isset($providerErrorDetails['message'])) {
                $providerDetails[] = sprintf('message: %s', $providerErrorDetails['message']);
            }

            if (isset($providerErrorDetails['type'])) {
                $providerDetails[] = sprintf('type: %s', $providerErrorDetails['type']);
            }

            if (! empty($providerDetails)) {
                $message .= ', error details: [' . implode(', ', $providerDetails) . ']';
            }
        }

        return $message;
    }
}
