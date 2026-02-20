<?php

namespace Drupal\Core\ImageToolkit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\ImageToolkit\Attribute\ImageToolkit;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages image toolkit plugins.
 *
 * @see \Drupal\Core\ImageToolkit\Annotation\ImageToolkit
 * @see \Drupal\Core\ImageToolkit\ImageToolkitInterface
 * @see \Drupal\Core\ImageToolkit\ImageToolkitBase
 * @see plugin_api
 */
class ImageToolkitManager extends DefaultPluginManager {

  /**
   * The map of active toolkit singleton instances, keyed by plugin ID.
   *
   * @var array<string, ImageToolkitInterface>
   */
  protected array $activeToolkits = [];

  /**
   * Constructs the ImageToolkitManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct(
      'Plugin/ImageToolkit',
      $namespaces,
      $module_handler,
      ImageToolkitInterface::class,
      ImageToolkit::class,
      'Drupal\Core\ImageToolkit\Annotation\ImageToolkit',
    );

    $this->setCacheBackend($cache_backend, 'image_toolkit_plugins');
  }

  /**
   * Returns an image toolkit active instance.
   *
   * It would be the singleton of stateless toolkit, or a new instance of the
   * toolkit for stateful ones. In drupal:43.0.0 only stateless toolkits will
   * be accepted.
   *
   * @param string $id
   *   The ID of the image toolkit.
   *
   * @return \Drupal\Core\ImageToolkit\ImageToolkitInterface
   *   The toolkit.
   */
  public function getToolkit(string $id): ImageToolkitInterface {
    if (isset($this->activeToolkits[$id])) {
      return $this->activeToolkits[$id];
    }
    $toolkit = $this->createInstance($id);
    if (!$toolkit->isStateful) {
      $this->activeToolkits[$id] = $toolkit;
    }
    return $toolkit;
  }

  /**
   * Gets the default image toolkit ID.
   *
   * @return string|false
   *   ID of the default toolkit, or FALSE on error.
   */
  public function getDefaultToolkitId() {
    $toolkit_id = $this->configFactory->get('system.image')->get('toolkit');
    $toolkits = $this->getAvailableToolkits();

    if (!isset($toolkits[$toolkit_id]) || !class_exists($toolkits[$toolkit_id]['class'])) {
      // The selected toolkit isn't available so return the first one found. If
      // none are available this will return FALSE.
      $toolkit_id = array_key_first($toolkits);
    }

    return $toolkit_id;
  }

  /**
   * Gets the default image toolkit.
   *
   * @return \Drupal\Core\ImageToolkit\ImageToolkitInterface
   *   Object of the default toolkit, or FALSE on error.
   */
  public function getDefaultToolkit() {
    if ($toolkit_id = $this->getDefaultToolkitId()) {
      return $this->getToolkit($toolkit_id);
    }
    return FALSE;
  }

  /**
   * Gets a list of available toolkits.
   *
   * @return array
   *   An array with the toolkit names as keys and the descriptions as values.
   */
  public function getAvailableToolkits() {
    // Use plugin system to get list of available toolkits.
    $toolkits = $this->getDefinitions();

    $output = [];
    foreach ($toolkits as $id => $definition) {
      // Only allow modules that aren't marked as unavailable.
      if (call_user_func($definition['class'] . '::isAvailable')) {
        $output[$id] = $definition;
      }
    }

    return $output;
  }

  /**
   * Returns all valid extensions.
   *
   * @return string[]
   *   All possible valid extensions.
   *
   * @see \Drupal\image\Plugin\ImageEffect\ConvertImageEffect::buildConfigurationForm()
   *
   * @internal
   * @todo Revisit in https://www.drupal.org/node/3446364
   */
  public static function getAllValidExtensions(): array {
    return \Drupal::service('image.toolkit.manager')->getDefaultToolkit()->getSupportedExtensions();
  }

}
