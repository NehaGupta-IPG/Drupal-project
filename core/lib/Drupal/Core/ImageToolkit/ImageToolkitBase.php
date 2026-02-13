<?php

namespace Drupal\Core\ImageToolkit;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Psr\Log\LoggerInterface;

/**
 * Provides a base class for image toolkit plugins.
 *
 * @see \Drupal\Core\ImageToolkit\Annotation\ImageToolkit
 * @see \Drupal\Core\ImageToolkit\ImageToolkitInterface
 * @see \Drupal\Core\ImageToolkit\ImageToolkitManager
 * @see plugin_api
 */
abstract class ImageToolkitBase extends PluginBase implements ImageToolkitInterface, ContainerFactoryPluginInterface {

  /**
   * An ImageInterface object to be used for BC.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play
   *    whack-a-mole instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  public ?ImageInterface $backfillImage;

  /**
   * Path of the image file.
   *
   * @var string
   *
   * @deprecated
   */
  protected $source = '';

  /**
   * Constructs an ImageToolkitBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\ImageToolkit\ImageToolkitOperationManagerInterface $operationManager
   *   The toolkit operation manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly ImageToolkitOperationManagerInterface $operationManager,
    protected readonly LoggerInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function setSource($source) {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play whack-a-mole instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    // If a previous image has been loaded, there is no way to know if the
    // toolkit implementation needs to perform any additional actions like
    // freeing memory. Therefore, the source image cannot be changed once set.
    if ($this->source) {
      throw new \BadMethodCallException(__METHOD__ . '() may only be called once');
    }
    $this->source = $source;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play whack-a-mole instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirements() {
    return [];
  }

  /**
   * Gets a toolkit operation plugin instance.
   *
   * @param string $operation
   *   The toolkit operation requested.
   *
   * @return \Drupal\Core\ImageToolkit\ImageToolkitOperationInterface
   *   An instance of the requested toolkit operation plugin.
   */
  protected function getToolkitOperation($operation) {
    return $this->operationManager->getToolkitOperation($this, $operation);
  }

  /**
   * {@inheritdoc}
   */
  public function apply($operation, $arguments = [], $extra = NULL) {
    if ($operation instanceof ImageInterface) {
      $image = $operation;
      $operation = $arguments;
      assert(is_string($operation));
      $arguments = $extra;
      assert(is_array($arguments));
    }
    else {
      $image = $this->backfillImage;
    }
    try {
      // Get the plugin to use for the operation and apply the operation.
      return $this->getToolkitOperation($operation)->apply($image, $arguments);
    }
    catch (PluginNotFoundException) {
      $this->logger->error("The selected image handling toolkit '@toolkit' can not process operation '@operation'.", [
        '@toolkit' => $this->getPluginId(),
        '@operation' => $operation,
      ]);
      return FALSE;
    }
    catch (\Throwable $t) {
      $this->logger->warning("The image toolkit '@toolkit' failed processing '@operation' for image '@image'. Reported error: @class - @message", [
        '@toolkit' => $this->getPluginId(),
        '@operation' => $operation,
        // @todo fix.
        // @phpstan-ignore method.deprecated
        '@image' => $this->getSource(),
        '@class' => get_class($t),
        '@message' => $t->getMessage(),
      ]);
      return FALSE;
    }
  }

}
