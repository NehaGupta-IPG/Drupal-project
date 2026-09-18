<?php

namespace Drupal\Core\Entity\EntityReferenceSelection;

use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ConfigurablePluginBase;

/**
 * Provides a base class for configurable selection handlers.
 */
abstract class SelectionPluginBase extends ConfigurablePluginBase implements SelectionInterface, DependentPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'target_type' => NULL,
      'entity' => NULL,
    ];
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function entityQueryAlter(SelectInterface $query) {}

  /**
   * Build the form elements for selection plugins with autocreate support.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param array $bundles
   *   An array of bundles to store new items in.
   *
   * @return array
   *   The form structure.
   */
  protected function buildAutocreateConfigurationForm(array $form, array $bundles): array {
    $form['auto_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Create referenced entities if they don't already exist"),
      '#default_value' => $this->configuration['auto_create'],
      '#weight' => -2,
    ];
    $form['auto_create_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Store new items in'),
      '#options' => $this->getBundleOptions($bundles),
      '#default_value' => $this->configuration['auto_create_bundle'],
      '#access' => count($bundles) > 1,
      '#states' => [
        'visible' => [
          ':input[name="settings[handler_settings][auto_create]"]' => ['checked' => TRUE],
        ],
      ],
      '#weight' => -1,
    ];
    return $form;
  }

  /**
   * Transforms bundles in the correct structure for a select element.
   *
   * @param array $bundles
   *   An array of bundles in the structure of
   *   Drupal\Core\Entity\EntityTypeBundleInfoInterface::getBundleInfo().
   *
   * @return string[]
   *   An array of bundle labels keyed by the bundle name.
   */
  protected function getBundleOptions(array $bundles): array {
    $bundle_options = [];
    foreach ($bundles as $bundle_name => $bundle_info) {
      $bundle_options[$bundle_name] = $bundle_info['label'];
    }
    natsort($bundle_options);

    return $bundle_options;
  }

}
