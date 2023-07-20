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
namespace HyperfTest\Odin\Cases\VectorStore;

use Hyperf\Odin\VectorStore\Qdrant\Client;
use Hyperf\Odin\VectorStore\Qdrant\Config;
use Hyperf\Odin\VectorStore\Qdrant\Qdrant;
use HyperfTest\Odin\Cases\AbstractTestCase;

/**
 * @internal
 * @coversNothing
 */
class QdrantTest extends AbstractTestCase
{
    public function setUp(): void
    {
        $this->qdrant = new Qdrant(new Client(new Config()));
    }

    public function testGetCollectionList()
    {
        $list = $this->qdrant->getCollectionList();
        $this->assertIsArray($list);
    }
    public function testGetCollectionInfo()
    {
        $list = $this->qdrant->getCollectionInfo('my_documents');
        print_r($list);
    }
}
