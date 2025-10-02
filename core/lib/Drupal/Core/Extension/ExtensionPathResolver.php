<?php

namespace Drupal\Core\Extension;

use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\Exception\UnknownExtensionTypeException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class for getting extension directories and info paths.
 *
 * This uses a service locator to gather all extension lists. You can use this
 * to get the directory or info file path for a given type and machine name of
 * an extension.
 */
class ExtensionPathResolver {

  public function __construct(
    #[AutowireLocator('extension_list', 'type')]
    protected ContainerInterface $extensionLists,
  ) {}

  /**
   * Gets the info file path for the extension.
   *
   * @param string $type
   *   The extension type.
   * @param string $name
   *   The extension name.
   *
   * @return string|null
   *   The extension path, or NULL if unknown.
   */
  public function getPathname(string $type, string $name): ?string {
    if ($type === 'core') {
      return 'core/core.info.yml';
    }
    try {
      return $this->extensionLists->get($type)->getPathname($name);
    }
    catch (ServiceNotFoundException) {
      throw new UnknownExtensionTypeException(sprintf('Extension type %s is unknown.', $type));
    }
    catch (UnknownExtensionException) {
      // Catch the exception. This will result in triggering an error.
      // If the filename is still unknown, create a user-level error message.
      trigger_error(sprintf('The following %s is missing from the file system: %s', $type, $name), E_USER_WARNING);
      return NULL;
    }
  }

  /**
   * Gets the extension directory path.
   *
   * @param string $type
   *   The extension type.
   * @param string $name
   *   The extension name.
   *
   * @return string
   *   The extension info file path.
   *
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionTypeException
   *   If the extension type is unknown.
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionException
   *   If the extension is unknown.
   */
  public function getPath(string $type, string $name): string {
    return dirname($this->getPathname($type, $name));
  }

}
