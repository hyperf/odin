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

namespace Hyperf\Odin\Exception\LLMException\Configuration;

use Hyperf\Odin\Exception\LLMException\LLMConfigurationException;
use Throwable;

/**
 * API密钥无效或缺失异常.
 */
class LLMInvalidApiKeyException extends LLMConfigurationException
{
    /**
     * 错误码，基于配置错误基数.
     */
    private const ERROR_CODE = 1;

    /**
     * 创建一个新的无效API密钥异常实例.
     */
    public function __construct(string $message = '无效的API密钥或API密钥缺失', ?Throwable $previous = null, string $provider = '')
    {
        $message = $provider ? sprintf('[%s] %s', $provider, $message) : $message;
        parent::__construct($message, self::ERROR_CODE, $previous, 0, 401);
    }
}
