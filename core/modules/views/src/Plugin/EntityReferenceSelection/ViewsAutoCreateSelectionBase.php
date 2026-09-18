<?php

declare(strict_types=1);

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
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'auto_create' => FALSE,
      'auto_create_bundle' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    if (isset($form['view']['view_and_display'])) {
      $entity_type = $this->entityTypeManager->getDefinition($this->configuration['target_type']);

      $bundles = $entity_type->hasKey('bundle') ? $this->entityTypeBundleInfo->getBundleInfo($entity_type->id()) : [];
      $form = $this->buildAutocreateConfigurationForm($form, $bundles);

    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    // Nothing todo here.
    if ($form_state->getValue(['settings', 'handler_settings', 'auto_create_bundle']) === NULL) {
      return;
    }
    $auto_created = $form_state->getValue(['settings', 'handler_settings', 'auto_create']);
    if ($auto_created === '0') {
      $form_state->setValue(['settings', 'handler_settings', 'auto_create_bundle'], NULL);
      return;
    }
    $entity_type = $this->entityTypeManager->getDefinition($this->configuration['target_type']);

    $bundles = $entity_type->hasKey('bundle') ? $this->entityTypeBundleInfo->getBundleInfo($entity_type->id()) : [];
    if (count($bundles) <= 1) {
      $form_state->setValue(['settings', 'handler_settings', 'auto_create_bundle'], NULL);
    }
  }

}
