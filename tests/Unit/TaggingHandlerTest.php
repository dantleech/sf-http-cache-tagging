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

use DTL\Symfony\HttpCacheTagging\Manager\TagManager;
use DTL\Symfony\HttpCacheTagging\TaggingHandler;
use FOS\HttpCache\ProxyClient\Symfony;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class TaggingHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var TagManager
     */
    private $tagManager;

    public function setUp()
    {
        $this->tagManager = $this->prophesize(TagManager::class);
        $this->requestMatcher = $this->prophesize(RequestMatcherInterface::class);
    }

    public function testHandleTagsNoHeader()
    {
        $response = Response::create('', 200, []);

        $this->createHandler([])->handleResponse(
            $response
        );
    }

    /**
     * It should store tags from the Response.
     */
    public function testHandleTags()
    {
        $response = Response::create('test', 200, [
            'X-Content-Digest' => '1234',
            'X-Cache-Tags' => json_encode(['one', 'two']),
        ]);

        $this->tagManager->tagContentDigest(['one', 'two'], '1234')->shouldBeCalled();

        $this->createHandler([])->handleResponse(
            $response
        );
    }

    /**
     * It should throw an exception if the response headers do not contain the required header.
     */
    public function testHandleTagsResponseMissingHeader()
    {
        $response = Response::create('test', 200, [
            'X-Content-Digest' => '1234',
            'X-Cache-Tags' => json_encode(['one', 'two']),
        ]);

        $this->tagManager->tagContentDigest(['one', 'two'], '1234')->shouldBeCalled();

        $this->createHandler([])->handleResponse(
            $response
        );
    }

    /**
     * It should throw an exception if the content digest header is not present.
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessage Could not find content digest
     */
    public function testHandleTagsNoContentDigest()
    {
        $response = Response::create('test', 200, [
            'X-Cache-Tags' => json_encode(['one', 'two']),
        ]);

        $this->createHandler([])->handleResponse(
            $response
        );
    }

    /**
     * It should throw an exception if the JSON is invalid.
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessage Could not JSON decode
     */
    public function testInvalidJsonEncodedTags()
    {
        $digest = 'abcd1234';

        $response = Response::create('response', 200, [
            'X-Content-Digest' => $digest,
            'X-Cache-Tags' => 'this ain\'t JSON',
        ]);
        $this->createHandler([])->handleResponse(
            $response
        );
    }

    /**
     * It should invalidate tags from the response.
     */
    public function testInvalidateTagsResponse()
    {
        $tags = ['one', 'two', 'three'];
        $response = Response::create('', 200, [
            'X-Cache-Invalidate-Tags' => json_encode($tags),
        ]);

        $this->tagManager->invalidateTags($tags)->shouldBeCalledTimes(1);

        $this->createHandler(['invalidate_from_response' => true])->handleResponse(
            $response
        );
    }

    /**
     * It should invalidate the tags from the request.
     */
    public function testInvalidateTagsRequest()
    {
        $tags = ['one', 'two', 'three'];
        $request = Request::create('', 'POST');
        $request->headers->set('X-Cache-Invalidate-Tags', json_encode($tags));

        $this->requestMatcher->matches($request)->willReturn(true);
        $this->tagManager->invalidateTags($tags)->shouldBeCalledTimes(1);

        $this->createHandler([])->handleRequest(
            $request
        );
    }

    /**
     * It should not invalidate tags if the "purge" method is wrong.
     */
    public function testInvalidateTagsRequestBadPurgeMethod()
    {
        $tags = ['one', 'two', 'three'];
        $request = Request::create('', 'FOO');
        $request->headers->set('X-Cache-Invalidate-Tags', json_encode($tags));

        $this->requestMatcher->matches($request)->willReturn(true);
        $this->tagManager->invalidateTags($tags)->shouldNotBeCalled();

        $this->createHandler([])->handleRequest(
            $request
        );
    }

    /**
     * It should not invalidate tags if the headers do not contain the configured tag invalidation header.
     */
    public function testInvalidateTagsRequestMissingTagsHeader()
    {
        $request = Request::create('', 'POST');

        $response = $this->createHandler([])->handleRequest(
            $request
        );
        $this->assertNull($response);
    }

    /**
     * It should not invalidate tags if the request is not allowed.
     */
    public function testInvalidateTagsRequestNotAllowed()
    {
        $tags = ['one', 'two', 'three'];
        $request = Request::create('', 'POST');
        $request->headers->set('X-Cache-Invalidate-Tags', json_encode($tags));

        $this->requestMatcher->matches($request)->willReturn(false);
        $this->tagManager->invalidateTags($tags)->shouldNotBeCalled();

        $response = $this->createHandler([])->handleRequest(
            $request
        );
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * It should throw an exception if invalid options are given.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unknown options: "foobar", "1234", valid options:
     */
    public function testInvalidOptions()
    {
        $this->createHandler([
            'tag_encoding' => 'foobar',
            'foobar' => 'barfoo',
            '1234' => '5678',
        ]);
    }

    /**
     * It should throw an exception if an invalid encoding strategy is given.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid tag encoding option "foobar". It must either be a callable or one of: "json", "comma-separated"
     */
    public function testInvalidTagEncoding()
    {
        $response = Response::create('test', 200, [
            'X-Content-Digest' => '1234',
            'X-Cache-Tags' => json_encode(['one', 'two']),
        ]);

        $this->createHandler(['tag_encoding' => 'foobar'])->handleResponse(
            $response
        );
    }

    /**
     * It should encode tags using the given strategy / callable.
     *
     * @dataProvider provideTagEncoding
     */
    public function testTagEncoding($strategy, $rawTags, $expectedTags)
    {
        $response = Response::create('test', 200, [
            'X-Content-Digest' => '1234',
            'X-Cache-Tags' => $rawTags,
        ]);

        $this->tagManager->tagContentDigest($expectedTags, '1234', null)->shouldBeCalled();

        $this->createHandler(['tag_encoding' => $strategy])->handleResponse(
            $response
        );
    }

    public function provideTagEncoding()
    {
        return [
            [
                'json',
                '["one", "two"]',
                ['one', 'two'],
            ],
            [
                'comma-separated',
                'one,two,three',
                ['one', 'two', 'three'],
            ],
            [
                function ($raw) { return [$raw]; },
                'whatever',
                ['whatever'],
            ],
        ];
    }

    private function createHandler($options = [])
    {
        return new TaggingHandler(
            $this->tagManager->reveal(),
            $this->requestMatcher->reveal(),
            $options
        );
    }
}
