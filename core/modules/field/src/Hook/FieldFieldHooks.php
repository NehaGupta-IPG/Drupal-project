<?php

namespace Drupal\field\Hook;

use Drupal\field\FieldStorageConfigInterface;
use Drupal\field\EntityDisplayRebuilder;
use Drupal\field\FieldConfigInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;

/**
 * Field hook implementations for field.
 */
class FieldFieldHooks {

  public function __construct(
    protected readonly ClassResolverInterface $classResolver,
    protected readonly FieldTypePluginManagerInterface $fieldTypeManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly SelectionPluginManagerInterface $entityReferenceSelectionManager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_insert() for 'field_config'.
   */
  #[Hook('field_config_insert')]
  public function fieldConfigInsert(FieldConfigInterface $field): void {
    if ($field->isSyncing()) {
      // Don't change anything during a configuration sync.
      return;
    }
    // Allow other view modes to update their configuration for the new field.
    // Otherwise, configuration for view modes won't get updated until the mode
    // is used for the first time, creating noise in config diffs.
    $this->classResolver->getInstanceFromDefinition(EntityDisplayRebuilder::class)->rebuildEntityTypeDisplays($field->getTargetEntityTypeId(), $field->getTargetBundle());
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'field_storage_config'.
   *
   * Reset the field handler settings, when the storage target_type is changed
   * on an entity reference field.
   */
  #[Hook('field_storage_config_update')]
  public function fieldStorageConfigUpdate(FieldStorageConfigInterface $field_storage): void {
    if ($field_storage->isSyncing()) {
      // Don't change anything during a configuration sync.
      return;
    }
    // Act on all sub-types of the entity_reference field type.
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $this->fieldTypeManager->getPluginClass($field_storage->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // If target_type changed, reset the handler in the fields using that
    // storage.
    if ($field_storage->getSetting('target_type') !== $field_storage->getOriginal()->getSetting('target_type')) {
      foreach ($field_storage->getBundles() as $bundle) {
        $field = FieldConfig::loadByName($field_storage->getTargetEntityTypeId(), $bundle, $field_storage->getName());
        // Reset the handler settings. This triggers
        // \Drupal\field\Hook\FieldHooks::fieldConfigPresave(), which will take
        // care of reassigning the handler to the correct derivative for the new
        // target_type.
        $field->setSetting('handler_settings', []);
        $field->save();
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_create() for 'field_config'.
   *
   * Determine the selection handler plugin ID for an entity reference field.
   */
  #[Hook('field_config_create')]
  public function fieldConfigCreate(FieldConfigInterface $field): void {
    if ($field->isSyncing()) {
      return;
    }
    // Act on all sub-types of the entity_reference field type.
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $this->fieldTypeManager->getPluginClass($field->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // If we don't know the target type yet, there's nothing else we can do.
    $target_type = $field->getFieldStorageDefinition()->getSetting('target_type');
    if (empty($target_type)) {
      return;
    }
    // Make sure the selection handler plugin is the correct derivative for the
    // target entity type.
    [$current_handler] = explode(':', $field->getSetting('handler'), 2);
    $field->setSetting('handler', $this->entityReferenceSelectionManager->getPluginId($target_type, $current_handler));
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for 'field_config'.
   *
   * Determine the selection handler plugin ID for an entity reference field.
   */
  #[Hook('field_config_presave')]
  public function fieldConfigPresave(FieldConfigInterface $field): void {
    // Don't change anything during a configuration sync.
    if ($field->isSyncing()) {
      return;
    }
    $this->fieldConfigCreate($field);
    // Act on all sub-types of the entity_reference field type.
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $this->fieldTypeManager->getPluginClass($field->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // In case we removed all the target bundles allowed by the field in
    // EntityReferenceItem::onDependencyRemoval() or
    // \Drupal\field\Hook\FieldHooks::entityBundleDelete() we have to log a
    // critical message because the field will not function correctly anymore.
    $handler_settings = $field->getSetting('handler_settings');
    if (isset($handler_settings['target_bundles']) && $handler_settings['target_bundles'] === []) {
      $this->loggerFactory->get('entity_reference')->critical('The %field_name entity reference field (entity_type: %entity_type, bundle: %bundle) no longer has any valid bundle it can reference. The field is not working correctly anymore and has to be adjusted.', [
        '%field_name' => $field->getName(),
        '%entity_type' => $field->getTargetEntityTypeId(),
        '%bundle' => $field->getTargetBundle(),
      ]);
    }
  }

}
