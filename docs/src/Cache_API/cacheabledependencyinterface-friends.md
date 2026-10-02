CacheableDependencyInterface &amp; friends
==========================================

To make dealing with cacheability metadata ([cache tags](https://www.drupal.org/docs/drupal-apis/cache-api/cache-tags), [cache contexts](https://www.drupal.org/docs/drupal-apis/cache-api/cache-contexts) and [max-age](https://www.drupal.org/docs/drupal-apis/cache-api/cache-max-age)) easier, Drupal 8 has `CacheableDependencyInterface`.

[](#s-why "Permalink to this headline")Why?
-------------------------------------------

Imagine having to manually construct the cache tags of every single entity and configuration object you use in a render array (or some other computation) manually. And, when on a multilingual site, also add the necessary cache contexts manually (for either the translated entity or the language override for the configuration object).

And not just entities &amp; configuration, but also [access results](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Access%21AccessResult.php/class/AccessResult/8), [block plugins](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Block%21BlockPluginInterface.php/interface/BlockPluginInterface/8), [menu links](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Menu%21MenuLinkInterface.php/interface/MenuLinkInterface/8), [context plugins](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Plugin%21Context%21ContextInterface.php/interface/ContextInterface/8), [condition plugins](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Condition%21ConditionInterface.php/interface/ConditionInterface/8), and so on — because all of those end up in rendering (a specific type of computation) that we would like to cache.

In the early development days of Drupal 8, this used to be the case. Clearly, that was not tenable. And it was very error prone.

That's why [`CacheableDependencyInterface`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/interface/CacheableDependencyInterface/8) was introduced. Like its name indicates: objects implementing this interface can automatically become *cacheable dependencies*.

For example, when creating a render array that renders to `<p>Hi, %user, welcome to %site!</p>`, you rely on both the current user's `User` entity and the `system.site` configuration. When that render array is cached, it has both that `User` entity and that configuration object as its *cacheable dependencies*.

`CacheableDependencyInterface` can be implemented by any value object (i.e. an object that represents a logical unit of data). If you go and look at [its API documentation](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/interface/CacheableDependencyInterface/8), you'll see it is implemented by *a lot* of value objects in Drupal 8 core. In fact, it would be safe to say it is implemented by a *majority* of objects you interact with while writing Drupal 8 code!

There are two extreme opposite cases that are encountered quite frequently, for which Drupal has handy traits: the case of an unchanging object and therefore cacheable forever ([` UnchangingCacheableDependencyTrait `](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21UnchangingCacheableDependencyTrait.php/trait/UnchangingCacheableDependencyTrait/8), which always returns `max-age === permanent`) and the case of an object always being dynamically calculated and therefore never cacheable ([`UncacheableDependencyTrait`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21UncacheableDependencyTrait.php/trait/UncacheableDependencyTrait/8), which always returns `max-age === 0`).

### [](#s-refinablecacheabledependencyinterface "Permalink to this headline")`RefinableCacheableDependencyInterface`

But `CacheableDependencyInterface` is only capable of dealing with the "inherent", "canonical" cacheability metadata of an object. Sometimes, there are multiple *variants* of an object.

The most prominent examples of this are entity translations (it's the same entity, with the same entity ID, just in a different translation) and config translations (it's the same configuration object, with the same configuration name, just with a language override).

In both cases, the cacheability metadata that already exists on the original (untranslated) object remains applicable. For example, `node:5` cache tag). But — in case of the entity — the content language cache context is necessary (`'languages:' . LanguageInterface::TYPE_CONTENT`, see [Cache contexts](https://www.drupal.org/developing/api/8/cache/contexts)), to convey that this entity is a variant of the original entity, that varies depending on whatever content language cache context was negotiated. Similarly, in case of the configuration object, the interface language cache context is necessary (`'languages:' . LanguageInterface::TYPE_INTERFACE`), to convey that this configuration object is a variant of the original configuration object, that varies depending on whatever interface language cache context was negotiated.

An example beyond translation would be the Pirate day module that has a configuration override that applies *only* on pirate day, which inserts yar, har and random parrots in configuration; the configuration objects will then have a `pirate_day` cache context.

In all of the above examples, we need to be able to *refine* the cacheability metadata of our objects, to indicate a variant that has been loaded. For that reason, [`RefinableCacheableDependencyInterface`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21RefinableCacheableDependencyInterface.php/interface/RefinableCacheableDependencyInterface/8) was added, which allows just that: it has the ability to add cache tags, contexts and update the max-age.

To make it easy to implement this interface, there's also a handy trait: [`RefinableCacheableDependencyTrait`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21RefinableCacheableDependencyTrait.php/trait/RefinableCacheableDependencyTrait/8).

### [](#s-about-entities-configuration-objects "Permalink to this headline")About entities &amp; configuration objects

*All* entities in Drupal 8 (core, contrib &amp; custom) implement the [`EntityInterface` interface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityInterface.php/interface/EntityInterface/8) which extends both [`CacheableDependencyInterface`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableDependencyInterface.php/interface/CacheableDependencyInterface/8.2.x) and [`RefinableCacheableDependencyInterface`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21RefinableCacheableDependencyInterface.php/interface/RefinableCacheableDependencyInterface/8.2.x). Besides that, all entities in Drupal 8 core extend the [`Entity` abstract base class](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21Entity.php/class/Entity/8), and contrib/custom are encouraged to do the same. This means that every single entity you interact with in Drupal 8 automatically has consistent cache tags (of the form `<entity type>:<entity ID>`, e.g. `node:5` and `user:3`) and cache contexts to reflect the translation.

*All* configuration objects in Drupal 8 core extend the [`ConfigBase` abstract base class](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Config%21ConfigBase.php/class/ConfigBase/8), which implements both `CacheableDependencyInterface` and `RefinableCacheableDependencyInterface`. This means that every single configuration object you interact with in Drupal 8 automatically has consistent cache tags (of the form `config:<configuration name>`, e.g. `config:system.performance`) and cache contexts to reflect the config overrides (of which translation is the only example in core).

Finally, all entities and configuration objects in Drupal 8 automatically have the content/interface language cache contexts (respectively) thanks to [`Entitymanager::getTranslationFromContext()`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityManager.php/function/EntityManager%3A%3AgetTranslationFromContext/8) and [`LangaugeConfigFactoryOverride::getCacheableMetadata($name)`](https://api.drupal.org/api/drupal/core%21modules%21language%21src%21Config%21LanguageConfigFactoryOverride.php/function/LanguageConfigFactoryOverride%3A%3AgetCacheableMetadata/8).

[](#s-using-objects-that-are-cacheable-dependencies "Permalink to this headline")Using objects that are cacheable dependencies
------------------------------------------------------------------------------------------------------------------------------

Rendering is the most common example of depending on an object that is a cacheable dependency. To simplify that, we have [`RendererInterface::addCacheableDependency($build, $dependency)`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21RendererInterface.php/function/RendererInterface%3A%3AaddCacheableDependency/8) — where `$build` is the render array that depends on the object `$dependency`; the cacheability metadata of the object will automatically be "absorbed" by the render array. Which means the render array will be invalidated whenever the object's cache tag is invalidated, will cause a different version to be cached if a different translation is used (i.e. the content language cache context maps to a different language) and will expire automatically if the dependency has a max-age that isn't permanent (infinite).

See [Cacheability of render arrays — Concrete example](https://www.drupal.org/developing/api/8/render/arrays/cacheability#concrete-example) for a full example.

Another good example is access checks, which return [`AccessResult`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Access%21AccessResult.php/class/AccessResult/8) objects, which also has an [`AccessResult::addCacheableDependency($dependency)`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Access%21AccessResult.php/function/AccessResult%3A%3AaddCacheableDependency/8) method. Note that here, we *only* have the `$dependency` parameter, because we can store the cacheability metadata of the passed in dependency on the access result object itself. (The renderer, with its render arrays, is the exception.)

[](#s-related-interfaces-classes "Permalink to this headline")Related interfaces &amp; classes
----------------------------------------------------------------------------------------------

- [`CacheableMetadata`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableMetadata.php/class/CacheableMetadata/8)
- [`BubbleableMetadata`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21BubbleableMetadata.php/class/BubbleableMetadata/8)
- [`CacheableResponseInterface`](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableResponseInterface.php/interface/CacheableResponseInterface/8)

[](#s-see-also "Permalink to this headline")See also
----------------------------------------------------

- [Cache tags](https://www.drupal.org/developing/api/8/cache/tags)
- [Cache contexts](https://www.drupal.org/developing/api/8/cache/contexts)
- [Cache max-age](https://www.drupal.org/developing/api/8/cache/max-age)
- [`RendererInterface::addCacheableDependency()` documentation](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21RendererInterface.php/function/RendererInterface%3A%3AaddCacheableDependency/8)
- [Cacheability of render arrays](https://www.drupal.org/developing/api/8/render/arrays/cacheability#concrete-example)

 