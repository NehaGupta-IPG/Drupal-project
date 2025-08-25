<?php

namespace Drupal\Core\Cache\Context;

/**
 * Allows cache contexts to influence how cache contexts are optimized.
 */
interface CacheContextOptimizableInterface {

  /**
   * Returns whether a cache context value has variations.
   *
   * For example, the timezone cache context can define itself as global
   * if a site does not have configurable timezones and the language contexts
   * are global on a site that is not multilingual.
   *
   * @return bool
   *   TRUE if the context has more than one possible variations.
   */
  public function hasVariations(): bool;

  /**
   * Returns parent contexts for this context.
   *
   * By default, parents are derived based on the cache context name, user is
   * a parent of user.permissions. This allows e.g. the user.roles context to
   * expand on this and indicate that user.permissions is also a valid
   * parent/replacement.
   *
   * Note that if implementing this, it is necessary to also include parents of
   * the parent contexts, e.g. user.roles must provide both user.permissions and
   * user.
   *
   * @return array
   *   A list of parents cache contexts.
   */
  public function getParentContexts(): array;

}
