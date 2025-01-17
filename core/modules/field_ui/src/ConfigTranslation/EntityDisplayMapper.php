<?php

namespace Drupal\field_ui\ConfigTranslation;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a configuration mapper for entity displays.
 */
class EntityDisplayMapper extends ConfigEntityMapper {

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteParameters(): array {
    $base_entity_info = $this->entityTypeManager->getDefinition($this->pluginDefinition['base_entity_type']);
    $bundle_parameter_key = $base_entity_info->getBundleEntityType() ?: 'bundle';

    $parameters = [];
    $parameters[$bundle_parameter_key] = $this->entity->getTargetBundle();
    $parameters[$this->pluginDefinition['display_context'] . '_mode_name'] = $this->entity->getMode();

    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverviewRouteName(): string {
    return "entity.{$this->entityType}.config_translation_overview.{$this->pluginDefinition['base_entity_type']}";
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    $base_entity_info = $this->entityTypeManager->getDefinition($this->pluginDefinition['base_entity_type']);
    $bundle = $base_entity_info->getLabel();
    if ($bundle_type = $base_entity_info->getBundleEntityType()) {
      $bundle = $this->entityTypeManager
        ->getStorage($bundle_type)
        ->load($this->entity->getTargetBundle())
        ->label();
    }

    $mode = $this->entityTypeManager
      ->getStorage("entity_{$this->pluginDefinition['display_context']}_mode")
      ->load($this->pluginDefinition['base_entity_type'] . '.' . $this->entity->getMode());
    $mode = $mode ? $mode->label() : $this->t('Default');

    if ($this->entityType == 'entity_view_display') {
      return $this->t('@bundle @mode display', [
        '@bundle' => $bundle,
        '@mode' => $mode,
      ]);
    }
    if ($this->entityType == 'entity_form_display') {
      return $this->t('@bundle @mode form display', [
        '@bundle' => $bundle,
        '@mode' => $mode,
      ]);
    }
    return parent::getTypeLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeLabel(): string {
    $base_entity_info = $this->entityTypeManager->getDefinition($this->pluginDefinition['base_entity_type']);

    if ($this->entityType == 'entity_view_display') {
      return $this->t('@label view display', [
        '@label' => $base_entity_info->getLabel(),
      ]);
    }
    if ($this->entityType == 'entity_form_display') {
      return $this->t('@label form display', [
        '@label' => $base_entity_info->getLabel(),
      ]);
    }
    return parent::getTypeLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function populateFromRouteMatch(RouteMatchInterface $route_match): void {
    $bundle_entity_type = $this->entityTypeManager->getDefinition($this->pluginDefinition['base_entity_type'])->getBundleEntityType();
    $bundle = $route_match->getParameter($bundle_entity_type ?: 'bundle') ?: $this->pluginDefinition['base_entity_type'];
    $mode = $route_match->getParameter($this->pluginDefinition['display_context'] . '_mode_name') ?: 'default';

    $entity = $this->entityTypeManager
      ->getStorage($this->entityType)
      ->load("{$this->pluginDefinition['base_entity_type']}.{$bundle}.{$mode}");

    if ($entity) {
      $route_match->getParameters()->set($this->entityType, $entity);

      $this->setEntity($entity);
      parent::populateFromRouteMatch($route_match);
    }
  }

}
