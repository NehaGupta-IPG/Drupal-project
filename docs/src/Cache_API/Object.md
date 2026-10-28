# Cache backends

By default, cached data is stored in the database. However, Drupal can be configured to use a different backend (specified in their service definition), e.g. APCu or Memcache. This configuration can nominate a different backend for all cached data or for specific cache bins.

In a [settings.php](https://www.drupal.org/api/drupal/core%21modules%21package_manager%21tests%21fixtures%21fake_site%21sites%21example.com%21settings.php/main "This file should never be staged.") file, you can override the service used for a particular cache bin. For example, if your service implementation of [\\Drupal\\Core\\Cache\\CacheBackendInterface](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/interface/CacheBackendInterface/main "Defines an interface for cache implementations.") was called cache.custom, the following line would make Drupal use it for the 'cache\_render' bin:

```
$settings['cache']['bins']['render'] = 'cache.custom';
```

Additionally, you can register your cache implementation to be used by default for all cache bins with:

```
$settings['cache']['default'] = 'cache.custom';
```

For cache bins that are stored in the database, the number of rows is limited to 5000 by default. This can be changed for all database cache bins. For example, to instead limit the number of rows to 50000:

```
$settings['database_cache_max_rows']['default'] = 50000;
```

Or per bin (in this example we allow infinite entries):

```
$settings['database_cache_max_rows']['bins']['dynamic_page_cache'] = -1;
```

For monitoring reasons it might be useful to figure out the amount of data stored in tables. The following SQL snippet can be used for that:

```
SELECT table_name AS `Table`, table_rows AS 'Num. of Rows',
ROUND(((data_length + index_length) / 1024 / 1024), 2) `Size in MB` FROM
information_schema.TABLES WHERE table_schema = '***DATABASE_NAME***' AND
table_name LIKE 'cache_%'  ORDER BY (data_length + index_length) DESC
LIMIT 10;
```

Finally, you can chain multiple cache backends together, see [\\Drupal\\Core\\Cache\\ChainedFastBackend](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21ChainedFastBackend.php/class/ChainedFastBackend/main "Defines a backend with a fast and a consistent backend chain.") and [\\Drupal\\Core\\Cache\\BackendChain](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21BackendChain.php/class/BackendChain/main "Defines a chained cache implementation for combining multiple cache backends.").

### Set

Any cache backend should implement [CacheBackendInterface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/interface/CacheBackendInterface/8), so when you set a cache item with the [`::set()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/function/CacheBackendInterface%3A%3Aset/8) method, provide third and fourth arguments e.g:

```php
$cache_backend->set(
  $cid, $data, Cache::PERMANENT, ['node:5', 'user:7']
);
```
This stores a cache item with ID `$cid` permanently (i.e., stored indefinitely), but makes it susceptible to invalidation through *either* the `node:5` or `user:7` cache tags.

## Basic Operations

### Deletion

There are two ways to remove an item from the cache:

- Deletion (using [delete()](http://php.net/delete) deleteMultiple() or deleteAll()) permanently removes the item from the cache.
- Invalidation (using invalidate() or invalidateMultiple()) is a "soft" delete that only marks items as "invalid", meaning "not fresh" or "not fresh enough". Invalid items are not usually returned from the cache, so in most ways they behave as if they have been deleted. However, it is possible to retrieve invalid items, if they have not yet been permanently removed by the garbage collector, by passing TRUE as the second argument for get($cid, $allow\_invalid).

Use deletion if a cache item is no longer useful; for instance, if the item contains references to data that has been deleted. Use invalidation if the cached item may still be useful to some callers until it has been updated with fresh data. The fact that it was fresh a short while ago may often be sufficient.

Invalidation is particularly useful to protect against stampedes. Rather than having multiple concurrent requests updating the same cache item when it expires or is deleted, there can be one request updating the cache, while the other requests can proceed using the stale value. As soon as the cache item has been updated, all future requests will use the updated value.

# Cache bins

Cache storage is separated into "bins", each containing various cache items. Each bin can be configured separately; see [Configuration](#configuration).

When you request a cache object, you can specify the bin name in your call to [\\Drupal::cache](https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main "Returns the requested cache bin.")(). Alternatively, you can request a bin by getting service "cache.name\_of\_bin" from the container. The default bin is called "default", with service name "cache.default", it is used to store common and frequently used caches.

Other common cache bins are the following:

- **bootstrap**: Data needed from the beginning to the end of most requests, that has a very strict limit on variations and is invalidated rarely.
- **render**: Contains cached HTML strings like cached pages and blocks, can grow to large size.
- **data**: Contains data that can vary by path or similar context.
- **discovery**: Contains cached discovery data for things such as plugins, views\_data, or YAML discovered data such as library info.
- **memory**: An in-memory cache bin, also called static cache. Useful alternative to properties when cache invalidation is needed, for example through cache tags.

A module can define a cache bin by defining a service in its modulename.services.yml file as follows (substituting the desired name for "name\_of\_bin"):

```
cache.name_of_bin:
  class: <a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/interface/CacheBackendInterface/main" title="Defines an interface for cache implementations.">Drupal\Core\Cache\CacheBackendInterface</a>
  tags:
    - { name: cache.bin }
  factory: ['@cache_factory', 'get']
  arguments: [name_of_bin]
```

The tag can also include a default backend, which is useful for example when defining in-memory (which uses a separate tag) or fast chained bin.

```
- { name: cache.bin, default_backend: cache.backend.chainedfast }
- { name: cache.bin.memory, default_backend: cache.backend.memory.memory }
```

## Cache Tags

Cache tags provide a declarative way to track which cache items depend on some data managed by Drupal.

This is essential for a content management system/framework like Drupal because the same content can be reused in many ways. In other words: it is impossible to know ahead of time *where* some content is going to be used. In any of the places where the content is used, it may be cached. Which means the same content could be cached in dozens of places. Which then brings us to the famous quote "There are only two hard things in Computer Science: cache invalidation and naming things." — that is, *how are you going to invalidate all cache items where the content is being used?*


The fourth argument of the set() method can be used to specify cache tags, which are used to identify which data is included in each cache item. A cache item can have multiple cache tags (an array of cache tags), and each cache tag is a string. The convention is to generate cache tags of the form \[prefix\]:\[suffix\]. Usually, you'll want to associate the cache tags of entities, or entity listings. You won't have to manually construct cache tags for them — just get their cache tags via [\\Drupal\\Core\\Cache\\CacheableDependencyInterface::getCacheTags](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/function/CacheableDependencyInterface%3A%3AgetCacheTags/main "The cache tags associated with this object.")() and [\\Drupal\\Core\\Entity\\EntityTypeInterface::getListCacheTags](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityTypeInterface.php/function/EntityTypeInterface%3A%3AgetListCacheTags/main "The list cache tags associated with this entity type.")(). Data that has been tagged can be invalidated as a group: no matter the Cache ID (cid) of the cache item, no matter in which cache bin a cache item lives; as long as it is tagged with a certain cache tag, it will be invalidated.

Because of that, cache tags are a solution to the cache invalidation problem:

- For caching to be effective, each cache item must only be invalidated when absolutely necessary. (i.e. maximizing the cache hit ratio.)
- For caching to be correct, each cache item that depends on a certain thing must be invalidated whenever that certain thing is modified.

A typical scenario: a user has modified a node that appears in two views, three blocks and on twelve pages. Without cache tags, we couldn't possibly know which cache items to invalidate, so we'd have to invalidate everything: we had to sacrifice effectiveness to achieve correctness. With cache tags, we can have both.

Example:

```
// A cache item with nodes, users, and some custom module data.
$tags = [
  'my_custom_tag',
  'node:1',
  'node:3',
  'user:7',
];
<a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/class/Drupal/main" title="Static Service Container wrapper.">\Drupal</a>::<a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main" title="Returns the requested cache bin.">cache</a>()-><a class="local" href="https://www.drupal.org/api/drupal/main/search/set" title="Multiple implementations exist.">set</a>($cid, $data, <a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/constant/CacheBackendInterface%3A%3ACACHE_PERMANENT/main" title="Indicates that the item should never be removed unless explicitly deleted.">CacheBackendInterface::CACHE_PERMANENT</a>, $tags);
// Invalidate all cache items with certain tags.
<a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Cache.php/class/Cache/main" title="Helper methods for cache.">\Drupal\Core\Cache\Cache</a>::<a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Cache.php/function/Cache%3A%3AinvalidateTags/main" title="Marks cache items from all bins with any of the specified tags as invalid.">invalidateTags</a>([
  'user:1',
]);
```

Drupal is a content management system, so naturally you want changes to your content to be reflected everywhere, immediately. That's why we made sure that every entity type in Drupal automatically has support for cache tags: when you save an entity, you can be sure that the cache items that have the corresponding cache tags will be invalidated. This also is the case when you define your own entity types: you'll get the exact same cache tag invalidation as any of the built-in entity types, with the ability to override any of the default behavior if needed. See [\\Drupal\\Core\\Cache\\CacheableDependencyInterface::getCacheTags](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/function/CacheableDependencyInterface%3A%3AgetCacheTags/main "The cache tags associated with this object.")(), [\\Drupal\\Core\\Entity\\EntityTypeInterface::getListCacheTags](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityTypeInterface.php/function/EntityTypeInterface%3A%3AgetListCacheTags/main "The list cache tags associated with this entity type.")(), [\\Drupal\\Core\\Entity\\EntityBase::invalidateTagsOnSave](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityBase.php/function/EntityBase%3A%3AinvalidateTagsOnSave/main "Invalidates an entity's cache tags upon save.")() and [\\Drupal\\Core\\Entity\\EntityBase::invalidateTagsOnDelete](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityBase.php/function/EntityBase%3A%3AinvalidateTagsOnDelete/main "Invalidates an entity's cache tags upon delete.")().

### Definition

A cache tag is a string.

Cache tags are passed around in sets (order doesn't matter) of strings, so they are typehinted to `string[]`. They're sets because a single cache item can depend on (be invalidated by) many cache tags.

### Syntax

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

### Common cache tags

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

### Invalidate

Tagged cache items are invalidated via their tags, using [cache\_tags.invalidator:invalidateTags()](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheTagsInvalidator.php/function/CacheTagsInvalidator%3A%3AinvalidateTags/8) (or, when you cannot inject the `cache_tags.invalidator` service: [`Cache::invalidateTags()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Cache.php/function/Cache%3A%3AinvalidateTags/8)), which accepts a set of cache tags (`string[]`).

*Note: this invalidates items tagged with given tags, across *all* cache bins. This is because it doesn't make sense to invalidate cache tags on individual bins, because the data that has been modified, whose cache tags are being invalidated, can have dependencies on cache items in *other* cache bins.*

### Integration with reverse proxies

Rather than caching responses in Drupal and invalidating them with cache tags, you could also cache responses in reverse proxies (Varnish, CDN …) and then invalidate responses they have cached using cache tags associated with those responses. To allow those reverse proxies to know which cache tags are associated with each response, you can send the cache tags along with a header.

Just like Drupal can send an `X-Drupal-Cache-Tags` header for debugging, it can also send a [`Surrogate-Keys` header with space-separated values](https://www.fastly.com/blog/surrogate-keys-part-1) as expected by some CDNs or a [`Cache-Tag` header with comma-separated values](https://support.cloudflare.com/hc/en-us/articles/206596608-How-to-Purge-Cache-Using-Cache-Tags) as expected by other CDNs. And it could also be a reverse proxy you run yourself, rather than a commercial CDN service.

As a rule of thumb, it's recommended that both your web server and your reverse proxy support response headers with values of up to 16 KB.

1. HTTP is text-based. Cache tags are therefore also text-based. Reverse proxies are free to represent cache tags in a different data structure internally. The 16 KB response header value limit was selected based on 2 factors: A) to ensure it works for the 99% case, B) what is practically achievable. Typical web servers (Apache) and typical CDNs (Fastly) support 16 KB response header values. This means roughly 1000 cache tags, which is enough for the 99% case.
2. The number of cache tags varies widely by site and the specific response. If it's a response that depends on many other things, there will be many cache tags. More than 1000 cache tags on a response will be rare.
3. But, of course, this guideline (~1000 tags/response is sufficient) may and will evolve over time, as we A) see more real-world applications use it, B) see systems specifically leverage/build on top of this capability.

Finally, anything beyond 1000 cache tags probably indicates a deeper problem: that the response is overly complex, that it should be split up. Nothing prevents you going beyond that number in Drupal, but it may require manual finetuning. Which is acceptable for such extremely complex use cases. Arguably, that's the case even for far less than 1000 cache tags.
