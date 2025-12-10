<?php

namespace Drupal\Core\Ajax;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Interface for Ajax commands that render content and attach assets.
 *
 * All Ajax commands that render HTML should implement these methods
 * to be able to return attached assets to the calling AjaxResponse object.
 *
 * @ingroup ajax
 */
interface CommandWithAttachedAssetsInterface {

  /**
   * Gets the attached assets.
   *
   * @return \Drupal\Core\Asset\AttachedAssets|null
   *   The attached assets for this command.
   */
  public function getAttachedAssets();

  /**
   * Gets the cacheability metadata.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability metadata for this command.
   */
  public function getCacheableMetadata(): CacheableMetadata;

}
