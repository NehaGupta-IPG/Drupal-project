Access checkers + cacheability
==============================

Route access checkers, `hook_entity_access()` and anything that needs to return an `AccessResultInterface` object should add the appropriate [cacheability metadata](https://www.drupal.org/developing/api/8/cache).

If you haven't read, go read up on [cache tags](https://www.drupal.org/developing/api/8/cache/tags), [cache contexts](https://www.drupal.org/developing/api/8/cache/contexts) and [max-age](https://www.drupal.org/developing/api/8/cache/max-age).

### [](#s-access-checker-parameters "Permalink to this headline")Access checker parameters

The access checker will receive various parameters — at least a user account (`AccountInterface`) and often an entity. Then it will make decisions on the properties of these parameters.

A cacheable dependency on a `$parameter` should be added if changing at least one property of this parameter will change the access result.

For example:

```php
$access_result = AccessResult::allowedIf($node->isPublished())
  // Access result depends on a property of the object that might change: it is a cacheable dependency.
  ->addCacheableDependency($node);

```

Another common case when the access result depends on a property which can not change (typically an ID, UUID). For example, allowing access if the given user account is the owner of an object:

```php
$access_result = AccessResult::allowedIf($node->getOwnerId() === $account->id())
  // Access result depends on the node's owner, the owner might change.
  ->addCacheableDependency($node);

// Access result also depends on a user account, and the ID of the user account can never change. Hence we don't need to add $account as a cacheable dependency.

// But, if $account is the current user, and not some hardcoded user, we also need to make sure we vary this by the current user, so that we don't run this access check once and then reuse its result for all users.
if ($account->id() === \Drupal::currentUser()->id()) {
  $access_result->cachePerUser();
}

```

 