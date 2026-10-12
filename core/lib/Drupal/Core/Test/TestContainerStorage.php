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
 * Identifiers that start with 'shared:' are shared by all tests, so they must
 * change whenever the compiled container would change. Test kernels build them
 * from everything the compiled container depends on. Other identifiers belong
 * to the site that stores them, like those of any other container storage: a
 * functional test and its child site use them to find the container of the
 * test site.
 *
 * Containers are never deleted: the test runner removes the directory after
 * the run.
 *
 * @see \Drupal\Core\Test\SharedContainerKernelTrait
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
   * Returns the bootstrap container definition of this container storage.
   *
   * @param string $directory
   *   The directory to store containers in.
   *
   * @return array
   *   The service definition.
   *
   * @see \Drupal\Core\DrupalKernel::$defaultBootstrapContainerDefinition
   */
  public static function getServiceDefinition(string $directory): array {
    return [
      'class' => static::class,
      'arguments' => [
        $directory,
        '@settings',
        [
          'app.root' => '%app.root%',
          'site.path' => '%site.path%',
        ],
      ],
    ];
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
    // storage, which belongs to the test that compiled it. A container of
    // this site was compiled or loaded by this site, which wrote them.
    // @see \Drupal\Core\Hook\HookCollectorKeyValueWritePass
    if ($this->isShared($identifier)) {
      $container->get('keyvalue')->get('hook_data')->setMultiple($hook_data);
      $container->get('cache.bootstrap')->deleteMultiple(['hook_data', 'theme_hook_data']);
    }

    return $container;
  }

  /**
   * Stores a container under another identifier as well.
   *
   * @param string $identifier
   *   The identifier to add.
   * @param string $existing_identifier
   *   The identifier of a stored container.
   */
  public function addIdentifier(string $identifier, string $existing_identifier): void {
    $data = @file_get_contents($this->getIdentifierPath($existing_identifier));
    if ($data !== FALSE) {
      $this->write($this->getIdentifierPath($identifier), $data);
    }
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
    // Shared identifiers change whenever their container would change, so
    // only the identifiers of this site are removed.
    $prefix = $this->getSitePrefix();
    foreach (scandir($this->directory) ?: [] as $filename) {
      if (str_starts_with($filename, $prefix)) {
        @unlink($this->directory . '/' . $filename);
      }
    }
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
    $prefix = $this->isShared($identifier) ? '' : $this->getSitePrefix();
    return $this->directory . '/' . $prefix . hash('xxh128', $identifier) . '.id';
  }

  /**
   * Returns whether an identifier is shared by all tests.
   *
   * @param string $identifier
   *   The container identifier.
   *
   * @return bool
   *   TRUE if the identifier is shared by all tests, FALSE if it belongs to the
   *   site.
   */
  protected function isShared(string $identifier): bool {
    return str_starts_with($identifier, 'shared:');
  }

  /**
   * Returns the file name prefix of the identifiers of this site.
   *
   * @return string
   *   The prefix.
   */
  protected function getSitePrefix(): string {
    return 'site-' . hash('xxh128', $this->runtimeParameters['site.path'] ?? '') . '-';
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
