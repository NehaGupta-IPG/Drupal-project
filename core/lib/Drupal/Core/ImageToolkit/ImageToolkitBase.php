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

  // @todo remove ignoreFile once PHPCS can parse property hooks.
  // phpcs:ignoreFile

  /**
   * BC layer.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Only
   *   introduced to support backwards compatibility; there is no replacement.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public protected(set) bool $isClone = FALSE;

  /**
   * An ImageInterface object to be used for BC.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Only
   *   introduced to support backwards compatibility; there is no replacement.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public ?ImageInterface $backfillImage {
    // @phpstan-ignore property.deprecated
    get => $this->backfillImage ?? NULL;
    set(?ImageInterface $image) {
      // @phpstan-ignore property.deprecated
      if (isset($this->getPluginDefinition()['imageClass']) && !$this->isClone) {
        throw new ImageToolkitException('Cannot set $backfillImage in the toolkit singleton');
      }
      if ($image === NULL) {
        throw new ImageToolkitException('$backfillImage cannot be set to NULL');
      }
      // @phpstan-ignore property.deprecated
      $this->backfillImage = $image;
    }
  }

  /**
   * Path of the image file.
   *
   * @var string
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access
   *   image source path from the ImageInterface object directly.
   *
   * @see https://www.drupal.org/node/3590417
   */
  protected $source {
    get {
      @trigger_error('Getting ' . __CLASS__ . '::$source is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access image source path from the ImageInterface object directly. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated,property.deprecated
      return isset($this->backfillImage) ? $this->backfillImage->getSource() : '';
    }
    set($source) {
      @trigger_error('Setting ' . __CLASS__ . '::$source is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Access image source path from the ImageInterface object directly. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
      // @phpstan-ignore property.deprecated
      $this->backfillImage->setSource($source);
    }
  }

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
   * BC layer.
   *
   * @todo remove from drupal:43.0.0.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public function __clone(): void {
    if (!isset($this->getPluginDefinition()['imageClass'])) {
      throw new ImageToolkitException('Cannot clone a stateful toolkit');
    }
    // @phpstan-ignore property.deprecated
    $this->isClone = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function setSource($source) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Set image source path via ImageInterface::setSource() instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
    $this->source = $source;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Get image source path via ImageInterface::getSource() instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
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
  public function apply(/* string */ $operation, array $arguments = [] /* , ImageInterface $image */) {
    // @todo BC layer - remove in drupal:43.0.0.
    $args = func_get_args();
    $image = $args[2] ?? NULL;
    // @phpstan-ignore property.deprecated
    $image ??= $this->backfillImage;
    assert($image instanceof ImageInterface);

    try {
      // Get the plugin to use for the operation and apply the operation.
      $operation = $this->getToolkitOperation($operation);
      return $operation->apply($image, $arguments);
    }
    catch (PluginNotFoundException) {
      $this->logger->error("The selected image handling toolkit '@toolkit' can not process operation '@operation'.", [
        '@toolkit' => $this->getPluginId(),
        '@operation' => $operation,
      ]);
      return FALSE;
    }
    catch (ImageToolkitException $e) {
      $this->logger->error("Error handling image with toolkit '@toolkit' for operation '@operation': @message", [
        '@toolkit' => $this->getPluginId(),
        '@operation' => $operation,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
    catch (\Throwable $t) {
      $this->logger->warning("The image toolkit '@toolkit' failed processing '@operation' for image '@image'. Reported error: @class - @message", [
        '@toolkit' => $this->getPluginId(),
        '@operation' => $operation,
        // @todo remove call to $this->getSource() in drupal:43.0.0.
        // @phpstan-ignore method.deprecated
        '@image' => $image ? $image->getSource() : $this->getSource(),
        '@class' => get_class($t),
        '@message' => $t->getMessage(),
      ]);
      return FALSE;
    }
  }

}
