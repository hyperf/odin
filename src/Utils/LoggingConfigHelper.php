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

namespace Hyperf\Odin\Utils;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Odin\Api\RequestOptions\ApiOptions;
use Throwable;

/**
 * 日志配置辅助类，用于获取日志白名单相关的配置.
 */
class LoggingConfigHelper
{
    /**
     * 从API选项中获取日志白名单字段列表.
     */
    public static function getWhitelistFields(?ApiOptions $apiOptions = null): array
    {
        if ($apiOptions) {
            return $apiOptions->getLoggingWhitelistFields();
        }

        // 如果没有提供ApiOptions，尝试从全局配置获取
        try {
            $config = self::getConfig();
            return $config->get('odin.llm.general_api_options.logging.whitelist_fields', []);
        } catch (Throwable $e) {
            // 如果获取配置失败，返回空数组（表示不过滤）
            return [];
        }
    }

    /**
     * 从API选项中检查是否启用白名单过滤.
     */
    public static function isWhitelistEnabled(?ApiOptions $apiOptions = null): bool
    {
        if ($apiOptions) {
            return $apiOptions->isLoggingWhitelistEnabled();
        }

        // 如果没有提供ApiOptions，尝试从全局配置获取
        try {
            $config = self::getConfig();
            return (bool) $config->get('odin.llm.general_api_options.logging.enable_whitelist', false);
        } catch (Throwable $e) {
            // 如果获取配置失败，默认不启用白名单
            return false;
        }
    }

    /**
     * 应用白名单过滤并格式化日志数据.
     *
     * @param array $logData 原始日志数据
     * @param null|ApiOptions $apiOptions API选项配置
     * @return array 过滤并格式化后的日志数据
     */
    public static function filterAndFormatLogData(array $logData, ?ApiOptions $apiOptions = null): array
    {
        $whitelistFields = self::getWhitelistFields($apiOptions);
        $enableWhitelist = self::isWhitelistEnabled($apiOptions);

        return LogUtil::filterAndFormatLogData($logData, $whitelistFields, $enableWhitelist);
    }

    /**
     * 获取配置实例.
     */
    private static function getConfig(): ConfigInterface
    {
        $container = ApplicationContext::getContainer();
        return $container->get(ConfigInterface::class);
    }
}
