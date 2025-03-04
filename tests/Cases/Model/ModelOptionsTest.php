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

namespace HyperfTest\Odin\Cases\Model;

use Hyperf\Odin\Model\ModelOptions;
use HyperfTest\Odin\Cases\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversClass(ModelOptions::class)]
class ModelOptionsTest extends AbstractTestCase
{
    /**
     * 测试默认值.
     */
    public function testDefaultValues()
    {
        $options = new ModelOptions();

        $this->assertTrue($options->isChat());
        $this->assertFalse($options->isEmbedding());
        $this->assertFalse($options->isMultiModal());
        $this->assertFalse($options->supportsFunctionCall());
        $this->assertEquals(0, $options->getVectorSize());
    }

    /**
     * 测试构造函数设置值.
     */
    public function testConstructorValues()
    {
        $options = new ModelOptions([
            'chat' => false,
            'embedding' => true,
            'multi_modal' => true,
            'function_call' => true,
            'vector_size' => 1536,
        ]);

        $this->assertFalse($options->isChat());
        $this->assertTrue($options->isEmbedding());
        $this->assertTrue($options->isMultiModal());
        $this->assertTrue($options->supportsFunctionCall());
        $this->assertEquals(1536, $options->getVectorSize());
    }

    /**
     * 测试fromArray静态方法.
     */
    public function testFromArray()
    {
        $options = ModelOptions::fromArray([
            'chat' => false,
            'embedding' => true,
        ]);

        $this->assertFalse($options->isChat());
        $this->assertTrue($options->isEmbedding());
        $this->assertFalse($options->isMultiModal());
        $this->assertFalse($options->supportsFunctionCall());
    }

    /**
     * 测试toArray方法.
     */
    public function testToArray()
    {
        $initialData = [
            'chat' => false,
            'embedding' => true,
            'multi_modal' => true,
            'function_call' => true,
            'vector_size' => 1536,
        ];

        $options = new ModelOptions($initialData);
        $array = $options->toArray();

        $this->assertIsArray($array);
        $this->assertEquals($initialData, $array);
    }
}
