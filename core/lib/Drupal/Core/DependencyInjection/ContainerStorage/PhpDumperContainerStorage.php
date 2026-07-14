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
        return new $containerClass();
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $suffix): bool {
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
    if (!$this->phpStorage->exists($filename) && !$this->phpStorage->save($filename, $code)) {
      return FALSE;
    }

    // Publish the hash only after the file is written, so the cache never
    // points to a container that failed to save. Superseded files are removed
    // later by ::garbageCollection().
    try {
      $this->cache->set(static::CACHE_PREFIX . $suffix, $hash);
    }
    catch (\Exception) {
      // The cache table may not exist yet (e.g., during install).
      return FALSE;
    }

    return TRUE;
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
    if (!$cache || !($hash = $cache->data)) {
      return;
    }

    // Delete every container older than the one currently in use.
    //
    // PhpDumperContainerStorage::dump() always writes its file before
    // publishing its hash to the cache, so a file at least as new as the one
    // being kept may belong to a PhpDumperContainerStorage::dump() call still
    // in progress on another request, whose cache write has not landed yet.
    // Comparing ages (rather than deleting everything that does not match the
    // current hash) avoids deleting that file without needing a lock.
    $keep = 'DrupalContainer_' . $hash;
    $keepPath = $this->phpStorage->getFullPath($keep);
    $keepMtime = $keepPath ? @filemtime($keepPath) : FALSE;
    if ($keepMtime === FALSE) {
      // The current container's age cannot be determined; skip this run
      // rather than risk deleting a file that is actually newer.
      return;
    }

    foreach ($this->phpStorage->listAll() as $name) {
      if ($name === $keep) {
        continue;
      }
      $path = $this->phpStorage->getFullPath($name);
      $mtime = $path ? @filemtime($path) : FALSE;
      if ($mtime !== FALSE && $mtime < $keepMtime) {
        $this->phpStorage->delete($name);
      }
    }
  }

}
