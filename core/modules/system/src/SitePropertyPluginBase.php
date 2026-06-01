<?php

namespace Drupal\system;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\Context\LazyContextRepository;
use Drupal\Core\Plugin\ContextAwarePluginAssignmentTrait;
use Drupal\Core\Plugin\ContextAwarePluginTrait;

/**
 * Base class for site_property plugins.
 */
abstract class SitePropertyPluginBase extends PluginBase implements SitePropertyInterface {

  use ContextAwarePluginTrait;
  use ContextAwarePluginAssignmentTrait;

  /**
   * @var mixed The name of this property.
   */
  protected $name;

  /**
   * @var mixed The value of this property.
   */
  protected $value;

  /**
   * @var mixed A description of the property.
   */
  protected $description;

  /**
   * @var bool Hide the property on view pages.
   */
  protected $hidden;

  /**
   * @var array A build array of information to display to users in the site report view.
   */
  protected $reason;

  /**
   * @var int The state this property generated.
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LazyContextRepository $contextRepository) {
    $this->contextRepository = $contextRepository;

    // Pass the other parameters up to the parent constructor.
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // set the defined contexts' values
    $this->setDefinedContextValues();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('context.repository')
    );
  }
  /**
   * Set values for the defined contexts of this plugin
   *
   */

  private function setDefinedContextValues() {
    // fetch the available contexts
    $available_contexts = $this->contextRepository->getAvailableContexts();

    // ensure that the contexts have data by getting corresponding runtime contexts
    $available_runtime_contexts = $this->contextRepository->getRuntimeContexts(array_keys($available_contexts));
    $plugin_context_definitions = $this->getContextDefinitions();
    foreach ($plugin_context_definitions as $name => $plugin_context_definition) {

      // identify and fetch the matching runtime context, with the plugin's context definition
      $matches = $this->contextHandler()
        ->getMatchingContexts($available_runtime_contexts, $plugin_context_definition);
      $matching_context = reset($matches);

      // set the value to the plugin's context, from runtime context value
      if ($matching_context) {
        $this->setContextValue($name,$matching_context->getContextValue());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function name() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function hidden() {
    return $this->pluginDefinition['hidden'] ?? false;
  }

  /**
   * {@inheritdoc}
   */
  public function value() {
    return $this->value ?? $this->pluginDefinition['default_value'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function reason() {
    return $this->reason;
  }

  /**
   * {@inheritdoc}
   */
  public function description() {
    return $this->description ?? $this->pluginDefinition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function state() {
    return $this->site;
  }

  /**
   * {@inheritdoc}
   */
  public function category() {
    return $this->pluginDefinition['category'] ?? 'site';
  }

  /**
   * Return a build array on site definition view pages.
   * @return array
   */
  public function view() {
    return [
      '#type' => 'item',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#description_display' => 'after',
      '#markup' => $this->value(),
    ];
  }

  /**
   * @TODO: I do not know why the ContextAwarePluginTrait trait requires t() method to be defined.
   */
  protected function t($string, array $args = [], array $options = []) {
    return parent::t($string, $args, $options);
  }
}
