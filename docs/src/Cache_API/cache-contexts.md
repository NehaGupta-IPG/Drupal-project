Cache contexts
==============

> Cache contexts = (request) context dependencies

> Cache contexts are analogous to HTTP's [`Vary`](http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.44) header.

[](#why "Permalink to this headline")Why?
-----------------------------------------

Cache contexts provide a declarative way to create context-dependent variations of something that needs to be cached. By making it declarative, code that creates caches becomes easier to read, and the same logic doesn't need to be repeated in every place where the same context variations are necessary.

Examples:

- Some expensive-to-calculate data depends on the active theme: different results for different themes. Then you'd vary by the `theme` cache context.
- When creating a render array that shows a personalized message, the render array varies per user. Then you'd vary (the render array) by the `user` cache context.
- Generally: when some expensive-to-calculate information varies by some environment context: vary by a cache context.

Please note that cache contexts are not applied when serving cached pages to anonymous users with the [Internal Page Cache (page\_cache) ](https://www.drupal.org/docs/8/administering-drupal-8-site/internal-page-cache)module enabled.

[](#what "Permalink to this headline")What?
-------------------------------------------

A cache context is a string that refers to one of the available cache context services (see below).

Cache contexts are passed around in sets (order doesn't matter) of strings, so they are typehinted to `string[]`. They're sets because a single cache item can depend on (vary by) many cache contexts.

Typically, cache contexts are derived from the request context (i.e., from the `Request`) object. Most of the environment for a web application is derived from the request context. After all, HTTP responses are generated in large part depending on the properties of the HTTP requests that triggered them.  
But, this doesn't mean cache contexts *have* to originate from the request — they could also depend on deployed code, e.g., a `deployment_id` cache context.

Second, cache contexts are *hierarchical* in nature. The clearest example: when varying something per user, it's pointless to also vary that per permissions (i.e., the set of permissions that a user has), because per-user is already more granular. A user has a set of permissions, so per-user caching *implies* per-permissions caching.  
Now for the most interesting aspect: if one part of the page is varied per user and another per permissions, then Drupal needs to be smart enough to make the combination of the two: only vary per user. That is where Drupal can exploit the hierarchy information to not create unnecessary variations.

### [](#syntax "Permalink to this headline")Syntax

- periods separate parents from children
- a plurally named cache context indicates a parameter may be specified; to use: append a colon, then specify the desired parameter (when no parameter is specified, *all* possible parameters are captured, e.g., *all* query arguments)

### [](#core-contexts "Permalink to this headline")Drupal core's cache contexts

Drupal core ships with the following hierarchy of cache contexts:


```php
cookies
  :name
headers
  :name
ip
languages
  :type
protocol_version // Available in 8.9.x or higher.
request_format
route
  .book_navigation
  .menu_active_trails
    :menu_name
  .name
session
  .exists
theme
timezone
url
  .path
    .is_front // Available in 8.3.x or higher.
    .parent
  .query_args
    :key
    .pagers
      :pager_id
  .site
user
  .is_super_user
  .node_grants
    :operation
  .permissions
  .roles
    :role

```

**Note:** To use the `url.path.is_front` cache context in prior branches/releases, see the [change record](https://www.drupal.org/node/2830442).

Everywhere cache contexts are used, that entire hierarchy is listed, which has 3 benefits:

1. no ambiguity: it's clear what parent cache context is based on wherever it is used
2. comparing (and folding) cache contexts becomes simpler: if both `a.b.c` and `a.b` are present, it's obvious that `a.b` encompasses `a.b.c`, and thus it's clear why the `a.b.c` can be omitted, why it can be "folded" into the parent
3. no need to deal with ensuring each level in a tree is unique in the entire tree

So, examples of declarative cache contexts from that hierarchy:

- `theme` (vary by negotiated theme)
- `user.roles` (vary by the combination of roles)
- `user.roles:anonymous` (vary by whether the current user has the 'anonymous' role or not, i.e., "is anonymous user")
- `languages` (vary by all language types: interface, content …)
- `languages:language_interface` (vary by interface language — `LanguageInterface::TYPE_INTERFACE`)
- `languages:language_content` (vary by content language — `LanguageInterface::TYPE_CONTENT`)
- `url` (vary by the entire URL)
- `url.query_args` (vary by the entire given query string)
- `url.query_args:foo` (vary by the `?foo` query argument)
- `protocol_version` (vary by HTTP 1 vs 2)

### [](#optimizing "Permalink to this headline")Optimizing/folding/combining/simplifying of cache contexts

Drupal automatically uses the hierarchy information to simplify cache contexts as much as possible. For example, when one part of the page is varied per user (`user` cache context) and another part of the page is varied per permissions (`user.permissions` cache context), then it doesn't make sense to vary the final result (e.g.,: the page) per permissions, since varying per user is already more granular.  
In other words: `optimize([user, user.permissions]) = [user]`.

However, that is oversimplifying things a bit: even though `user` indeed implies `user.permissions` because it is more specific, if we optimize `user.permissions` away, any *changes to permissions* no longer cause the `user.permissions` cache context to be evaluated on every page load. Which means that if the permissions change, we still continue to use the same cached version, even though it should change whenever permissions change.

That is why cache contexts that depend on configuration that may change over time can associate cacheability metadata: [cache tags](https://www.drupal.org/developing/api/8/cache/tags) and a max-age. When such a cache context is optimized away, its cache tags are associated with the cache item. Hence whenever the assigned permissions change, the cache item is also invalidated.

(Remember that "caching" is basically "avoiding unnecessary computations". Therefore, optimizing a context away can be thought of as caching the result of the context service's `getContext()` method. In this case, it's an implicit cache (the value is discarded rather than stored), but the effect is the same: on a cache hit, the `getContext()` method is not called, hence: computations avoided. And when we cache something, we associate the cacheability of that thing; so in the case of cache contexts, we associate tags and max-age.)

A similar, but more advanced example are node grants. Node grants apply to a specific user, so the node grants cache context is `user.node_grants` Except that node grants *can* be extremely dynamic (they could, e.g., be time-dependent, and change every few minutes). It depends on the node grant hook implementations present on the particular site. Therefore, to be safe, the node grants cache context specifies `max-age = 0`, meaning that it can *not* be cached (i.e., optimized away). Hence `optimize([user, user.node_grants]) = [user, user.node_grants]`.  
Specific sites can override the default node grants cache context implementation and specify `max-age = 3600` instead, indicating that all their node grant hooks allow access results to be cached for *at most* an hour. On such sites, `optimize([user, user.node_grants]) = [user]`.

### [](#s-uncacheable-contexts "Permalink to this headline")Uncacheable contexts

Drupal core treats cache contexts with poor cacheability as uncacheable. The definition of what constitutes "poor cacheability" depends on the `renderer.config.auto_placeholder_conditions` service container parameter (defined in `core.services.yml`, by default `session` &amp; `user` contexts are not cached), which can be overridden by sites.  
For more information, see [Render API &gt; Auto-placeholdering](https://www.drupal.org/docs/drupal-apis/render-api/auto-placeholdering).

[](#recognize-discover-create "Permalink to this headline")How to recognize, discover and create?
-------------------------------------------------------------------------------------------------

Cache contexts are `cache.context`-tagged services. Any module can thus add more cache contexts. They implement `\Drupal\Core\Cache\Context\CacheContextInterface` or `\Drupal\Core\Cache\Context\CalculatedCacheContextInterface` (for cache contexts that accept parameters — i.e., cache contexts that accept a `:parameter` suffix).

Hence, all you have to do to find all cache contexts you have available for use, is to go to the `CacheContextInterface` and `CalculatedCacheContextInterface` and use your IDE to find all of its implementations. (In PHPStorm: Type Hierarchy → Subtypes Hierarchy, in NetBeans: right-click on Interface name → Find Usages → Find All Subtypes.)

Alternatively you can use Drush `php` command to run PHP code inside the context of Drupal to display all current cache contexts for your site or application. For this, run `drush php` and paste the following code in the PHP console:


```php
$cache_contexts_manager_service =  \Drupal::service('cache_contexts_manager');
$cache_contexts = [];
foreach ($cache_contexts_manager_service->getAll() as $code) {

  $cache_context_service =  \Drupal::service('cache_context.'.$code);
  $label = $cache_context_service->getLabel();
  $class_name = get_class($cache_context_service);
  $cache_contexts[] = [
    'ID' => $code,
    'Label' => is_string($label) ? $label : $label->render(),
    'Class' => $class_name,      
  ];
}    
var_export($cache_contexts)
```

In every class you find, you will see data like this one in `\Drupal\Core\Cache\Context\UserCacheContext`:


```php
array (
  'ID' => 'user',
  'Label' => 'User',
  'Class' => 'Drupal\\Core\\Cache\\Context\\UserCacheContext',
)

```

This means that `'user'` is the actual cache context you can specify in code. (Alternatively, find where this class is being used in a `*.services.yml` file and look at the service ID. More about that below.)

> Tip: You can get an up-to-date, complete listing of all cache contexts in *Drupal core only* by [looking at the services tagged with `cache.context`! ](https://api.drupal.org/api/drupal/services/8.1.x?tag=cache.context)

The service ID is standardized. It always begins with `cache_context.`, followed by the parents of the cache context, finally followed by the name of the cache context. So, for example: `cache_context` (mandatory prefix) + `route` (parents) + `book_navigation` (this cache context's name):


```php
cache_context.route.book_navigation:
  class: Drupal\book\Cache\BookNavigationCacheContext
  arguments: ['@request_stack']
  tags:
    - { name: cache.context }

```

This defines the `route.book_navigation` cache context.

[](#debugging "Permalink to this headline")Debugging
----------------------------------------------------

All of the above is helpful information when debugging something that is being cached. But, there's one more thing: let's say something is being cached with the cache keys `['foo', 'bar']` and the cache contexts `['languages:language_interface', 'user.permissions', 'route']`. Then the corresponding cache item will be cached in a particular cache bin with a CID (*cache ID*) of:


```php
foo:bar:[languages:language_interface]=en:[route]=myroute.ROUTE_PARAMS_HASH:[user.permissions]=A_QUITE_LONG_HASH

```

In other words:

- cache keys are listed first, in the order provided
- cache contexts are listed second, alphabetically, and result in CID parts of the form `[<cache context name>]=<cache context value>`
- all these CID parts are concatenated together using colons

That should make it much easier to analyze &amp; debug caches!

### [](#s-html-comments-debugging "Permalink to this headline")HTML comments (debugging)

By [enabling render cache debugging](https://www.drupal.org/docs/develop/development-tools/disabling-and-debugging-caching#s-enable-render-cache-debugging) each render elements will display its cache information (including context) in HTML comments:


```html
<!-- CACHE CONTEXTS:
   * languages:language_interface
   * theme
   * timezone
   * url.site
   * user.permissions
   * user.roles
   * user.roles:anonymous
   * user.roles:authenticated
-->
...
<!-- PRE-BUBBLING CACHE CONTEXTS:
   * url.site
   * languages:language_interface
   * theme
   * user.permissions
-->
```

### [](#headers "Permalink to this headline")Headers (debugging)

Finally: it is easy to see which cache contexts a certain response depends on (and thus is varied by): one must only look at the `X-Drupal-Cache-Contexts` header!

*Note: If you're not seeing those headers, you will want to [set up your Drupal instance for development.](https://www.drupal.org/developing/api/8/render/arrays/cacheability#headers)*

[](#dynamic "Permalink to this headline")Dynamic Page Cache
-----------------------------------------------------------

Comprehensive use of cache contexts across Drupal allows Drupal to ship with its [Dynamic Page Cache](https://www.drupal.org/documentation/modules/smart_cache) enabled by default. (Previously known as 'Smart Cache')

[](#internal "Permalink to this headline")Internal Page Cache
-------------------------------------------------------------

Note the [Internal Page Cache](https://www.drupal.org/docs/8/administering-drupal-8-site/internal-page-cache) assumes that all pages served to anonymous users will be identical, regardless of the implementation of cache contexts. If you want to use cache contexts to vary the content served to anonymous users, this module must be disabled, and the performance impact that entails incurred.


 Related Content 
-----------------

[Cacheability of render arrays](https://www.drupal.org/docs/drupal-apis/render-api/cacheability-of-render-arrays)
-----------------------------------------------------------------------------------------------------------------

 Short version in the API documentation: Render API overview: Caching [Cache tags](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cache-tags)
----------------------------------------------------------------------------------

 Cache tags = data dependencies [Cache max-age](https://www.drupal.org/docs/develop/drupal-apis/cache-api/cache-max-age)
----------------------------------------------------------------------------------------

 Cache max-age = time dependencies [Auto-placeholdering](https://www.drupal.org/docs/drupal-apis/render-api/auto-placeholdering)
---------------------------------------------------------------------------------------------

 Drupal 8's Render API automatically creates placeholders of highly dynamic parts of a page, to have the best possible cacheability. 