<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

/**
 * Container storage using Symfony's PhpDumper and PhpStorage.
 *
 * Compiles the container into a PHP class that benefits from opcache. The
 * compiled file is stored via PhpStorage and a hash is kept in the cache
 * backend to map the container identifier to the correct container class.
 */
class PhpDumperContainerStorage implements ContainerStorageInterface {

  /**
   * The cache prefix used to store container hash.
   */
  protected const string CACHE_PREFIX = 'container_hash:';

  /**
   * Suffix appended to a container's confirmation marker file.
   */
  protected const string CONFIRMED_SUFFIX = '.confirmed';

  /**
   * Constructs a PhpDumperContainerStorage.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend for storing the container hash.
   * @param \Drupal\Component\PhpStorage\PhpStorageInterface $phpStorage
   *   The PHP storage for compiled container files.
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly PhpStorageInterface $phpStorage,
    protected readonly Settings $settings,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function load(string $identifier): ?ContainerInterface {
    $cache = $this->cache->get(static::CACHE_PREFIX . $identifier);
    if ($cache && $hash = $cache->data) {
      $containerClass = 'DrupalContainer_' . $hash;
      if (!class_exists($containerClass, FALSE)) {
        $this->phpStorage->load($containerClass . '.php');
      }
      if (class_exists($containerClass, FALSE)) {
        return new $containerClass();
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $identifier): void {
    // Strip leading backslash so the generated PHP code produces a correct
    // fully qualified class name (the dumper prepends its own backslash).
    $baseClass = ltrim($this->settings->get('container_base_class', PhpContainer::class), '\\');
    $dumper = new PhpDumper($container);
    $code = $dumper->dump([
      'class' => 'DrupalContainer',
      'base_class' => $baseClass,
    ]);

    // Hash the built container code to generate a unique class name. This
    // ensures that changes in the container definition result in a different
    // class name that can be loaded in the same request.
    $hash = hash('xxh128', $code);
    $containerClass = 'DrupalContainer_' . $hash;

    $replace_count = 0;
    $code = str_replace(
      "class DrupalContainer extends \\$baseClass\n{",
      "class $containerClass extends \\$baseClass\n{",
      $code,
      $replace_count,
    );
    if ($replace_count !== 1) {
      throw new \RuntimeException(sprintf('Expected to replace 1 occurrence of "DrupalContainer" with "%s" in the dumped container code.', $containerClass));
    }

    // Only write the container to disk if it does not already exist.
    $filename = $containerClass . '.php';
    if (!$this->phpStorage->exists($filename)) {
      if (!$this->phpStorage->save($filename, $code)) {
        return;
      }
    }

    // Publish the hash only after the file is written, so the cache never
    // points to a container that failed to save.
    $this->cache->set(static::CACHE_PREFIX . $identifier, $hash);

    // Mark the container confirmed only now that its hash has been
    // published. This marker is used by garbage collection to tell a fully
    // committed container that has since been superseded apart from one whose
    // dump() call has not finished publishing its hash yet.
    $this->phpStorage->save($containerClass . static::CONFIRMED_SUFFIX, '1');
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    $this->cache->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(string $identifier): void {
    $cache = $this->cache->get(static::CACHE_PREFIX . $identifier);
    if (!$cache || !($hash = $cache->data)) {
      return;
    }

    // Delete every confirmed container except the one currently in use. A
    // container without a confirmation marker may belong to a dump() call
    // still in progress on another request; see dump().
    $keep = 'DrupalContainer_' . $hash;
    foreach ($this->phpStorage->listAll() as $name) {
      if ($name === $keep || str_ends_with($name, static::CONFIRMED_SUFFIX)) {
        continue;
      }
      if (!$this->phpStorage->exists($name . static::CONFIRMED_SUFFIX)) {
        continue;
      }
      $this->phpStorage->delete($name);
      $this->phpStorage->delete($name . static::CONFIRMED_SUFFIX);
    }
  }

}
