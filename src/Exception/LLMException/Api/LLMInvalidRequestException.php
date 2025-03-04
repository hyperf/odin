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
     * 创建一个新的无效请求异常实例.
     */
    public function __construct(
        string $message = '无效的API请求',
        ?Throwable $previous = null,
        ?int $statusCode = 400,
        ?array $invalidFields = null
    ) {
        $this->invalidFields = $invalidFields;

        if (! empty($invalidFields)) {
            $fieldsStr = implode(', ', array_keys($invalidFields));
            $message = sprintf('%s，问题字段: %s', $message, $fieldsStr);
        }

        parent::__construct($message, self::ERROR_CODE, $previous, 0, $statusCode);
    }

    /**
     * 获取请求中的问题字段.
     */
    public function getInvalidFields(): ?array
    {
        return $this->invalidFields;
    }
}
