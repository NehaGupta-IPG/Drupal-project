<?php

namespace Drupal\config_translation\Controller;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the config translation list builder for field entities.
 */
abstract class BundleAwareConfigEntityListBuilder extends ConfigTranslationEntityListBuilder {

  /**
   * The name of the entity type the configs are attached to.
   *
   * @var string
   */
  protected $baseEntityType = '';

  /**
   * An array containing the base entity type's definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $baseEntityInfo;

  /**
   * The bundles info for the base entity type.
   *
   * @var array
   */
  protected $baseEntityBundles = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $entity_type_manager = $container->get('entity_type.manager');
    $entity_type_bundle_info = $container->get('entity_type.bundle.info');
    return new static(
      $entity_type,
      $entity_type_manager->getStorage($entity_type->id()),
      $entity_type_manager,
      $entity_type_bundle_info
    );
  }

  /**
   * Constructs a new ConfigTranslationFieldListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($entity_type, $storage);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function setMapperDefinition($mapper_definition) {
    $this->baseEntityType = $mapper_definition['base_entity_type'];
    $this->baseEntityInfo = $this->entityTypeManager->getDefinition($this->baseEntityType);
    $this->baseEntityBundles = $this->entityTypeBundleInfo->getBundleInfo($this->baseEntityType);
    return $this;
  }

  /**
   * Controls the visibility of the bundle column on field list pages.
   *
   * @return bool
   *   Whenever the bundle is displayed or not.
   */
  public function displayBundle() {
    // The bundle key is explicitly defined in the entity definition.
    if ($this->baseEntityInfo->getKey('bundle')) {
      return TRUE;
    }

    // There is more than one bundle defined.
    if (count($this->baseEntityBundles) > 1) {
      return TRUE;
    }

    // The defined bundle ones not match the entity type name.
    if (!empty($this->baseEntityBundles) && !isset($this->baseEntityBundles[$this->baseEntityType])) {
      return TRUE;
    }

    return FALSE;
  }

}
