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

use Hyperf\Context\ApplicationContext;
use Hyperf\Odin\Contract\Api\ClientInterface;
use Hyperf\Odin\Factory\ClientFactory;
use Hyperf\Odin\Utils\ModelUtil;
use Throwable;

use function Hyperf\Config\config;

/**
 * OpenAI模型实现.
 *
 * 支持智能路由（需在配置中启用）：
 * - 当使用qwen系列模型时，自动切换到DashScope客户端
 * - 当使用deepseek系列模型时，自动切换到DeepSeek客户端
 * - 当使用kimi系列模型时，自动切换到DeepSeek客户端（支持reasoning_content）
 * - 其他模型继续使用OpenAI客户端
 */
class OpenAIModel extends AbstractModel
{
    protected bool $streamIncludeUsage = true;

    /**
     * 获取客户端实例，根据模型类型智能路由.
     * 智能路由功能需要在配置中启用，默认关闭.
     */
    protected function getClient(): ClientInterface
    {
        // 处理API基础URL，确保包含正确的版本路径
        $config = $this->config;
        $this->processApiBaseUrl($config);

        // 检查是否启用了Qwen智能路由，且为qwen系列模型
        if ($this->isSmartRoutingEnabled('qwen') && ModelUtil::isQwenModel($this->model)) {
            // 使用ClientFactory统一创建DashScope客户端
            return ClientFactory::createClient(
                'dashscope',
                $config,
                $this->getApiRequestOptions(),
                $this->logger
            );
        }

        // 检查是否启用了DeepSeek智能路由，且为deepseek系列模型
        if ($this->isSmartRoutingEnabled('deepseek') && ModelUtil::isDeepSeekModel($this->model)) {
            // 使用ClientFactory统一创建DeepSeek客户端
            return ClientFactory::createClient(
                'deepseek',
                $config,
                $this->getApiRequestOptions(),
                $this->logger
            );
        }

        // 检查是否启用了Kimi智能路由，且为kimi系列模型
        if ($this->isSmartRoutingEnabled('kimi') && ModelUtil::isKimiModel($this->model)) {
            // 使用ClientFactory统一创建DeepSeek客户端（Kimi API兼容且支持reasoning_content）
            return ClientFactory::createClient(
                'deepseek',
                $config,
                $this->getApiRequestOptions(),
                $this->logger
            );
        }

        // 使用ClientFactory统一创建OpenAI客户端
        return ClientFactory::createClient(
            'openai',
            $config,
            $this->getApiRequestOptions(),
            $this->logger
        );
    }

    /**
     * 获取API版本路径.
     * OpenAI的API版本路径为 v1.
     */
    protected function getApiVersionPath(): string
    {
        return 'v1';
    }

    /**
     * 检查指定类型的智能路由是否启用.
     *
     * @param string $type 路由类型：'qwen'、'deepseek' 或 'kimi'
     */
    private function isSmartRoutingEnabled(string $type): bool
    {
        try {
            if (! ApplicationContext::hasContainer()) {
                return false;
            }
            return (bool) config("odin.llm.smart_routing.{$type}", false);
        } catch (Throwable) {
            return false;
        }
    }
}
