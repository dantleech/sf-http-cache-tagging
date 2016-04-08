<?php

/*
 * This file is part of the Symfony Http Cache Tagging package.
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DTL\Symfony\HttpCacheTagging\Tests\Unit\Manager;

use DTL\Symfony\HttpCacheTagging\Manager\NullTagManager;
use DTL\Symfony\HttpCacheTagging\TagManagerInterface;

class NullTagManagerTest extends \PHPUnit_Framework_TestCase
{
    private $manager;

    public function setUp()
    {
        $this->manager = new NullTagManager();
    }

    /**
     * It shuld implement the TagManagerInterface.
     */
    public function testImplements()
    {
        $this->assertInstanceOf(TagManagerInterface::class, $this->manager);
    }

    /**
     * It should do nothing when invalidate tags is invoked.
     */
    public function testInvalidateTags()
    {
        $this->manager->invalidateTags(['one', 'two']);
    }

    /**
     * It should do nothing when tagContentDigest is invoked.
     */
    public function testTagContentDigest()
    {
        $this->manager->tagContentDigest([], 'asdf');
    }
}
