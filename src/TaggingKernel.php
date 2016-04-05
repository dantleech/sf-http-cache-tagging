<?php

/*
 * This file is part of the Symfony Http Cache Tagging package.
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DTL\Symfony\HttpCacheTagging;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Middleware for adding tagging support to the Symfony
 * HTTP cache.
 */
class TaggingKernel implements HttpKernelInterface, TerminableInterface
{
    private $kernel;
    private $handler;

    /**
     * Wrap the given kernel in with this TaggingKernel using the given
     * TagManager implementation.
     *
     * The $options are passed directly to the TaggingHandler.
     *
     * @param HttpKernelInterface $kernel
     * @param TagManagerInterface $options
     * @param array $options
     */
    public function __construct(HttpKernelInterface $kernel, TagManagerInterface $tagManager, $options = [])
    {
        $this->handler = new TaggingHandler($tagManager, null, $options);
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if ($response = $this->handler->handleRequest($request)) {
            return $response;
        }

        $response = $this->kernel->handle($request, $type, $catch);

        $this->handler->handleResponse($response);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response)
    {
        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }
    }
}
