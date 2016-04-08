<?php

/*
 * This file is part of the Symfony Http Cache Tagging package.
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DTL\Symfony\HttpCacheTagging\Manager;

use DTL\Symfony\HttpCacheTagging\StorageInterface;
use DTL\Symfony\HttpCacheTagging\TagManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\HttpCache\Store;

/**
 * Tag manager for the Symfony HTTP Cache Proxy.
 */
class TagManager implements TagManagerInterface
{
    /**
     * @var StorageInterface
     */
    private $tagStorage;

    /**
     * @var Store
     */
    private $cacheStorage;

    /**
     * @var Filesystem
     */
    private $filesystem;

    public function __construct(StorageInterface $tagStorage, Store $cacheStorage, Filesystem $filesystem = null)
    {
        $this->tagStorage = $tagStorage;
        $this->cacheStorage = $cacheStorage;
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    /**
     * Invalidate the cache entries associated with any of the given list of tags.
     *
     * @param string[] $tags
     */
    public function invalidateTags(array $tags)
    {
        $digests = $this->tagStorage->getCacheIds($tags);

        foreach ($digests as $cacheDigest) {
            $cachePath = $this->cacheStorage->getPath($cacheDigest);

            $this->filesystem->remove($cachePath);
        }

        // remove the tag directory
        $this->tagStorage->removeTags($tags);
    }

    /**
     * Associate the given content digest with the given tags.
     *
     * NOTE: Often this method could simply be a proxy to
     *       StorageInterface#tagContentDigest.
     *
     * @param string[] $tags
     * @param string $contentDigest
     * @param int $lifetime
     *
     * @return void
     */
    public function tagContentDigest(array $tags, $contentDigest)
    {
        $this->tagStorage->tagContentDigest($tags, $contentDigest);
    }
}
