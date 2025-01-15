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
    protected function callNonpublicMethod(object $object, string $method)
    {
        $reflection = new ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        return $reflectionMethod->invoke($object);
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
