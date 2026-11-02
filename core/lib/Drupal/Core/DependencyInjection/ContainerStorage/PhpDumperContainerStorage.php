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
   * @param array $runtimeParameters
   *   (optional) Parameter values to set on the container when it is loaded,
   *   keyed by parameter name. These parameters are left out of the dumped
   *   container.
   *
   * @see \Drupal\Core\DependencyInjection\Compiler\RuntimeParametersPass
   */
  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly PhpStorageInterface $phpStorage,
    protected readonly Settings $settings,
    protected readonly array $runtimeParameters = [],
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
        return $this->createContainer($containerClass);
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $identifier): void {
    [$hash, $code] = $this->dumpCode($container);
    $containerClass = 'DrupalContainer_' . $hash;

    $filename = $containerClass . '.php';
    if (!$this->phpStorage->save($filename, $code)) {
      return;
    }

    // Record the hash only after the file is written, so the cache never
    // points to a container that failed to save.
    $this->cache->set(static::CACHE_PREFIX . $identifier, $hash);

    // Mark the container confirmed only now that its hash has been
    // published. This marker is used by garbage collection to tell a fully
    // committed container that has since been superseded apart from one whose
    // dump() call has not finished publishing its hash yet. Skip the write if
    // the marker is already there (e.g. dump() recompiled to the same hash
    // after invalidateContainer()).
    $confirmedFilename = $containerClass . static::CONFIRMED_SUFFIX;
    if (!$this->phpStorage->exists($confirmedFilename)) {
      $this->phpStorage->save($confirmedFilename, '1');
    }
  }

  /**
   * Creates a container from a loaded container class.
   *
   * @param string $containerClass
   *   The container class name.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container.
   */
  protected function createContainer(string $containerClass): ContainerInterface {
    $container = new $containerClass();
    $container->setRuntimeParameters($this->runtimeParameters);
    return $container;
  }

  /**
   * Generates the PHP code of a container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The compiled container builder.
   *
   * @return array{string, string}
   *   The hash of the code, which is part of the container class name, and the
   *   code.
   */
  protected function dumpCode(ContainerBuilder $container): array {
    // Strip leading backslash so the generated PHP code produces a correct
    // fully qualified class name (the dumper prepends its own backslash).
    $baseClass = ltrim($this->settings->get('container_base_class', PhpContainer::class), '\\');
    // Hide the runtime parameters, so that the dumped code reads them from
    // the container instead of containing their values.
    $code = $container->withoutParameters(array_keys($this->runtimeParameters), fn (ContainerBuilder $container): string => (new PhpDumper($container))->dump([
      'class' => 'DrupalContainer',
      'base_class' => $baseClass,
    ]));

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

    return [$hash, $code];
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
    // Remove outdated copies left behind when a container file is saved again.
    $this->phpStorage->garbageCollection();

    $cache = $this->cache->get(static::CACHE_PREFIX . $identifier);
    if (!$cache || !($hash = $cache->data)) {
      return;
    }

    // Delete every confirmed container except the one currently in use. A
    // container without a confirmation marker may belong to a dump() call
    // still in progress on another request; see dump().
    $keep = 'DrupalContainer_' . $hash;
    foreach ($this->phpStorage->listAll() as $name) {
      // PhpStorageInterface implementations disagree on whether listAll()
      // strips the '.php' extension: MTimeProtectedFileStorage does (it
      // stores each file in a directory named after the virtual name minus
      // '.php'), but FileStorage does not. Normalize to the bare class name
      // for comparisons, but keep deleting the container file by its
      // original $name so FileStorage, which requires an exact filename
      // match, still finds it.
      $class_name = str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;
      if ($class_name === $keep || str_ends_with($class_name, static::CONFIRMED_SUFFIX)) {
        continue;
      }
      if (!$this->phpStorage->exists($class_name . static::CONFIRMED_SUFFIX)) {
        continue;
      }
      $this->phpStorage->delete($name);
      $this->phpStorage->delete($class_name . static::CONFIRMED_SUFFIX);
    }
  }

}
