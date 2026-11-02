## Cache contexts

Cache contexts provide a declarative way to create context-dependent variations of something that needs to be cached. By making it declarative, code that creates caches becomes easier to read, and the same logic doesn't need to be repeated in every place where the same context variations are necessary.

Examples:

- Some expensive-to-calculate data depends on the active theme: different results for different themes. Then you'd vary by the `theme` cache context.
- When creating a render array that shows a personalized message, the render array varies per user. Then you'd vary (the render array) by the `user` cache context.
- Generally: when some expensive-to-calculate information varies by some environment context: vary by a cache context.


Cache contexts are services tagged with 'cache.context', whose classes implement [\\Drupal\\Core\\Cache\\Context\\CacheContextInterface](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21Context%21CacheContextInterface.php/interface/CacheContextInterface/main "Provides an interface for defining a cache context service.").

### Definition

A cache context is a string that refers to one of the available cache context services.

Cache contexts are passed around in sets (order doesn't matter) of strings, so they are typehinted to `string[]`. They're sets because a single cache item can depend on (vary by) many cache contexts.

Typically, cache contexts are derived from the request context (i.e., from the `Request`) object. Most of the environment for a web application is derived from the request context. After all, HTTP responses are generated in large part depending on the properties of the HTTP requests that triggered them.  
But, this doesn't mean cache contexts *have* to originate from the request — they could also depend on deployed code, e.g., a `deployment_id` cache context.

Second, cache contexts are *hierarchical* in nature. The clearest example: when varying something per user, it's pointless to also vary that per permissions (i.e., the set of permissions that a user has), because per-user is already more granular. A user has a set of permissions, so per-user caching *implies* per-permissions caching.  
Now for the most interesting aspect: if one part of the page is varied per user and another per permissions, then Drupal needs to be smart enough to make the combination of the two: only vary per user. That is where Drupal can exploit the hierarchy information to not create unnecessary variations.

For a render array, the cache context is specified as part of the #cache property of a render array. See the Caching section of the [Render API overview topic](https://www.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21theme.api.php/group/theme_render/main "Overview of the Theme system and Render API.") for details.

#### Syntax

- periods separate parents from children
- a plurally named cache context indicates a parameter may be specified; to use: append a colon, then specify the desired parameter (when no parameter is specified, *all* possible parameters are captured, e.g., *all* query arguments)

### Drupal core cache contexts

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

### Optimizing cache contexts

Drupal automatically uses the hierarchy information to simplify cache contexts as much as possible. For example, when one part of the page is varied per user (`user` cache context) and another part of the page is varied per permissions (`user.permissions` cache context), then it doesn't make sense to vary the final result (e.g.,: the page) per permissions, since varying per user is already more granular.  
In other words: `optimize([user, user.permissions]) = [user]`.

However, that is oversimplifying things a bit: even though `user` indeed implies `user.permissions` because it is more specific, if we optimize `user.permissions` away, any *changes to permissions* no longer cause the `user.permissions` cache context to be evaluated on every page load. Which means that if the permissions change, we still continue to use the same cached version, even though it should change whenever permissions change.

That is why cache contexts that depend on configuration that may change over time can associate cacheability metadata: [cache tags](https://www.drupal.org/developing/api/8/cache/tags) and a max-age. When such a cache context is optimized away, its cache tags are associated with the cache item. Hence whenever the assigned permissions change, the cache item is also invalidated.

(Remember that "caching" is basically "avoiding unnecessary computations". Therefore, optimizing a context away can be thought of as caching the result of the context service's `getContext()` method. In this case, it's an implicit cache (the value is discarded rather than stored), but the effect is the same: on a cache hit, the `getContext()` method is not called, hence: computations avoided. And when we cache something, we associate the cacheability of that thing; so in the case of cache contexts, we associate tags and max-age.)

A similar, but more advanced example are node grants. Node grants apply to a specific user, so the node grants cache context is `user.node_grants` Except that node grants *can* be extremely dynamic (they could, e.g., be time-dependent, and change every few minutes). It depends on the node grant hook implementations present on the particular site. Therefore, to be safe, the node grants cache context specifies `max-age = 0`, meaning that it can *not* be cached (i.e., optimized away). Hence `optimize([user, user.node_grants]) = [user, user.node_grants]`.  
Specific sites can override the default node grants cache context implementation and specify `max-age = 3600` instead, indicating that all their node grant hooks allow access results to be cached for *at most* an hour. On such sites, `optimize([user, user.node_grants]) = [user]`.

### Uncacheable contexts

Drupal core treats cache contexts with poor cacheability as uncacheable. The definition of what constitutes "poor cacheability" depends on the `renderer.config.auto_placeholder_conditions` service container parameter (defined in `core.services.yml`, by default `session` &amp; `user` contexts are not cached), which can be overridden by sites.  
For more information, see [Render API &gt; Auto-placeholdering](https://www.drupal.org/docs/drupal-apis/render-api/auto-placeholdering).

#### Discover

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

## CacheableDependencyInterface friends

To make dealing with cacheability metadata ([cache tags](https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags), [cache contexts](https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts) and [max-age](https://www.drupal.org/docs/drupal-apis/cache-api/cache-max-age)) easier, Drupal 8 has `CacheableDependencyInterface`.

### Entities and configuration objects

*All* entities in Drupal 8 (core, contrib &amp; custom) implement the [`EntityInterface` interface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityInterface.php/interface/EntityInterface/8) which extends both [`CacheableDependencyInterface`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/interface/CacheableDependencyInterface/8.2.x) and [`RefinableCacheableDependencyInterface`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21RefinableCacheableDependencyInterface.php/interface/RefinableCacheableDependencyInterface/8.2.x). Besides that, all entities in Drupal 8 core extend the [`Entity` abstract base class](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Entity.php/class/Entity/8), and contrib/custom are encouraged to do the same. This means that every single entity you interact with in Drupal 8 automatically has consistent cache tags (of the form `<entity type>:<entity ID>`, e.g. `node:5` and `user:3`) and cache contexts to reflect the translation.

*All* configuration objects in Drupal 8 core extend the [`ConfigBase` abstract base class](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Config%21ConfigBase.php/class/ConfigBase/8), which implements both `CacheableDependencyInterface` and `RefinableCacheableDependencyInterface`. This means that every single configuration object you interact with in Drupal 8 automatically has consistent cache tags (of the form `config:<configuration name>`, e.g. `config:system.performance`) and cache contexts to reflect the config overrides (of which translation is the only example in core).

Finally, all entities and configuration objects in Drupal 8 automatically have the content/interface language cache contexts (respectively) thanks to [`Entitymanager::getTranslationFromContext()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityManager.php/function/EntityManager%3A%3AgetTranslationFromContext/8) and [`LangaugeConfigFactoryOverride::getCacheableMetadata($name)`](https://api.drupal.org/api/drupal/core%21modules%21language%21src%21Config%21LanguageConfigFactoryOverride.php/function/LanguageConfigFactoryOverride%3A%3AgetCacheableMetadata/8).

### Using objects that are cacheable dependencies


Rendering is the most common example of depending on an object that is a cacheable dependency. To simplify that, we have [`RendererInterface::addCacheableDependency($build, $dependency)`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21RendererInterface.php/function/RendererInterface%3A%3AaddCacheableDependency/8) — where `$build` is the render array that depends on the object `$dependency`; the cacheability metadata of the object will automatically be "absorbed" by the render array. Which means the render array will be invalidated whenever the object's cache tag is invalidated, will cause a different version to be cached if a different translation is used (i.e. the content language cache context maps to a different language) and will expire automatically if the dependency has a max-age that isn't permanent (infinite).

See [Cacheability of render arrays — Concrete example](https://www.drupal.org/developing/api/8/render/arrays/cacheability#concrete-example) for a full example.

Another good example is access checks, which return [`AccessResult`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Access%21AccessResult.php/class/AccessResult/8) objects, which also has an [`AccessResult::addCacheableDependency($dependency)`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Access%21AccessResult.php/function/AccessResult%3A%3AaddCacheableDependency/8) method. Note that here, we *only* have the `$dependency` parameter, because we can store the cacheability metadata of the passed in dependency on the access result object itself. (The renderer, with its render arrays, is the exception.)

## Cache max-age

Cache max-age provides a declarative way to create time-dependent caches.

Some data is only valid for a limited period of time, in that case, you want to specify a corresponding maximum age. However, in Drupal core's case, we don't have any data that is valid for only a limited period of time; we typically cache permanently (see below) and rely entirely on [cache tags](https://www.drupal.org/developing/api/8/cache/tags) for invalidation.

## Definition

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

#### Limitations of max-age

Unfortunately, `max-age`does not work for anonymous users and the Drupal core Page Cache module (or other external cache services such as Varnish or CDNs). For example, see these issues:

- <span class="project-issue-issue-link project-issue-status-info project-issue-status-13">[\#2352009: Bubbling of elements' max-age to the page's headers and the page cache](https://www.drupal.org/project/drupal/issues/2352009 "Status: Needs work")</span>
- <span class="project-issue-issue-link project-issue-status-info project-issue-status-1">[\#2449749: Add #cache\['downstream-ttl'\] to force expiration after a certain time and fix #cache\['max-age'\] logic by adding #cache\['age'\]](https://www.drupal.org/project/drupal/issues/2449749 "Status: Active")</span>

Until these (and perhaps other) issues are resolved, beware that setting `max-age` on a render array included in a page is insufficient to ensure that anonymous users will see a new version after the `max-age` as elapsed. In the meanwhile, the [Cache Control Override](https://www.drupal.org/project/cache_control_override) contributed module tries to mitigate the problems. You might also have more luck setting a custom [cache tag](https://www.drupal.org/docs/8/api/cache-api/cache-tags) on pages with time-dependent content and invalidating those cache tags manually via `hook_cron()`. Good luck!
