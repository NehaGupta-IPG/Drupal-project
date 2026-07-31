<?php

namespace Drupal\layout_builder;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;

/**
 * Provides a value object for a section component.
 *
 * A component represents the smallest part of a layout (for example, a block).
 * Components wrap a renderable plugin, currently using
 * \Drupal\Core\Block\BlockPluginInterface, and contain the layout region
 * within the section layout where the component will be rendered.
 *
 * @see \Drupal\Core\Layout\LayoutDefinition
 * @see \Drupal\layout_builder\Section
 * @see \Drupal\layout_builder\SectionStorageInterface
 */
class SectionComponent implements ThirdPartySettingsInterface {

  /**
   * The UUID of the component.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The region the component is placed in.
   *
   * @var string
   */
  protected $region;

  /**
   * An array of plugin configuration.
   *
   * @var mixed[]
   */
  protected $configuration;

  /**
   * The weight of the component.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * Any additional properties and values.
   *
   * @var mixed[]
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0.
   * Additional component properties should be set via ::setThirdPartySetting().
   *
   * @see https://www.drupal.org/node/3100177
   */
  protected $additional = [];

  /**
   * Third party settings.
   *
   * An array of key/value pairs keyed by provider.
   *
   * @var mixed[]
   */
  protected array $thirdPartySettings = [];

  /**
   * Constructs a new SectionComponent.
   *
   * @param string $uuid
   *   The UUID.
   * @param string $region
   *   The region.
   * @param mixed[] $configuration
   *   The plugin configuration.
   * @param mixed[] $additional
   *   (optional) Additional values.
   * @param array[] $thirdPartySettings
   *   (optional) Any third party settings.
   */
  public function __construct($uuid, $region, array $configuration = [], array $additional = [], array $thirdPartySettings = []) {
    $this->uuid = $uuid;
    $this->region = $region;
    $this->configuration = $configuration;
    // @phpstan-ignore-next-line
    $this->additional = $additional;
    if ($additional !== []) {
      @trigger_error('Setting additional properties is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. Additional component properties should be set via ::setThirdPartySetting(). See https://www.drupal.org/node/3100177', E_USER_DEPRECATED);
    }
    $this->thirdPartySettings = $thirdPartySettings;
  }

  /**
   * Returns the renderable array for this component.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   An array of available contexts.
   * @param bool $in_preview
   *   TRUE if the component is being previewed, FALSE otherwise.
   *
   * @return array
   *   A renderable array representing the content of the component.
   */
  public function toRenderArray(array $contexts = [], $in_preview = FALSE) {
    $event = new SectionComponentBuildRenderArrayEvent($this, $contexts, $in_preview);
    $this->eventDispatcher()->dispatch($event, LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY);
    $output = $event->getBuild();
    $event->getCacheableMetadata()->applyTo($output);
    return $output;
  }

  /**
   * Gets any arbitrary property for the component.
   *
   * @param string $property
   *   The property to retrieve.
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:12.0.0.
   * Additional properties should be fetched via ::getThirdPartySetting().
   *
   * @see https://www.drupal.org/node/3100177
   *
   * @return mixed
   *   The value for that property, or NULL if the property does not exist.
   */
  public function get($property) {
    if (property_exists($this, $property)) {
      $value = $this->{$property} ?? NULL;
    }
    else {
      $value = $this->additional[$property] ?? NULL;
    }
    @trigger_error('Getting additional properties is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. Additional component properties should be gotten via ::getThirdPartySetting(). See https://www.drupal.org/node/3100177', E_USER_DEPRECATED);
    return $value;
  }

  /**
   * Sets a value to an arbitrary property for the component.
   *
   * @param string $property
   *   The property to use for the value.
   * @param mixed $value
   *   The value to set.
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:12.0.0.
   * Additional properties should be set via ::setThirdPartySetting().
   *
   * @see https://www.drupal.org/node/3100177
   *
   * @return $this
   */
  public function set($property, $value) {
    if (property_exists($this, $property)) {
      $this->{$property} = $value;
    }
    else {
      $this->additional[$property] = $value;
    }
    @trigger_error('Setting random section component properties is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. Component properties should be set via dedicated setters. See https://www.drupal.org/node/3100177', E_USER_DEPRECATED);
    return $this;
  }

  /**
   * Gets the region for the component.
   *
   * @return string
   *   The region.
   */
  public function getRegion() {
    return $this->region;
  }

  /**
   * Sets the region for the component.
   *
   * @param string $region
   *   The region.
   *
   * @return $this
   */
  public function setRegion($region) {
    $this->region = $region;
    return $this;
  }

