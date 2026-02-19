<?php

namespace Drupal\Core\ImageToolkit;

/**
 * Defines an interface for image toolkit operation managers.
 */
interface ImageToolkitOperationManagerInterface {

  /**
   * Returns a toolkit operation plugin instance.
   *
   * @param ImageToolkitInterface $toolkit
   *   The toolkit instance.
   * @param string $operation
   *   The operation (e.g. "crop").
   *
   * @return ImageToolkitOperationInterface
   *   An instance of the requested toolkit operation plugin.
   *
   * @throws PluginNotFoundException
   *   When no plugin is available.
   */
  public function getToolkitOperation(ImageToolkitInterface $toolkit, $operation);

}
