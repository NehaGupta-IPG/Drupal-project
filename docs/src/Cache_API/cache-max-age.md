Cache max-age
=============

> Cache max-age = time dependencies

> Cache max-age is analogous to HTTP's [`Cache-Control`](http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9) header's [`max-age`](http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9.3) directive (**but it is not related to it!**)

[](#s-why "Permalink to this headline")Why?
-------------------------------------------

Cache max-age provides a declarative way to create time-dependent caches.

Some data is only valid for a limited period of time, in that case, you want to specify a corresponding maximum age. However, in Drupal core's case, we don't have any data that is valid for only a limited period of time; we typically cache permanently (see below) and rely entirely on [cache tags](https://www.drupal.org/developing/api/8/cache/tags) for invalidation.

[](#s-what "Permalink to this headline")What?
---------------------------------------------

A cache `max-age` is a positive integer, expressing a number of seconds.

Cache max-ages are passed around as individual integers, because a given cache item can only logically have a single max-age.

Examples:

- `60` means cacheable for 60 seconds
- `100` means cacheable for 100 seconds
- `0` means cacheable for zero seconds, i.e. *not cacheable*
- [`\Drupal\Core\Cache\Cache::PERMANENT`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Cache.php/constant/Cache%3A%3APERMANENT) (value `-1`) means `cacheable forever`, i.e. *this will only ever be invalidated due to [cache tags](https://www.drupal.org/developing/api/8/cache/tags)*. (In other words: ∞, or *infinite* seconds.)

So if you for example want to prevent a rendered block from being cached, you should specify `max-age=0` on it.

Example for most render arrays:

`$build['#cache']['max-age'] = 0;`

Example in a function:


```php
\Drupal::cache()->set('my_cache_item', $school_list, \Drupal::time()->getRequestTime() + (86400));
```

If you want to change block max-age to 0 then you must implement getCacheMaxAge method.

[](#s-limitations-of-max-age "Permalink to this headline")Limitations of max-age
--------------------------------------------------------------------------------

Unfortunately, `max-age`does not work for anonymous users and the Drupal core Page Cache module (or other external cache services such as Varnish or CDNs). For example, see these issues:

- <span class="project-issue-issue-link project-issue-status-info project-issue-status-13">[\#2352009: Bubbling of elements' max-age to the page's headers and the page cache](https://www.drupal.org/project/drupal/issues/2352009 "Status: Needs work")</span>
- <span class="project-issue-issue-link project-issue-status-info project-issue-status-1">[\#2449749: Add #cache\['downstream-ttl'\] to force expiration after a certain time and fix #cache\['max-age'\] logic by adding #cache\['age'\]](https://www.drupal.org/project/drupal/issues/2449749 "Status: Active")</span>
- <span class="project-issue-issue-link project-issue-status-info project-issue-status-6">[\#2835068: PageCache caching uncacheable responses (violating HTTP/1.0 spec) + D8 intentionally disabling HTTP/1.0 proxies = WTF](https://www.drupal.org/project/drupal/issues/2835068 "Status: Closed (works as designed), Assigned to: vidorado")</span>
- <span class="project-issue-issue-link project-issue-status-info project-issue-status-7">[\#2951814: Improve X-Drupal-Cache and X-Drupal-Dynamic-Cache headers, even for responses that are not cacheable](https://www.drupal.org/project/drupal/issues/2951814 "Status: Closed (fixed)")</span>

Until these (and perhaps other) issues are resolved, beware that setting `max-age` on a render array included in a page is insufficient to ensure that anonymous users will see a new version after the `max-age` as elapsed. In the meanwhile, the [Cache Control Override](https://www.drupal.org/project/cache_control_override) contributed module tries to mitigate the problems. You might also have more luck setting a custom [cache tag](https://www.drupal.org/docs/8/api/cache-api/cache-tags) on pages with time-dependent content and invalidating those cache tags manually via `hook_cron()`. Good luck!

[](#s-debugging "Permalink to this headline")Debugging
------------------------------------------------------

### [](#s-html-comments-debugging "Permalink to this headline") HTML comments (debugging)

By [enabling render cache debugging](https://www.drupal.org/docs/develop/development-tools/disabling-and-debugging-caching#s-enable-render-cache-debugging) each render elements will display its cache information (including cache max-age) in HTML comments:


```html
<!-- CACHE MAX-AGE: -1 -->
...
<!-- PRE-BUBBLING CACHE MAX-AGE: -1 -->
```

### [](#headers "Permalink to this headline")Headers (debugging)

Finally: it is easy to see global cache max-age: one must only look at the `X-Drupal-Cache-Max-Age` header!

*Note: If you're not seeing those headers, you will want to [set up your Drupal instance for development.](https://www.drupal.org/developing/api/8/response/cacheable-response-interface#debugging)*

 Related Content 
-----------------

[Cacheability of render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays)
-----------------------------------------------------------------------------------------------------------------

 Short version in the API documentation: Render API overview: Caching [Cache contexts](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cache-contexts)
------------------------------------------------------------------------------------------

 Cache contexts provide a declarative way to create context-dependent variations of something that needs to be cached. [cache](https://www.drupal.org/taxonomy/term/184648)[performance](https://www.drupal.org/taxonomy/term/182595)[Render API](https://www.drupal.org/taxonomy/term/197404)[Cache tags](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cache-tags)
----------------------------------------------------------------------------------

 Cache tags = data dependencies 