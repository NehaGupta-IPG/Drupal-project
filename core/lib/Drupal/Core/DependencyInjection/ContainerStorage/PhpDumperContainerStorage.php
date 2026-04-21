<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\DependencyInjection\Dumper\FileLoaderStub;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

/**
 * Container storage using Symfony's PhpDumper and PhpStorage.
 *
 * Compiles the container into a PHP class that benefits from opcache. The
 * compiled file is stored via PhpStorage (typically MTimeProtectedFileStorage)
 * and a hash is kept in the cache backend to map the cache key suffix to the
 * correct container class.
 */
class PhpDumperContainerStorage implements ContainerStorageInterface, DestructableInterface {

  /**
   * The cache prefix used to store container hash.
   */
  protected const string CACHE_PREFIX = 'container_hash:';

  /**
   * The hash of the currently loaded container.
   */
  protected ?string $loadedContainerHash = NULL;

  /**
   * An array of old hashes, keyed by the hash.
   *
   * @var true[]
   */
  protected array $oldHashes = [];

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
    // Ensure FileLoader::ANONYMOUS_ID_REGEXP is available for PhpDumper.
    // Check both the real dependency (symfony/config FileLoader) and the alias
    // target to avoid "Cannot redeclare class" warnings on repeated dumps.
    if (!class_exists('Symfony\Component\Config\Loader\FileLoader') && !class_exists('Symfony\Component\DependencyInjection\Loader\FileLoader', FALSE)) {
      class_alias(FileLoaderStub::class, 'Symfony\Component\DependencyInjection\Loader\FileLoader');
    }

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

    // If the hash already by used and marked for deletion, re-use the container
    // file and class. This can occur when a container is rebuilt multiple times
    // during module installation and a later rebuild produces the same hash as
    // an earlier one.
    if ($hash !== $this->loadedContainerHash && isset($this->oldHashes[$hash]) && class_exists($containerClass, FALSE)) {
      unset($this->oldHashes[$hash]);
      if ($this->loadedContainerHash) {
        $this->oldHashes[$this->loadedContainerHash] = TRUE;
      }
      $this->loadedContainerHash = $hash;
    }
    elseif ($hash !== $this->loadedContainerHash) {
      if ($this->loadedContainerHash) {
        $this->oldHashes[$this->loadedContainerHash] = TRUE;
      }
      $filename = $containerClass . '.php';

      $code = str_replace(
        "class DrupalContainer extends \\$baseClass\n{",
        "class $containerClass extends \\$baseClass\n{",
        $code,
      );
      // Always save the file so it exists on disk for future requests.
      $this->phpStorage->save($filename, $code);
      $this->phpStorage->load($filename);
      $this->loadedContainerHash = $hash;
    }

    $container = new $containerClass();

    try {
      $this->cache->set(static::CACHE_PREFIX . $suffix, $hash);
    }
    catch (\Exception) {
      // The cache table may not exist yet (e.g., during install).
    }

    return $container;
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
  public function destruct(): void {
    // Delete any files corresponding to old hashes that have accumulated.
    foreach (array_keys($this->oldHashes) as $hash) {
      $this->phpStorage->delete('DrupalContainer_' . $hash . '.php');
      unset($this->oldHashes[$hash]);
    }
  }

}
