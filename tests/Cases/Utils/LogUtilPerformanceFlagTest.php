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

namespace HyperfTest\Odin\Cases\Utils;

use Hyperf\Odin\Utils\LogUtil;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversDefaultClass \Hyperf\Odin\Utils\LogUtil
 */
class LogUtilPerformanceFlagTest extends TestCase
{
    /**
     * 测试正常响应时间（≤3分钟）返回NORMAL标记.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagNormal()
    {
        // 测试不同的正常响应时间
        $normalTimes = [
            0,          // 0毫秒
            1000,       // 1秒
            30000,      // 30秒
            60000,      // 1分钟
            120000,     // 2分钟
            180000,     // 3分钟（边界值）
        ];

        foreach ($normalTimes as $time) {
            $this->assertEquals(
                'NORMAL',
                LogUtil::getPerformanceFlag($time),
                "正常响应时间 {$time}ms 应该返回NORMAL标记"
            );
        }
    }

    /**
     * 测试慢响应（3-5分钟）返回SLOW标记.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagSlow()
    {
        $slowTimes = [
            180001,     // 3分钟1毫秒（刚超过阈值）
            240000,     // 4分钟
            300000,     // 5分钟（边界值）
        ];

        foreach ($slowTimes as $time) {
            $this->assertEquals(
                'SLOW',
                LogUtil::getPerformanceFlag($time),
                "慢响应时间 {$time}ms 应该返回SLOW标记"
            );
        }
    }

    /**
     * 测试很慢响应（5-10分钟）返回VERY_SLOW标记.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagVerySlow()
    {
        $verySlowTimes = [
            300001,     // 5分钟1毫秒
            450000,     // 7.5分钟
            600000,     // 10分钟（边界值）
        ];

        foreach ($verySlowTimes as $time) {
            $this->assertEquals(
                'VERY_SLOW',
                LogUtil::getPerformanceFlag($time),
                "很慢响应时间 {$time}ms 应该返回VERY_SLOW标记"
            );
        }
    }

    /**
     * 测试极慢响应（10-15分钟）返回EXTREMELY_SLOW标记.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagExtremelySlow()
    {
        $extremelySlowTimes = [
            600001,     // 10分钟1毫秒
            750000,     // 12.5分钟
            900000,     // 15分钟（边界值）
        ];

        foreach ($extremelySlowTimes as $time) {
            $this->assertEquals(
                'EXTREMELY_SLOW',
                LogUtil::getPerformanceFlag($time),
                "极慢响应时间 {$time}ms 应该返回EXTREMELY_SLOW标记"
            );
        }
    }

    /**
     * 测试严重慢响应（15-20分钟）返回CRITICALLY_SLOW标记.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagCriticallySlow()
    {
        $criticallySlowTimes = [
            900001,     // 15分钟1毫秒
            1050000,    // 17.5分钟
            1200000,    // 20分钟（边界值）
        ];

        foreach ($criticallySlowTimes as $time) {
            $this->assertEquals(
                'CRITICALLY_SLOW',
                LogUtil::getPerformanceFlag($time),
                "严重慢响应时间 {$time}ms 应该返回CRITICALLY_SLOW标记"
            );
        }
    }

    /**
     * 测试超时风险（>20分钟）返回TIMEOUT_RISK标记.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagTimeoutRisk()
    {
        $timeoutRiskTimes = [
            1200001,    // 20分钟1毫秒
            1500000,    // 25分钟
            1800000,    // 30分钟
            3600000,    // 1小时
        ];

        foreach ($timeoutRiskTimes as $time) {
            $this->assertEquals(
                'TIMEOUT_RISK',
                LogUtil::getPerformanceFlag($time),
                "超时风险响应时间 {$time}ms 应该返回TIMEOUT_RISK标记"
            );
        }
    }

    /**
     * 测试浮点数参数.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagWithFloat()
    {
        // 测试浮点数参数（模拟round()函数的结果）
        $this->assertEquals('NORMAL', LogUtil::getPerformanceFlag(179999.99));
        $this->assertEquals('SLOW', LogUtil::getPerformanceFlag(180000.01));
        $this->assertEquals('VERY_SLOW', LogUtil::getPerformanceFlag(300000.5));
        $this->assertEquals('EXTREMELY_SLOW', LogUtil::getPerformanceFlag(600000.1));
        $this->assertEquals('CRITICALLY_SLOW', LogUtil::getPerformanceFlag(900000.9));
        $this->assertEquals('TIMEOUT_RISK', LogUtil::getPerformanceFlag(1200000.1));
    }

    /**
     * 测试边界值.
     * @covers ::getPerformanceFlag
     */
    public function testGetPerformanceFlagBoundaryValues()
    {
        // 精确的边界值测试
        $this->assertEquals('NORMAL', LogUtil::getPerformanceFlag(180000));      // 3分钟 = 正常
        $this->assertEquals('SLOW', LogUtil::getPerformanceFlag(180001));  // 3分钟+1毫秒 = 慢

        $this->assertEquals('SLOW', LogUtil::getPerformanceFlag(300000));       // 5分钟 = 慢
        $this->assertEquals('VERY_SLOW', LogUtil::getPerformanceFlag(300001));  // 5分钟+1毫秒 = 很慢

        $this->assertEquals('VERY_SLOW', LogUtil::getPerformanceFlag(600000));        // 10分钟 = 很慢
        $this->assertEquals('EXTREMELY_SLOW', LogUtil::getPerformanceFlag(600001));   // 10分钟+1毫秒 = 极慢

        $this->assertEquals('EXTREMELY_SLOW', LogUtil::getPerformanceFlag(900000));   // 15分钟 = 极慢
        $this->assertEquals('CRITICALLY_SLOW', LogUtil::getPerformanceFlag(900001));  // 15分钟+1毫秒 = 严重慢

        $this->assertEquals('CRITICALLY_SLOW', LogUtil::getPerformanceFlag(1200000)); // 20分钟 = 严重慢
        $this->assertEquals('TIMEOUT_RISK', LogUtil::getPerformanceFlag(1200001));    // 20分钟+1毫秒 = 超时风险
    }

    /**
     * 测试性能标记常量映射的正确性.
     * @covers ::getPerformanceFlag
     */
    public function testPerformanceFlagMappingCorrectness()
    {
        $expectedMappings = [
            // 正常范围 (0 - 3分钟)
            60000 => 'NORMAL',
            180000 => 'NORMAL',

            // SLOW范围 (3 - 5分钟)
            240000 => 'SLOW',
            300000 => 'SLOW',

            // VERY_SLOW范围 (5 - 10分钟)
            450000 => 'VERY_SLOW',
            600000 => 'VERY_SLOW',

            // EXTREMELY_SLOW范围 (10 - 15分钟)
            750000 => 'EXTREMELY_SLOW',
            900000 => 'EXTREMELY_SLOW',

            // CRITICALLY_SLOW范围 (15 - 20分钟)
            1050000 => 'CRITICALLY_SLOW',
            1200000 => 'CRITICALLY_SLOW',

            // TIMEOUT_RISK范围 (> 20分钟)
            1500000 => 'TIMEOUT_RISK',
            3600000 => 'TIMEOUT_RISK',
        ];

        foreach ($expectedMappings as $time => $expectedFlag) {
            $this->assertEquals(
                $expectedFlag,
                LogUtil::getPerformanceFlag($time),
                "时间 {$time}ms 的性能标记映射不正确"
            );
        }
    }
}
