<?php

namespace Drupal\field_layout\Form;

use Drupal\Component\Plugin\PluginManagerBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\field_ui\Form\EntityViewDisplayEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit form for the EntityViewDisplay entity type.
 *
 * @internal
 */
class FieldLayoutEntityViewDisplayEditForm extends EntityViewDisplayEditForm {

  use FieldLayoutEntityDisplayFormTrait;

  public function __construct(FieldTypePluginManagerInterface $field_type_manager, PluginManagerBase $plugin_manager, LayoutPluginManagerInterface $layout_plugin_manager, ?EntityDisplayRepositoryInterface $entity_display_repository = NULL, ?EntityFieldManagerInterface $entity_field_manager = NULL) {
    parent::__construct($field_type_manager, $plugin_manager, $entity_display_repository, $entity_field_manager);
    $this->layoutPluginManager = $layout_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.field.formatter'),
      $container->get('plugin.manager.core.layout'),
      $container->get('entity_display.repository'),
      $container->get('entity_field.manager')
    );
  }

}
