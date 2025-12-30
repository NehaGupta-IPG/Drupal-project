<?php

namespace Drupal\jsonapi\Normalizer\Value;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Represents the cacheability associated with the omission of a value.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
final class CacheableOmission extends CacheableNormalization {

  public function __construct(CacheableDependencyInterface $cacheability) {
    parent::__construct($cacheability, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public static function permanent($no_op = NULL) {
    return parent::permanent(NULL);
  }

  /**
   * A CacheableOmission should never have its normalization retrieved.
   */
  public function getNormalization() {
    throw new \LogicException('A CacheableOmission should never have its normalization retrieved.');
  }

}
