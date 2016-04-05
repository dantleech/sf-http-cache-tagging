<?php

/*
 * This file is part of the Symfony Http Cache Tagging package.
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DTL\Symfony\HttpCacheTagging\Tests\Unit;

use DTL\Symfony\HttpCacheTagging\TaggingKernel;
use DTL\Symfony\HttpCacheTagging\TagManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class TaggingKernelTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->kernel = $this->prophesize(HttpKernelInterface::class);
        $this->terminableKernel = $this->prophesize(HttpKernelInterface::class)
            ->willImplement(TerminableInterface::class);
        $this->tagManager = $this->prophesize(TagManagerInterface::class);
    }

    /**
     * It should handle the request.
     */
    public function testHandle()
    {
        $request = Request::create('/');
        $response = Response::create();

        $this->kernel->handle($request, 'foo-type', false)
            ->willReturn($response);

        $taggingKernel = $this->createKernel($this->kernel->reveal());
        $taggingKernel->handle($request, 'foo-type', false);
    }

    /**
     * It should pass on the terminate request when using a terminable kernel.
     */
    public function testTerminate()
    {
        $request = Request::create('/');
        $response = Response::create();

        $this->terminableKernel->terminate(
            $request, $response
        )->shouldBeCalled();

        $taggingKernel = $this->createKernel(
            $this->terminableKernel->reveal()
        );
        $taggingKernel->terminate($request, $response);
    }

    /**
     * It should not pass on the terminate request when using a non-terminable kernel.
     */
    public function testTerminateNonTerminable()
    {
        $request = Request::create('/');
        $response = Response::create();

        $this->terminableKernel->terminate(
            $request, $response
        )->shouldNotBeCalled();

        $taggingKernel = $this->createKernel(
            $this->kernel->reveal()
        );
        $taggingKernel->terminate($request, $response);
    }

    private function createKernel(HttpKernelInterface $kernel, array $options = [])
    {
        return new TaggingKernel(
            $kernel,
            $this->tagManager->reveal(),
            $options
        );
    }
}
