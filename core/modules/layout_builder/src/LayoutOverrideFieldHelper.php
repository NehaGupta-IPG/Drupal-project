<?php

declare(strict_types=1);

namespace Drupal\layout_builder;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides methods to help with entities using Layout Builder.
 */
class LayoutOverrideFieldHelper implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * Constructs a new LayoutOverrideFieldHelper.
   */
  public function __construct(
    protected SectionStorageManagerInterface $sectionStorageManager,
    protected LayoutTempstoreRepositoryInterface $layoutTempstoreRepository,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('layout_builder.tempstore_repository'),
    );
  }

  /**
   * Updates a layout overrides's entity context when entity values change.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity with the overridden layout.
   */
  public function updateTempstoreEntityContext(FieldableEntityInterface $entity): void {
    if ($section_storage = $this->getOverridesSectionStorageForEntity($entity)) {

      // This is only necessary if there is a layout override in the tempstore.
      if ($this->layoutTempstoreRepository->has($section_storage)) {
        /** @var \Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorageInterface $override_temp_store */
        $override_temp_store = $this->layoutTempstoreRepository->get($section_storage);

        // Get the entity currently in the tempstore's entity context.
        $stored_entity = $override_temp_store->getContextValue('entity');

        // Reload the entity from storage to ensure revision metadata
        // (revision ID, original values, loaded revision tracking) is
        // consistent with the database. Using the post-save entity directly
        // can cause revision mismatches when reverting layout overrides
        // after a new content revision is created.
        $entity_storage = \Drupal::entityTypeManager()
          ->getStorage($entity->getEntityTypeId());

        $updated_entity = $entity_storage->load($entity->id());
        $updated_entity->{OverridesSectionStorage::FIELD_NAME} = $stored_entity->{OverridesSectionStorage::FIELD_NAME};

        $override_temp_store->setContextValue('entity', $updated_entity);
        $this->layoutTempstoreRepository->set($override_temp_store);
      }
    }
  }

}
