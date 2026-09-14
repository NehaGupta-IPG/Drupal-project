<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\content_translation\BundleTranslationSettingsInterface;
use Drupal\language\ContentLanguageSettingsInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\content_translation\FieldTranslationSynchronizerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Entity hook implementations for content_translation.
 */
class ContentTranslationEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    private readonly ContentTranslationManagerInterface $contentTranslationManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityLastInstalledSchemaRepositoryInterface $schemaRepository,
    private readonly FieldTranslationSynchronizerInterface $fieldTranslationSynchronizer,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   *
   * The content translation UI relies on the entity info to provide its
   * features. See the documentation of hook_entity_type_build() in the Entity
   * API documentation for more details on all the entity info keys that may be
   * defined.
   *
   * To make Content Translation automatically support an entity type some keys
   * may need to be defined, but none of them is required unless the entity path
   * is different from the usual /ENTITY_TYPE/{ENTITY_TYPE} pattern (for
   * instance "/taxonomy/term/{taxonomy_term}"). Here are a list of those
   * optional keys:
   * - canonical: This key (in the 'links' entity info property) must be defined
   *   if the entity path is different from /ENTITY_TYPE/{ENTITY_TYPE}
   * - translation: This key (in the 'handlers' entity annotation property)
   *   specifies the translation handler for the entity type. If an entity type
   *   is translatable and no translation handler is defined,
   *   \Drupal\content_translation\ContentTranslationHandler will be assumed.
   *   Every translation handler must implement
   *   \Drupal\content_translation\ContentTranslationHandlerInterface.
   * - content_translation_ui_skip: By default, entity types that do not have a
   *   canonical link template cannot be enabled for translation. Setting this
   *   key to TRUE overrides that. When that key is set, the Content Translation
   *   module will not provide any UI for translating the entity type, and the
   *   entity type should implement its own UI. For instance, this is useful for
   *   entity types that are embedded into others for editing (which would not
   *   need a canonical link, but could still support translation).
   * - content_translation_metadata: To implement its business logic the content
   *   translation UI relies on various metadata items describing the
   *   translation state. The default implementation is provided by
   *   \Drupal\content_translation\ContentTranslationMetadataWrapper, which is
   *   relying on one field for each metadata item (field definitions are
   *   provided by the translation handler). Entity types needing to customize
   *   this behavior can specify an alternative class through the
   *   'content_translation_metadata' key in the entity type definition. Every
   *   content translation metadata wrapper needs to implement
   *   \Drupal\content_translation\ContentTranslationMetadataWrapperInterface.
   *
   * If the entity paths match the default pattern above and there is no need
   * for an entity-specific translation handler, Content Translation will
   * provide built-in support for the entity. However enabling translation for
   * each translatable bundle will be required.
   *
   * @see \Drupal\Core\Entity\Annotation\EntityType
   */
  #[Hook('entity_type_alter', order: Order::Last)]
  public function entityTypeAlter(array &$entity_types) : void {
    // Provide defaults for translation info.
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    foreach ($entity_types as $entity_type) {
      if ($entity_type->isTranslatable()) {
        if (!$entity_type->hasHandlerClass('translation')) {
          $entity_type->setHandlerClass('translation', 'Drupal\content_translation\ContentTranslationHandler');
        }
        if (!$entity_type->get('content_translation_metadata')) {
          $entity_type->set('content_translation_metadata', 'Drupal\content_translation\ContentTranslationMetadataWrapper');
        }
        if (!$entity_type->getFormClass('content_translation_deletion')) {
          $entity_type->setFormClass('content_translation_deletion', '\Drupal\content_translation\Form\ContentTranslationDeleteForm');
        }
        $translation = $entity_type->get('translation');
        if (!$translation || !isset($translation['content_translation'])) {
          $translation['content_translation'] = [];
        }
        if ($entity_type->hasLinkTemplate('canonical')) {
          // Provide default route names for the translation paths.
          if (!$entity_type->hasLinkTemplate('drupal:content-translation-overview')) {
            $translations_path = $entity_type->getLinkTemplate('canonical') . '/translations';
            $entity_type->setLinkTemplate('drupal:content-translation-overview', $translations_path);
            $entity_type->setLinkTemplate('drupal:content-translation-add', $translations_path . '/add/{source}/{target}');
            $entity_type->setLinkTemplate('drupal:content-translation-edit', $translations_path . '/edit/{language}');
            $entity_type->setLinkTemplate('drupal:content-translation-delete', $translations_path . '/delete/{language}');
          }
          // @todo Remove this as soon as menu access checks rely on the
          //   controller. See https://www.drupal.org/node/2155787.
          $translation['content_translation'] += ['access_callback' => 'content_translation.manager:access'];
        }
        $entity_type->set('translation', $translation);
      }
      $entity_type->addConstraint('ContentTranslationSynchronizedFields');
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   *
   * Installs Content Translation's field storage definitions for the target
   * entity type, if required.
   *
   * Also clears the bundle information cache so that the bundle's
   * translatability will be set properly.
   *
   * @see content_translation_entity_bundle_info_alter()
   * @see \Drupal\content_translation\ContentTranslationManager::isEnabled()
   */
  #[Hook('language_content_settings_insert')]
  public function languageContentSettingsInsert(ContentLanguageSettingsInterface $settings): void {
    if ($settings->getThirdPartySetting('content_translation', 'enabled', FALSE)) {
      $this->installFieldStorageDefinitions($settings->getTargetEntityTypeId());
    }
    $this->entityTypeBundleInfo->clearCachedBundles();
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   *
   * Installs Content Translation's field storage definitions for the target
   * entity type, if required.
   *
   * Also clears the bundle information cache so that the bundle's
   * translatability will be changed properly.
   *
   * @see content_translation_entity_bundle_info_alter()
   * @see \Drupal\content_translation\ContentTranslationManager::isEnabled()
   */
  #[Hook('language_content_settings_update')]
  public function languageContentSettingsUpdate(ContentLanguageSettingsInterface $settings): void {
    $original_settings = $settings->getOriginal();
    if ($settings->getThirdPartySetting('content_translation', 'enabled', FALSE) && !$original_settings->getThirdPartySetting('content_translation', 'enabled', FALSE)) {
      $this->installFieldStorageDefinitions($settings->getTargetEntityTypeId());
    }
    $this->entityTypeBundleInfo->clearCachedBundles();
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter', order: Order::First)]
  public function entityBundleInfoAlter(&$entity_type_bundles): void {

    // Inline the logic from ContentTranslationManager::isEnabled() to avoid
    // having to load configuration entities one by one.
    $content_translation_manager = $this->contentTranslationManager;
    $entity_type_manager = $this->entityTypeManager;
    $ids = [];
    foreach ($entity_type_bundles as $entity_type => $bundles) {
      if ($content_translation_manager->isSupported($entity_type)) {
        foreach ($bundles as $bundle => $bundle_info) {
          $ids[] = $entity_type . '.' . $bundle;
        }
      }
    }
    $language_content_settings = $entity_type_manager->getStorage('language_content_settings')->loadMultiple($ids);

    foreach ($language_content_settings as $language_content_setting) {
      if ($language_content_setting->getThirdPartySetting('content_translation', 'enabled', FALSE)) {
        $entity_type_id = $language_content_setting->get('target_entity_type_id');
        $bundle = $language_content_setting->get('target_bundle');
        $entity_type_bundles[$entity_type_id][$bundle]['translatable'] = TRUE;
        if ($content_translation_manager instanceof BundleTranslationSettingsInterface) {
          $settings = $content_translation_manager->getBundleTranslationSettings($entity_type_id, $bundle);
          $entity_type_bundles[$entity_type_id][$bundle]['untranslatable_fields.default_translation_affected'] = !empty($settings['untranslatable_fields_hide']);
        }
      }
    }
    foreach ($entity_type_bundles as $entity_type => $info) {
      foreach ($info as $bundle => $bundle_info) {
        if (!isset($bundle_info['translatable'])) {
          $entity_type_bundles[$entity_type][$bundle]['translatable'] = FALSE;
        }
      }
    }

    // Always hide untranslatable field widgets if pending revision support is
    // enabled otherwise changes in pending
    // revisions might be overridden by changes in later default revisions.
    // This can't use
    // Drupal\content_translation\ContentTranslationManager::isPendingRevisionSupportEnabled()
    // since that depends on entity bundle information to be completely built.
    if ($this->moduleHandler->moduleExists('content_moderation')) {
      /** @var \Drupal\workflows\WorkflowInterface[] $workflows */
      $workflows = $this->entityTypeManager
        ->getStorage('workflow')
        ->loadByProperties(['type' => 'content_moderation']);
      foreach ($workflows as $workflow) {
        /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModeration $plugin */
        $plugin = $workflow->getTypePlugin();
        foreach ($plugin->getEntityTypes() as $entity_type_id) {
          foreach ($plugin->getBundlesForEntityType($entity_type_id) as $bundle_id) {
            if (isset($entity_type_bundles[$entity_type_id][$bundle_id])) {
              $entity_type_bundles[$entity_type_id][$bundle_id]['untranslatable_fields.default_translation_affected'] = TRUE;
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $info = [];
    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $manager */
    $manager = $this->contentTranslationManager;
    $entity_type_id = $entity_type->id();
    if ($manager->isSupported($entity_type_id)) {
      $definitions = $manager->getTranslationHandler($entity_type_id)->getFieldDefinitions();
      $installed_storage_definitions = $this->schemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
      // We return metadata storage fields whenever content translation is
      // enabled or it was enabled before, so that we keep translation metadata
      // around when translation is disabled.
      // @todo Re-evaluate this approach and consider removing field storage
      //   definitions and the related field data if the entity type has no
      //   bundle enabled for translation. See https://www.drupal.org/i/2907777
      if ($manager->isEnabled($entity_type_id) || array_intersect_key($definitions, $installed_storage_definitions)) {
        $info = $definitions;
      }
    }
    return $info;
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    $operations = [];
    if ($entity->hasLinkTemplate('drupal:content-translation-overview') && $this->contentTranslationManager->access($entity)->isAllowed()) {
      $operations['translate'] = [
        'title' => $this->t('Translate'),
        'url' => $entity->toUrl('drupal:content-translation-overview'),
        'weight' => 50,
      ];
    }
    return $operations;
  }

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    // Inline the logic from ContentTranslationManager::isEnabled() to avoid
    // having to load configuration entities one by one.
    $extra = [];
    $content_translation_manager = $this->contentTranslationManager;
    $entity_type_manager = $this->entityTypeManager;
    $ids = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type => $info) {
      if ($content_translation_manager->isSupported($entity_type)) {
        foreach ($info as $bundle => $bundle_info) {
          $ids[] = $entity_type . '.' . $bundle;
        }
      }
    }
    $settings = $entity_type_manager->getStorage('language_content_settings')->loadMultiple($ids);
    foreach ($settings as $config) {
      if ($config->getThirdPartySetting('content_translation', 'enabled', FALSE)) {
        $extra[$config->get('target_entity_type_id')][$config->get('target_bundle')]['form']['translation'] = [
          'label' => $this->t('Translation'),
          'description' => $this->t('Translation settings'),
          'weight' => 10,
        ];
      }
    }
    return $extra;
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface && $entity->isTranslatable() && !$entity->isNew() && $entity->getOriginal()) {
      /** @var \Drupal\content_translation\ContentTranslationManagerInterface $manager */
      $manager = $this->contentTranslationManager;
      if (!$manager->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
        return;
      }
      $langcode = $entity->language()->getId();
      $source_langcode = !$entity->getOriginal()->hasTranslation($langcode) ? $manager->getTranslationMetadata($entity)->getSource() : NULL;
      $this->fieldTranslationSynchronizer->synchronizeFields($entity, $langcode, $source_langcode);
    }
  }

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
