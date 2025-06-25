<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Cacheable dependency object for use in tests.
 */
class TestCacheableDependency implements CacheableDependencyInterface {

  /**
   * The cache maximum age.
   */
  protected int $maxAge;

  public function __construct(
    protected array $contexts,
    protected array $tags,
    $max_age,
  ) {
    $this->maxAge = $max_age;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return $this->tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return $this->maxAge;
  }

}
