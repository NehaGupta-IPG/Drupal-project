<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;

/**
 * Hook implementations for content_translation.
 */
readonly class ContentTranslationHooks {

  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityLastInstalledSchemaRepositoryInterface $schemaRepository,
    private readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
  ) {}

  /**
   * Installs Content Translation's fields for a given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @todo Generalize this code in https://www.drupal.org/node/2346013.
   */
  public function installFieldStorageDefinitions(string $entity_type_id): void {
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = $this->entityFieldManager;

    /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $schema_repository */
    $schema_repository = $this->schemaRepository;
    $definition_update_manager = $this->entityDefinitionUpdateManager;

    $field_manager->useCaches();
    $storage_definitions = $field_manager->getFieldStorageDefinitions($entity_type_id);
    $field_manager->useCaches(TRUE);
    $installed_storage_definitions = $schema_repository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    foreach (array_diff_key($storage_definitions, $installed_storage_definitions) as $storage_definition) {
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition */
      if ($storage_definition->getProvider() == 'content_translation') {
        $definition_update_manager->installFieldStorageDefinition($storage_definition->getName(), $entity_type_id, 'content_translation', $storage_definition);
      }
    }
  }

}
