<?php

namespace Drupal\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorageInterface;
use Drupal\Core\DependencyInjection\Dumper\FileLoaderStub;
use Drupal\Core\DrupalKernelInterface;
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
class PhpDumperContainerStorage implements ContainerStorageInterface {

  /**
   * The cache prefix used to store container hash.
   */
  protected const string CACHE_PREFIX = 'container_hash:';

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
   * @param string $baseClass
   *   The base class for the dumped container.
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly PhpStorageInterface $phpStorage,
    protected readonly string $baseClass,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function load(string $suffix, DrupalKernelInterface $kernel): ?ContainerInterface {
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
  public function dump(ContainerBuilder $container, string $suffix, DrupalKernelInterface $kernel): ContainerInterface {
    // Ensure FileLoader::ANONYMOUS_ID_REGEXP is available for PhpDumper.
    // Check both the real dependency (symfony/config FileLoader) and the alias
    // target to avoid "Cannot redeclare class" warnings on repeated dumps.
    if (!class_exists('Symfony\Component\Config\Loader\FileLoader') && !class_exists('Symfony\Component\DependencyInjection\Loader\FileLoader', FALSE)) {
      class_alias(FileLoaderStub::class, 'Symfony\Component\DependencyInjection\Loader\FileLoader');
    }

    // Strip leading backslash so the generated PHP code produces a correct
    // fully qualified class name (the dumper prepends its own backslash).
    $baseClass = ltrim($this->baseClass, '\\');
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
    if ($hash !== $this->loadedContainerHash && !class_exists($containerClass, FALSE)) {
      if ($this->loadedContainerHash) {
        $this->phpStorage->delete('DrupalContainer_' . $this->loadedContainerHash . '.php');
      }
      $filename = $containerClass . '.php';

      $code = str_replace(
        "class DrupalContainer extends \\$baseClass\n{",
        "class $containerClass extends \\$baseClass\n{",
        $code,
      );
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

}
