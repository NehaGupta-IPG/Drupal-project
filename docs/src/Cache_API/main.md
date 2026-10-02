Information about the Drupal Cache API

### Basics

Note: If not specified, all of the methods mentioned here belong to [\\Drupal\\Core\\Cache\\CacheBackendInterface](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheBackendInterface.php/interface/CacheBackendInterface/main "Defines an interface for cache implementations.").

The Cache API is used to store data that takes a long time to compute. Caching can either be permanent or valid only for a certain time span, and the cache can contain any type of data.

To use the Cache API:

- Request a cache object through [\\Drupal::cache](https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main "Returns the requested cache bin.")() or by injecting a cache service.
- Define a Cache ID (cid) value for your data. A cid is a string, which must contain enough information to uniquely identify the data. For example, if your data contains translated strings, then your cid value must include the interface text language selected for page.
- Call the get() method to attempt a cache read, to see if the cache already contains your data.
- If your data is not already in the cache, compute it and add it to the cache using the set() method. The third argument of set() can be used to control the lifetime of your cache item.

Example:

```
$cid = 'my_module_example:' . <a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/class/Drupal/main" title="Static Service Container wrapper.">\Drupal</a>::<a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3AlanguageManager/main" title="Returns the language manager service.">languageManager</a>()-><a class="local" href="https://www.drupal.org/api/drupal/main/search/getCurrentLanguage" title="Multiple implementations exist.">getCurrentLanguage</a>()
  -><a class="local" href="https://www.drupal.org/api/drupal/main/search/getId" title="Multiple implementations exist.">getId</a>();
$data = NULL;
if ($cache = <a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/class/Drupal/main" title="Static Service Container wrapper.">\Drupal</a>::<a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main" title="Returns the requested cache bin.">cache</a>()-><a class="local" href="https://www.drupal.org/api/drupal/main/search/get" title="Multiple implementations exist.">get</a>($cid)) {
  $data = $cache-><a class="local" href="https://www.drupal.org/api/drupal/main/search/data" title="Multiple implementations exist.">data</a>;
}
else {
  $data = my_module_complicated_calculation();
  <a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/class/Drupal/main" title="Static Service Container wrapper.">\Drupal</a>::<a class="local" href="https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main" title="Returns the requested cache bin.">cache</a>()-><a class="local" href="https://www.drupal.org/api/drupal/main/search/set" title="Multiple implementations exist.">set</a>($cid, $data);
}
```

Note the use of $data and $cache-&gt;data in the above example. Calls to [\\Drupal::cache](https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main "Returns the requested cache bin.")()-&gt;get() return a record that contains the information stored by [\\Drupal::cache](https://www.drupal.org/api/drupal/core%21lib%21Drupal.php/function/Drupal%3A%3Acache/main "Returns the requested cache bin.")()-&gt;set() in the data property as well as additional meta information about the cached data. In order to make use of the cached data you can access it via $cache-&gt;data.

### Cache bins

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

See the [Services topic](https://www.drupal.org/api/drupal/core%21core.api.php/group/container/main "Overview of the Dependency Injection Container and Services.") for more on defining services.

### Deletion

There are two ways to remove an item from the cache:

- Deletion (using [delete](http://php.net/delete " delete()
      See unlink or unset")(), deleteMultiple() or deleteAll()) permanently removes the item from the cache.
- Invalidation (using invalidate() or invalidateMultiple()) is a "soft" delete that only marks items as "invalid", meaning "not fresh" or "not fresh enough". Invalid items are not usually returned from the cache, so in most ways they behave as if they have been deleted. However, it is possible to retrieve invalid items, if they have not yet been permanently removed by the garbage collector, by passing TRUE as the second argument for get($cid, $allow\_invalid).

Use deletion if a cache item is no longer useful; for instance, if the item contains references to data that has been deleted. Use invalidation if the cached item may still be useful to some callers until it has been updated with fresh data. The fact that it was fresh a short while ago may often be sufficient.

Invalidation is particularly useful to protect against stampedes. Rather than having multiple concurrent requests updating the same cache item when it expires or is deleted, there can be one request updating the cache, while the other requests can proceed using the stale value. As soon as the cache item has been updated, all future requests will use the updated value.

### Cache Tags

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

### Cache contexts

Some computed data depends on contextual data, such as the user roles of the logged-in user who is viewing a page, the language the page is being rendered in, the theme being used, etc. When caching the output of such a calculation, you must cache each variation separately, along with information about which variation of the contextual data was used in the calculation. The next time the computed data is needed, if the context matches that for an existing cached data set, the cached data can be reused; if no context matches, a new data set can be calculated and cached for later use.

Cache contexts are services tagged with 'cache.context', whose classes implement [\\Drupal\\Core\\Cache\\Context\\CacheContextInterface](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Context%21CacheContextInterface.php/interface/CacheContextInterface/main "Provides an interface for defining a cache context service."). See <https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts> for more information on cache contexts, including a list of the contexts that exist in Drupal core, and information on how to define your own contexts. See the [Services and the Dependency Injection Container](https://www.drupal.org/api/drupal/core%21core.api.php/group/container/main "Overview of the Dependency Injection Container and Services.")topic for more information about services.

Typically, the cache context is specified as part of the #cache property of a render array; see the Caching section of the [Render API overview topic](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21theme.api.php/group/theme_render/main "Overview of the Theme system and Render API.") for details.

### Configuration

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

### See also

[\\Drupal\\Core\\Cache\\DatabaseBackend](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21DatabaseBackend.php/class/DatabaseBackend/main "Defines a default cache implementation.")

<https://www.drupal.org/node/1884796>

### File

<dl> <dt> core/<wbr></wbr>[core.api.php](https://www.drupal.org/api/drupal/core%21core.api.php/main), line 404 </dt></dl> <section> </section>---