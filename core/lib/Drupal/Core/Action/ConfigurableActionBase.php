<?php

namespace Drupal\Core\Action;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a base implementation for a configurable Action plugin.
 */
abstract class ConfigurableActionBase extends ActionBase implements ConfigurableInterface, DependentPluginInterface, PluginFormInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $this->mapConfigurationToContext();
    parent::execute();
  }

  protected function mapConfigurationToContext() {
    $configuration = $this->getConfiguration();
    $mapping = $this->getContextMapping();
    foreach ($this->getContextDefinitions() as $context_name => $definition) {
      if (!isset($mapping[$context_name]) && isset($configuration[$context_name])) {
        $this->setContextValue($context_name, $configuration[$context_name]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $this->mapConfigurationToContext();
    return parent::access($account, $return_as_object);
  }

}