  /**
   * Gets the weight of the component.
   *
   * @return int
   *   The zero-based weight of the component.
   *
   * @throws \UnexpectedValueException
   *   Thrown if the weight was never set.
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * Sets the weight of the component.
   *
   * @param int $weight
   *   The zero-based weight of the component.
   *
   * @return $this
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * Gets the component plugin configuration.
   *
   * @return mixed[]
   *   The component plugin configuration.
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Sets the plugin configuration.
   *
   * @param mixed[] $configuration
   *   The plugin configuration.
   *
   * @return $this
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
    return $this;
  }

  /**
   * Gets the plugin ID.
   *
   * @return string
   *   The plugin ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   Thrown if the plugin ID cannot be found.
   */
  public function getPluginId() {
    if (empty($this->configuration['id'])) {
      throw new PluginException(sprintf('No plugin ID specified for component with "%s" UUID', $this->uuid));
    }
    return $this->configuration['id'];
  }

  /**
   * Gets the UUID for this component.
   *
   * @return string
   *   The UUID.
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * Gets the plugin for this component.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts to set on the plugin.
   *
   * @return \Drupal\Component\Plugin\PluginInspectionInterface
   *   The plugin.
   */
  public function getPlugin(array $contexts = []) {
    $plugin = $this->pluginManager()->createInstance($this->getPluginId(), $this->getConfiguration());
    if ($contexts && $plugin instanceof ContextAwarePluginInterface) {
      $this->contextHandler()->applyContextMapping($plugin, $contexts);
    }
    return $plugin;
  }

  /**
   * Wraps the component plugin manager.
   *
   * @return \Drupal\Core\Block\BlockManagerInterface
   *   The plugin manager.
   */
  protected function pluginManager() {
    // @todo Figure out the best way to unify fields and blocks and components
    //   in https://www.drupal.org/node/1875974.
    return \Drupal::service('plugin.manager.block');
  }

  /**
   * Wraps the context handler.
   *
   * @return \Drupal\Core\Plugin\Context\ContextHandlerInterface
   *   The context handler.
   */
  protected function contextHandler() {
    return \Drupal::service('context.handler');
  }

  /**
   * Wraps the event dispatcher.
   *
   * @return \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher() {
    return \Drupal::service('event_dispatcher');
  }

  /**
   * Returns an array representation of the section component.
   *
   * Only use this method if you are implementing custom storage for sections.
   *
   * @return array
   *   An array representation of the section component.
   */
  public function toArray() {
    return [
      'uuid' => $this->getUuid(),
      'region' => $this->getRegion(),
      'configuration' => $this->getConfiguration(),
      'weight' => $this->getWeight(),
      // @todo Remove below key/value in https://www.drupal.org/project/drupal/issues/3160644.
      // @phpstan-ignore-next-line
      'additional' => $this->additional,
      'third_party_settings' => $this->thirdPartySettings,
    ];
  }

  /**
   * Creates an object from an array representation of the section component.
   *
   * Only use this method if you are implementing custom storage for sections.
   *
   * @param array $component
   *   An array of section component data in the format returned by ::toArray().
   *
   * @return static
   *   The section component object.
   */
  public static function fromArray(array $component) {
    // Ensure expected array keys are present.
    $component += [
      'uuid' => '',
      'region' => '',
      'configuration' => [],
      // @todo Remove below key/value in https://www.drupal.org/project/drupal/issues/3160644.
      'additional' => [],
      'third_party_settings' => [],
    ];
    return (new static(
      $component['uuid'],
      $component['region'],
      $component['configuration'],
      // @todo Remove below key/value in https://www.drupal.org/project/drupal/issues/3160644.
      $component['additional'],
      $component['third_party_settings']
    ))->setWeight($component['weight']);
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartySetting($provider, $key, $default = NULL) {
    return $this->thirdPartySettings[$provider][$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getThirdPartySettings($provider) {
    return $this->thirdPartySettings[$provider] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySetting($provider, $key, $value) {
    $this->thirdPartySettings[$provider][$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unsetThirdPartySetting($provider, $key) {
    unset($this->thirdPartySettings[$provider][$key]);
    // If the third party is no longer storing any information, completely
    // remove the array holding the settings for this provider.
    if (empty($this->thirdPartySettings[$provider])) {
      unset($this->thirdPartySettings[$provider]);
    }
    return $this;
  }

  /**
   * Gets the list of third parties that store information.
   *
   * @return array
   *   The list of third parties.
   */
  public function getThirdPartyProviders() {
    return array_keys($this->thirdPartySettings);
  }

}
