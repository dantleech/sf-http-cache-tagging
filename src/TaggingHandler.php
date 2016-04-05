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

use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tag handler is responsible for processing Request and Response objects;
 * associating HTTP responses with tags and tag invalidation.
 *
 * It can be invoked either from a HttpKernelInterface (middleware) instance or
 * by an event subscriber or listener (such as the event dispatcher of the
 * FOSHttpCache component).
 */
class TaggingHandler
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var TagManager
     */
    private $manager;

    /**
     * @var RequestMatcherInterface
     */
    private $requestMatcher;

    /**
     * @param TagManagerInterface $manager
     * @param RequestMatcherInterface $requestMatcher
     * @param array $options
     */
    public function __construct(
        TagManagerInterface $manager,
        RequestMatcherInterface $requestMatcher = null,
        array $options = []
    ) {
        $defaultOptions = [
            'purge_method' => 'POST',
            'header_tags' => 'X-Cache-Tags',
            'header_invalidate_tags' => 'X-Cache-Invalidate-Tags',
            'header_content_digest' => 'X-Content-Digest',
            'tag_encoding' => 'json',
            'ips' => null,
            'invalidate_from_response' => false,
        ];

        if ($diff = array_diff(array_keys($options), array_keys($defaultOptions))) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown options: "%s", valid options: "%s"',
                implode('", "', $diff), implode('", "', array_keys($defaultOptions))
            ));
        }

        $this->options = array_merge($defaultOptions, $options);
        $this->manager = $manager;
        $this->requestMatcher = $requestMatcher ?: new RequestMatcher(null, null, null, $this->options['ips'] ?: '127.0.0.1');
    }

    /**
     * Check to see if a tag invlidation request has been made and invalidate
     * the tags in that case.
     *
     * Will return a Response object if the calling class should return a
     * premature response rather than continue.
     *
     * @param Request $request
     *
     * @return Response|null
     */
    public function handleRequest(Request $request)
    {
        if ($request->getMethod() !== $this->options['purge_method']) {
            return;
        }

        if (false === $request->headers->has($this->options['header_invalidate_tags'])) {
            return;
        }

        if (false === $this->requestMatcher->matches($request)) {
            $response = new Response('', 400);

            return $response;
        }

        $tags = $this->decodeTags($request->headers->get($this->options['header_invalidate_tags']));

        $this->manager->invalidateTags($tags);

        $response = new Response(sprintf('Tags processed: "%s"', implode('", "', $tags)));
        $response->setStatusCode(200, 'Invalidated');

        return $response;
    }

    /**
     * Check to see if the response contains tags which should be associated
     * with the cached page.
     *
     * @param Response $response
     */
    public function handleResponse(Response $response)
    {
        if ($response->headers->has($this->options['header_tags'])) {
            $this->storeTagsFromResponse($response);
        }

        if ($this->options['invalidate_from_response'] && $response->headers->has($this->options['header_invalidate_tags'])) {
            $this->invalidateTagsFromResponse($response);
        }
    }

    /**
     * Store tags and associate them with the response.
     *
     * @param Response $response
     */
    private function storeTagsFromResponse(Response $response)
    {
        $contentDigest = $this->getContentDigestFromHeaders($response->headers);
        $tags = $this->getTagsFromHeaders($response->headers);
        $this->manager->tagContentDigest($tags, $contentDigest);
    }

    /**
     * If the response has tags for invalidation, invalidate them.
     *
     * @param Response $response
     */
    private function invalidateTagsFromResponse(Response $response)
    {
        $tags = $this->decodeTags($response->headers->get($this->options['header_invalidate_tags']));
        $this->manager->invalidateTags($tags);
    }

    /**
     * Return the content digest from the headers.
     * The content digest should be set by the Symfony HTTP cache before
     * this method is invoked.
     *
     * If the content digest cannot be found then a \RuntimeException
     * is thrown.
     *
     * @param HeaderBag $headers
     *
     * @throws RuntimeException
     *
     * @return string
     */
    private function getContentDigestFromHeaders(HeaderBag $headers)
    {
        if (!$headers->has($this->options['header_content_digest'])) {
            throw new \RuntimeException(sprintf(
                'Could not find content digest header: "%s". Got headers: "%s"',
                $this->options['header_content_digest'],
                implode('", "', array_keys($headers->all()))
            ));
        }

        return $headers->get($this->options['header_content_digest']);
    }

    /**
     * Retrieve and decode the tag list from the response
     * headers.
     *
     * If no tags header is found then throw a \RuntimeException.
     * If the JSON is invalid then throw a \RuntimeException
     *
     * @param HeaderBag $headers
     *
     * @throws \RuntimeException
     *
     * @return string[]
     */
    private function getTagsFromHeaders(HeaderBag $headers)
    {
        $tagsRaw = $headers->get($this->options['header_tags']);
        $tags = $this->decodeTags($tagsRaw, true);

        return $tags;
    }

    /**
     * Decode the encoded tags using the given strategy or a callback.
     *
     * @param string $encodedTags
     *
     * @throws \RuntimeException if tags cannot be decoded.
     * @throws \InvalidArgumentException if given strategy is not known.
     *
     * @return string[]
     */
    private function decodeTags($encodedTags)
    {
        if ($this->options['tag_encoding'] === 'json') {
            $tags = json_decode($encodedTags);

            if (null === $tags) {
                throw new \RuntimeException(sprintf(
                    'Could not JSON decode tags header with value "%s"',
                    $encodedTags
                ));
            }

            return $tags;
        }

        if ($this->options['tag_encoding'] === 'comma-separated') {
            return explode(',', $encodedTags);
        }

        if (is_callable($this->options['tag_encoding'])) {
            return $this->options['tag_encoding']($encodedTags);
        }

        $validEncodings = ['json', 'comma-separated'];

        throw new \InvalidArgumentException(sprintf(
            'Invalid tag encoding option "%s". It must either be a callable or one of: "%s"',
            $this->options['tag_encoding'],
            implode('", "', $validEncodings)
        ));
    }
}
