<?php

namespace Drupal\views\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for views selection plugins with basic autocreate functionality.
 */
abstract class ViewsAutoCreateSelectionBase extends ViewsSelection implements SelectionWithAutocreateInterface {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $viewsSelection = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $viewsSelection->setEntityTypeBundleInfo($container->get('entity_type.bundle.info'));
    return $viewsSelection;
  }

  /**
   * Sets the entity type bundle info service.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   */
  protected function setEntityTypeBundleInfo(EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'auto_create' => FALSE,
      'auto_create_bundle' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    if (isset($form['view']['view_and_display'])) {
      $entity_type = $this->entityTypeManager->getDefinition($this->configuration['target_type']);

      $bundles = $entity_type->hasKey('bundle') ? $this->entityTypeBundleInfo->getBundleInfo($entity_type->id()) : [];
      $form = $this->buildAutocreateConfigurationForm($form, $bundles);

    }
    return $form;
  }

}
