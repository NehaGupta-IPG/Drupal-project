Cache tags
==========

> Cache tags = data dependencies

> Cache tags describe dependencies on data managed by Drupal

[](#s-why "Permalink to this headline")Why?
-------------------------------------------

Cache tags provide a declarative way to track which cache items depend on some data managed by Drupal.

This is essential for a content management system/framework like Drupal because the same content can be reused in many ways. In other words: it is impossible to know ahead of time *where* some content is going to be used. In any of the places where the content is used, it may be cached. Which means the same content could be cached in dozens of places. Which then brings us to the famous quote "There are only two hard things in Computer Science: cache invalidation and naming things." — that is, *how are you going to invalidate all cache items where the content is being used?*

*Note: Drupal 7 offered 3 ways of invalidating cache items: invalidate a specific CID, invalidate using a CID prefix, or invalidate everything in a cache bin. None of those 3 methods allow us to invalidate the cache items that contain an entity that was modified, because that was impossible to know!*

[](#s-what "Permalink to this headline")What?
---------------------------------------------

A cache tag is a string.

Cache tags are passed around in sets (order doesn't matter) of strings, so they are typehinted to `string[]`. They're sets because a single cache item can depend on (be invalidated by) many cache tags.

### [](#syntax "Permalink to this headline")Syntax

By convention, identifiers follow the format `thing:identifier`. When there is no concept of multiple instances of a thing, the format is simply `thing`. The only rule is that identifiers cannot contain spaces.

There is no strict syntax.

Examples:

- `node:5` — cache tag for `Node` entity 5 (invalidated whenever it changes)
- `user:3` — cache tag for `User` entity 3 (invalidated whenever it changes)
- `node_list` — list cache tag for `Node` entities (invalidated whenever any `Node` entity is updated, deleted or created, i.e., when a listing of nodes may need to change). Applicable to any entity type in following format: `{entity_type}_list`.
- `node_list:article` — list cache tag for the `article` bundle (content type). Applicable to any entity + bundle type in following format: `{entity_type}_list:{bundle}`.
- `config:node_type_list` — list cache tag for `Node type` entities (invalidated whenever any content types are updated, deleted or created). Applicable to any entity type in the following format: `config:{entity_bundle_type}_list`.
- `config:system.performance` — cache tag for the `system.performance` configuration
- `library_info` — cache tag for asset libraries

### [](#s-common-cache-tags "Permalink to this headline")Common cache tags

The data that Drupal manages fall in 3 categories:

1. entities — these have cache tags of the form `<entity type ID>:<entity ID>` as well as `<entity type ID>_list` and `<entity type ID>_list:<bundle>` to invalidate lists of entities. Config entity types use the cache tag of the underlying configuration object.
2. configuration — these have cache tags of the form `config:<configuration name>`
3. custom (for example `library_info`)

Drupal provides cache tags for entities &amp; configuration automatically — see the [`Entity` base class](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityBase.php/class/EntityBase/8.9.x) and the [`ConfigBase` base class](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Config%21ConfigBase.php/class/ConfigBase/8). (All specific entity types and configuration objects inherit from those.)

Although many entity types follow a predictable cache tag format of `<entity type ID>:<entity ID>`, third-party code shouldn't rely on this. Instead, it should retrieve cache tags to invalidate for a single entity using its`::getCacheTags()` method, e.g., `$node->getCacheTags()`, `$user->getCacheTags()`, `$view->getCacheTags()` etc.

In addition, it may be necessary to invalidate listings-based caches that depend on data from the entity in question (e.g., refreshing the rendered HTML for a listing when a new entity for it is created): this can be done using `EntityTypeInterface::getListCacheTags()`, then invalidating any returned by that method along with the entity's own tag(s). Starting with Drupal 8.9 ([change notice](https://www.drupal.org/node/3107058)), entities with bundles also automatically have a more specific cache tag that includes their bundle, to allow for more targetted invalidation of lists.

It is also possible to define custom, more specific cache tags based on values that entities have, for example a term reference field for lists that show entities that have a certain term. Invalidation for such tags can be put in custom presave/delete entity hooks:


```php
function yourmodule_node_presave(NodeInterface $node) {
  $tags = [];
  if ($node->hasField('field_category')) {
    foreach ($node->get('field_category') as $item) {
      $tags[] = 'mysite:node:category:' . $item->target_id;
    }
  }
  if ($tags) {
    Cache::invalidateTags($tags);
  }
}

```

These tags can then be used in code and in views using the contributed module [Views Custom Cache Tag.](https://www.drupal.org/project/views_custom_cache_tag)

Note: There is currently no API to get per-bundle and more specific cache tags from an entity or other object. That is because it is not the entity that decided which list cache tags are relevant for a certain list/query, that depends on the query itself. Future Drupal core versions will likely improve out of the box support for per-bundle cache tags and for example integrate them into the entity query builder and views.

[](#s-how "Permalink to this headline")How
------------------------------------------

### [](#s-setting "Permalink to this headline")Setting

Any cache backend should implement [CacheBackendInterface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/interface/CacheBackendInterface/8), so when you set a cache item with the [`::set()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/function/CacheBackendInterface%3A%3Aset/8) method, provide third and fourth arguments e.g:


```php
$cache_backend->set(
  $cid, $data, Cache::PERMANENT, ['node:5', 'user:7']
);
```

This stores a cache item with ID `$cid` permanently (i.e., stored indefinitely), but makes it susceptible to invalidation through *either* the `node:5` or `user:7` cache tags.

### [](#s-invalidating "Permalink to this headline")Invalidating

Tagged cache items are invalidated via their tags, using [cache\_tags.invalidator:invalidateTags()](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheTagsInvalidator.php/function/CacheTagsInvalidator%3A%3AinvalidateTags/8) (or, when you cannot inject the `cache_tags.invalidator` service: [`Cache::invalidateTags()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Cache.php/function/Cache%3A%3AinvalidateTags/8)), which accepts a set of cache tags (`string[]`).

*Note: this invalidates items tagged with given tags, across *all* cache bins. This is because it doesn't make sense to invalidate cache tags on individual bins, because the data that has been modified, whose cache tags are being invalidated, can have dependencies on cache items in *other* cache bins.*

[](#s-debugging "Permalink to this headline")Debugging
------------------------------------------------------

All of the above is helpful information when debugging something that is being cached. But, there's one more thing: let's say something is being cached with the cache tags `['foo', 'bar']`. Then the corresponding cache item will have a `tags` column (assuming the database cache back-end for a moment) with the following value:


```php
bar foo

```

In other words:

- cache tags are separated by space
- cache tags are sorted alphabetically

That should make it much easier to analyze &amp; debug caches!

### [](#s-html-comments-debugging "Permalink to this headline") HTML comments (debugging)

By [enabling render cache debugging](https://www.drupal.org/docs/develop/development-tools/disabling-and-debugging-caching#s-enable-render-cache-debugging) each render elements will display its cache information (including cache tags) in HTML comments:


```html
<!-- CACHE TAGS:
   * node:1
   * node_view
   * user:1
   * user_view
-->
...
<!-- PRE-BUBBLING CACHE TAGS:
   * node_view
   * node:1
-->

```

### [](#headers "Permalink to this headline")Headers (debugging)

Finally: it is easy to see which cache tags a certain response depends on (and thus is invalidated by): one must only look at the `X-Drupal-Cache-Tags` header!

(This is also why spaces [are forbidden](#syntax): because the `X-Drupal-Cache-Tags` header, just like many HTTP headers, uses spaces to separate values.)

*Note: If you're not seeing those headers, you will want to [set up your Drupal instance for development.](https://www.drupal.org/developing/api/8/response/cacheable-response-interface#debugging)*

[](#reverse-proxies "Permalink to this headline")Integration with reverse proxies
---------------------------------------------------------------------------------

Rather than caching responses in Drupal and invalidating them with cache tags, you could also cache responses in reverse proxies (Varnish, CDN …) and then invalidate responses they have cached using cache tags associated with those responses. To allow those reverse proxies to know which cache tags are associated with each response, you can send the cache tags along with a header.

Just like Drupal can send an `X-Drupal-Cache-Tags` header for debugging, it can also send a [`Surrogate-Keys` header with space-separated values](https://www.fastly.com/blog/surrogate-keys-part-1) as expected by some CDNs or a [`Cache-Tag` header with comma-separated values](https://support.cloudflare.com/hc/en-us/articles/206596608-How-to-Purge-Cache-Using-Cache-Tags) as expected by other CDNs. And it could also be a reverse proxy you run yourself, rather than a commercial CDN service.

As a rule of thumb, it's recommended that both your web server and your reverse proxy support response headers with values of up to 16 KB.

1. HTTP is text-based. Cache tags are therefore also text-based. Reverse proxies are free to represent cache tags in a different data structure internally. The 16 KB response header value limit was selected based on 2 factors: A) to ensure it works for the 99% case, B) what is practically achievable. Typical web servers (Apache) and typical CDNs (Fastly) support 16 KB response header values. This means roughly 1000 cache tags, which is enough for the 99% case.
2. The number of cache tags varies widely by site and the specific response. If it's a response that depends on many other things, there will be many cache tags. More than 1000 cache tags on a response will be rare.
3. But, of course, this guideline (~1000 tags/response is sufficient) may and will evolve over time, as we A) see more real-world applications use it, B) see systems specifically leverage/build on top of this capability.

Finally, anything beyond 1000 cache tags probably indicates a deeper problem: that the response is overly complex, that it should be split up. Nothing prevents you going beyond that number in Drupal, but it may require manual finetuning. Which is acceptable for such extremely complex use cases. Arguably, that's the case even for far less than 1000 cache tags.

Read documentation for [using Varnish with cache tags](https://www.drupal.org/developing/api/8/cache/tags/varnish).

CDNs known to support tag-based invalidation/purging:

- [CloudFlare](https://support.cloudflare.com/hc/en-us/articles/206596608-How-to-Purge-Cache-Using-Cache-Tags)
- [Fastly](https://docs.fastly.com/api/purge#purge_077dfb4aa07f49792b13c87647415537)
- [KeyCDN](https://www.keycdn.com/api#purge-zone-tag)
- [Akamai](https://developer.akamai.com/api/core_features/fast_purge/v3.html#concepts)

### [](#page_cache "Permalink to this headline")Internal Page Cache

Comprehensive use of cache tags across Drupal allows Drupal to ship with its [Internal Page Cache](https://www.drupal.org/documentation/modules/internal_page_cache) enabled by default. This is nothing more than a built-in reverse proxy.

[](#s-see-also "Permalink to this headline")See also
----------------------------------------------------

- [Cacheability of render arrays](https://www.drupal.org/developing/api/8/render/arrays/cacheability)
- [Cache contexts](https://www.drupal.org/developing/api/8/cache/contexts)
- [Cache max-age](https://www.drupal.org/developing/api/8/cache/max-age)

 