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
 * backend to map the cache key suffix to the correct container class.
 */
class PhpDumperContainerStorage implements ContainerStorageInterface {

  /**
   * The cache prefix used to store container hash.
   */
  protected const string CACHE_PREFIX = 'container_hash:';

  /**
   * The cache key suffix of the currently loaded container.
   */
  protected ?string $loadedSuffix = NULL;

  /**
   * The hash of the currently loaded container.
   */
  protected ?string $loadedContainerHash = NULL;

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
  public function load(string $suffix): ?ContainerInterface {
    $cache = $this->cache->get(static::CACHE_PREFIX . $suffix);
    if ($cache && $hash = $cache->data) {
      $containerClass = 'DrupalContainer_' . $hash;
      if (!class_exists($containerClass, FALSE)) {
        $this->phpStorage->load($containerClass . '.php');
      }
      if (class_exists($containerClass, FALSE)) {
        $this->loadedSuffix = $suffix;
        $this->loadedContainerHash = $hash;
        return new $containerClass();
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $suffix): ContainerInterface {
    $this->loadedSuffix = $suffix;

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
    $classExists = class_exists($containerClass, FALSE);

    // There is no change from the loaded container, so there is nothing to do.
    if ($hash === $this->loadedContainerHash) {
      return new $containerClass();
    }

    $filename = $containerClass . '.php';

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
    if (!$classExists || !$this->phpStorage->exists($filename)) {
      if ($this->phpStorage->save($filename, $code) === FALSE) {
        // Calling save() failed; another worker may already be writing this
        // file.
        return $container;
      }
    }
    // Only include if the class is not already in memory. If it is, the
    // class was defined in a prior kernel lifecycle (e.g. the kernel was
    // booted, terminated, then booted again). MTimeProtectedFileStorage
    // writes each save() to a new mtime-based directory, so include_once
    // on that new path would trigger "Cannot redeclare class".
    if (!$classExists) {
      if ($this->phpStorage->load($filename) === FALSE) {
        // If the file was not loaded, it is not yet in memory.
        return $container;
      }
    }

    $this->loadedContainerHash = $hash;

    // Publish the hash only after the file is written, so the cache never
    // points to a container that failed to save. Superseded files are removed
    // later by ::garbageCollection().
    try {
      $this->cache->set(static::CACHE_PREFIX . $suffix, $hash);
    }
    catch (\Exception) {
      // The cache table may not exist yet (e.g., during install).
    }

    return new $containerClass();
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
  public function garbageCollection(): void {
    if ($this->loadedSuffix === NULL) {
      return;
    }
    $cache = $this->cache->get(static::CACHE_PREFIX . $this->loadedSuffix);
    if ($cache && $hash = $cache->data) {
      // Match the compiled container names only. This excludes sibling files
      // such as the '.php.lock' files created by locking storage backends.
      $pattern = '/^DrupalContainer_[0-9a-f]+$/';
      $keep = 'DrupalContainer_' . $hash;
      foreach ($this->phpStorage->listAll() as $name) {
        if ($name !== $keep && preg_match($pattern, $name) === 1) {
          $this->phpStorage->delete($name);
        }
      }
    }
  }

}
