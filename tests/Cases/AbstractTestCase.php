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

namespace HyperfTest\Odin\Cases;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Class AbstractTestCase.
 */
abstract class AbstractTestCase extends TestCase
{
    /**
     * 调用对象的非公共方法.
     *
     * @param object $object 要调用方法的对象
     * @param string $method 方法名称
     * @param mixed ...$args 传递给方法的参数
     * @return mixed 方法的返回值
     */
    protected function callNonpublicMethod(object $object, string $method, ...$args)
    {
        $reflection = new ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        return $reflectionMethod->invoke($object, ...$args);
    }

    protected function getNonpublicProperty(object $object, string $property)
    {
        $reflection = new ReflectionClass($object);
        $reflectionProperty = $reflection->getProperty($property);
        return $reflectionProperty->getValue($object);
    }

    protected function setNonpublicPropertyValue(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($object);
        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setValue($object, $value);
    }
}
