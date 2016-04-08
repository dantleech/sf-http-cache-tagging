Symfony HTTP Cache Tagging Middleware
=====================================

[![Build Status](https://travis-ci.org/dantleech/sf-http-cache-tagging.svg?branch=master)](https://travis-ci.org/dantleech/sf-http-cache-tagging)
[![StyleCI](https://styleci.io/repos/54131442/shield)](https://styleci.io/repos/54131442)

Introduction
------------

This package provides a middleware which allows you to add "tagging"
capabilties to the [Symfony HTTPCache](http://symfony.com/doc/current/book/http_cache.html).

What this means is that you can associate responses with tags, which can later
be invalidated. What THIS means is that you can cache your responses
indefinitely and invalidate them only when the content on the page changes.

Features
--------

- Middleware to add tagging capability to the Symfony HTTP Cache.
- Configurable and extensible tag storage.
- Local and remote tag invalidation.
- Configurable HTTP headers.
- Configurable tag encoding.

Quick Start
-----------

### Installation

Require the library with composer:

    $ composer require dtl/http-cache-tagging

You will need a storage strategy, it is easiest to use the ``DoctrineCache``
strategy, and for this you will need the ``doctrine/cache`` package:

    $ composer require doctrine/cache

### Wrapping the kernel

```php
use Doctrine\Common\Cache\PhpFileCache;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use DTL\Symfony\HttpCacheTagging\Storage\DoctrineCache;
use DTL\Symfony\HttpCacheTagging\Manager\TagManager;
use DTL\Symfony\HttpCacheTagging\TaggingKernel;

// your main application
$app = new TestKernel();

// the standard Symfony HTTP cache
$store = new Store('/path/to/keep/cache');
$httpCache = new HttpCache($app, $store);

// our tag storage strategy
$tagStorage = new DoctrineCache(new PhpFileCache('/path/to/keep/tags'));
$tagManager = new TagManager($tagStorage, $store);

// now you can procss the request
$app = new TaggingKernel($httpCache, $tagManager);
$app->handle(Request::create());
```

### Tagging your response

To tag the response just add the tags to the configured tag header
(``X-Cache-Tags`` by default).

```php
class MyController
{
    // ..
    public function someAction(Request $request)
    {

        $id = 1;
        $entity = $this->entitymanager->find($id);
        $tag = get_class($entity) . $id;

        $response = Response::create($entity->getHelloWorld());
        $response->headers->set('X-Cache-Tags', json_encode([ $tag ]));

        return $response;
    }
}
```

Note that above we used JSON encode to convert the tags to a string. A JSON
encoded string is expected by default, however you may also choose to use
``comma-seperated`` value strategy or an encoding system of your choice by
sepecifying a callback in the ``tag_encoding`` option.

### Invalidating cache entries with tags

Invalidation can be done in three different ways:

- Direct invalidation.
- Request invalidation.
- Response invalidation.

#### Direct invalidation

Is where you purge the cache directly from your application, for this you will
need to inject the ``TagManager`` which you instantiated into your Application
kernel.


#### Request invalidation

Is where you send separate HTTP request to the caching server or servers. By
default this should be a ``POST`` request with the tags encoded in the
``X-Cache-Invalidate-Tags`` header.

Note that only request invalidation can be used when you have multiple
servers.

#### Response invalidation

Is where you set the invalidation headers in the HTTP response.
This has the same advantage as direct invalidation, but avoids having to
inject the tag manager as a service. The ``X-Cache-Invalidate-Tags`` header
is expected by default.

The response method is probably the most simple:

```php
class MyController
{
    // ...

    public function editAction(Request $request)
    {
        $id = 1;
        $entity = $this->entitymanager->find($id);
        $tag = get_class($entity) . $id;

        // update the entity

        $response = // get your response
        $response->headers->set('X-Cache-Invalidate-Tags', json_encode([ $tag ]));

        return $response;
    }
}
```

Here we are editing an object which represents a page on your website, we set
the response header, after the response has been sent any cache entries which
have the entities tag will be removed.

You will also need to update the kernel wrapping to:
```php
$kernel = new TaggingKernel($httpCache, $tagManager, array('invalidate_from_response' => true));
```

### Configuration

- **purge_method**: Purge method to use when invalidating remotely, note that
  the Symfony HTTP cache does not support the ``PURGE`` method.
- **header_tags**: Header to use when tagging the response, ``X-Cache-Tags``
  by default.
- **header_invalidate_tags**: Header to use for invalidating tags in either
  request (remote) or response (local). Default is
  ``X-Cache-Invalidate-Tags``.
- **tag_encoding**: How the tags should be decoded by the middleware, can be
  ``json`` (default), ``comma-separate`` or a PHP callable which will receive
  the raw tag string and return an array.
- **ips**: List of IP addresses which may remotely invalidate the cache.

License
-------

This library is released under the MIT license. See the included
[LICENSE](LICENSE) file for more information.
