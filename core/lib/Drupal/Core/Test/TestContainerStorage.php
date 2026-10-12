<?php

declare(strict_types=1);

namespace Drupal\Core\Test;

use Drupal\Component\PhpStorage\FileStorage;
use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Stores compiled containers in a directory shared by the tests of a run.
 *
 * Each container is stored in a file named after the hash of its code, so
 * tests that compile the same container write the same file. A second file,
 * named after the hash of the container identifier, records which container
 * the identifier maps to, and the hook implementations that compiling the
 * container wrote to key value storage. Files are written to a temporary file
 * first and then renamed, so that tests running in parallel never read a
 * partial file.
 *
 * Nothing is ever deleted: the test runner removes the directory after the
 * run. The identifier must change whenever the compiled container would
 * change, because a stored container is never replaced by a different one.
 *
 * @internal
 */
class TestContainerStorage extends PhpDumperContainerStorage {

  /**
   * Constructs a TestContainerStorage.
   *
   * @param string $directory
   *   The directory to store containers in.
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings.
   * @param array $runtimeParameters
   *   (optional) Parameter values to set on the container when it is loaded,
   *   keyed by parameter name. These parameters are left out of the dumped
   *   container.
   */
  public function __construct(
    protected readonly string $directory,
    Settings $settings,
    array $runtimeParameters = [],
  ) {
    // The parent class stores the container hash in a cache backend and the
    // container in PHP storage. This class does neither.
    parent::__construct(new NullBackend('container'), new FileStorage(['directory' => $directory, 'bin' => 'container']), $settings, $runtimeParameters);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $identifier): ?ContainerInterface {
    $data = @file_get_contents($this->getIdentifierPath($identifier));
    if ($data === FALSE) {
      return NULL;
    }
    ['hash' => $hash, 'hook_data' => $hook_data] = unserialize($data, ['allowed_classes' => FALSE]);
    $containerClass = 'DrupalContainer_' . $hash;
    if (!class_exists($containerClass, FALSE)) {
      if (!is_file($this->getContainerPath($hash))) {
        return NULL;
      }
      require $this->getContainerPath($hash);
    }
    $container = $this->createContainer($containerClass);

    // Compiling the container writes the hook implementations to key value
    // storage, which belongs to the test that compiled it.
    // @see \Drupal\Core\Hook\HookCollectorKeyValueWritePass
    $container->get('keyvalue')->get('hook_data')->setMultiple($hook_data);
    $container->get('cache.bootstrap')->deleteMultiple(['hook_data', 'theme_hook_data']);

    return $container;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(ContainerBuilder $container, string $identifier): void {
    try {
      [$hash, $code] = $this->dumpCode($container);
    }
    catch (RuntimeException) {
      // The container cannot be dumped, for example because a service argument
      // is an object. The test uses the container builder instead.
      return;
    }
    if (!file_exists($this->getContainerPath($hash))) {
      $this->write($this->getContainerPath($hash), $code);
    }
    $this->write($this->getIdentifierPath($identifier), serialize([
      'hash' => $hash,
      'hook_data' => $container->get('keyvalue')->get('hook_data')->getAll(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    // Stored containers are shared with other tests, so they are never
    // invalidated. A test that changes its container rebuilds it.
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(string $identifier): void {
    // The test runner removes the directory after the run.
  }

  /**
   * Returns the path of the file that records a container identifier.
   *
   * @param string $identifier
   *   The container identifier.
   *
   * @return string
   *   The path.
   */
  protected function getIdentifierPath(string $identifier): string {
    return $this->directory . '/' . hash('xxh128', $identifier) . '.id';
  }

  /**
   * Returns the path of the file that stores a container.
   *
   * @param string $hash
   *   The hash of the container code.
   *
   * @return string
   *   The path.
   */
  protected function getContainerPath(string $hash): string {
    return $this->directory . '/DrupalContainer_' . $hash . '.php';
  }

  /**
   * Writes a file so that other processes never read it partially written.
   *
   * @param string $path
   *   The path of the file.
   * @param string $data
   *   The data to write.
   */
  protected function write(string $path, string $data): void {
    $temporary_path = $path . '.' . bin2hex(random_bytes(8));
    if (file_put_contents($temporary_path, $data) === FALSE || !rename($temporary_path, $path)) {
      @unlink($temporary_path);
      throw new \RuntimeException(sprintf('Unable to write %s.', $path));
    }
  }

}
