<?php

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\Datetime\Time;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\DatabaseBackend;
use Drupal\Core\Cache\DatabaseCacheTagsChecksum;
use Drupal\Core\Database\Database;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Minimal hardcoded container for early bootstrap.
 */
class BootstrapContainer implements ContainerInterface {

  /**
   * The cache.container service instance.
   */
  protected ?CacheBackendInterface $cacheContainer = NULL;

  /**
   * {@inheritdoc}
   */
  public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object {
    if ($id === 'cache.container') {
      if (!$this->cacheContainer) {
        $db = Database::getConnection();
        $this->cacheContainer = new DatabaseBackend(
          $db,
          new DatabaseCacheTagsChecksum($db),
          'container',
          new PhpSerialize(),
          new Time(new RequestStack()),
          DatabaseBackend::MAXIMUM_NONE,
        );
      }
      return $this->cacheContainer;
    }

    if ($invalidBehavior === self::EXCEPTION_ON_INVALID_REFERENCE) {
      throw new ServiceNotFoundException($id);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $id, ?object $service): void {
    throw new \BadMethodCallException('Not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $id): bool {
    throw new \BadMethodCallException('Not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function initialized(string $id): bool {
    throw new \BadMethodCallException('Not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null {
    throw new \BadMethodCallException('Not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function hasParameter(string $name): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value): void {
    throw new \BadMethodCallException('Not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceIds() {
    throw new \BadMethodCallException('Not supported.');
  }

}
