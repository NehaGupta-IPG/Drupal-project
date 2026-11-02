<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;

/**
 * Provides discovery and retrieval of entity type bundles.
 */
class EntityTypeBundleInfo implements EntityTypeBundleInfoInterface {

  use UseCacheBackendTrait;

  /**
   * Static cache of bundle information.
   *
   * @var array
   */
  protected $bundleInfo;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManagerInterface
   */
  protected $typedDataManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EntityTypeBundleInfo.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\TypedData\TypedDataManagerInterface $typed_data_manager
   *   The typed data manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, ModuleHandlerInterface $module_handler, TypedDataManagerInterface $typed_data_manager, CacheBackendInterface $cache_backend) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->moduleHandler = $module_handler;
    $this->typedDataManager = $typed_data_manager;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleInfo(/* string */ $entity_type_id) {
    if (!is_string($entity_type_id)) {
      @trigger_error('Calling ' . __CLASS__ . '::getBundleInfo() with a non-string $entity_type_id is deprecated in drupal:11.3.0 and throws an exception in drupal:12.0.0. See https://www.drupal.org/node/3557136', E_USER_DEPRECATED);
      return [];
    }
    $bundle_info = $this->getAllBundleInfo();
    return $bundle_info[$entity_type_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleLabels(string $entity_type_id): array {
    return array_map(static fn (array $bundle_info) => $bundle_info['label'], $this->getBundleInfo($entity_type_id));
  }

  /**
   * {@inheritdoc}
   */
  public function getAllBundleInfo() {
    // Bundle labels may be t() instances or bundle config strings.
    // So this must respect both cache contexts.
    // @todo Find a way to have real cache contexts in bundle info.
    $interfaceLangcode = $this->languageManager->getCurrentLanguage()->getId();
    $configLangcode = $this->languageManager->getConfigOverrideLanguage()->getId();

    $bundleInfo =& $this->bundleInfo[$interfaceLangcode][$configLangcode];
    if (!isset($bundleInfo)) {
      $cid = "entity_bundle_info:$interfaceLangcode:$configLangcode";
      if ($cache = $this->cacheGet($cid)) {
        $bundleInfo = $cache->data;
      }
      else {
        $bundleInfo = $this->moduleHandler->invokeAll('entity_bundle_info');
        foreach ($this->entityTypeManager->getDefinitions() as $type => $entity_type) {
          // First look for entity types that act as bundles for others, load
          // them and add them as bundles.
          if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
            foreach ($this->entityTypeManager->getStorage($bundle_entity_type)->loadMultiple() as $entity) {
              $bundleInfo[$type][$entity->id()]['label'] = $entity->label();
            }
          }
          // If entity type bundles are not supported and
          // hook_entity_bundle_info() has not already set up bundle
          // information, use the entity type name and label.
          elseif (!isset($bundleInfo[$type])) {
            $bundleInfo[$type][$type]['label'] = $entity_type->getLabel();
          }

          // Add bundle information provided by entity type plugin discovery
          // using the Drupal\Core\Entity\Attribute\Bundle attribute.
          $attribute_entity_type_bundle_info = $entity_type->get('entity_type_bundle_info');
          if ($attribute_entity_type_bundle_info === NULL) {
            continue;
          }
          foreach ($attribute_entity_type_bundle_info as $bundle => $info) {
            if ($bundle_entity_type && !isset($bundleInfo[$type][$bundle])) {
              // If the entity type has a bundle entity type, do not allow
              // bundle definitions to be created by attributes.
              continue;
            }
            $bundleInfo[$type][$bundle]['class'] = $info['class'];
            $additional_bundle_info = array_filter([
              'label' => $info['label'],
              'translatable' => $info['translatable'],
            ], fn($property) => $property !== NULL);
            $bundleInfo[$type][$bundle] = $additional_bundle_info + $bundleInfo[$type][$bundle];

            // Make sure the bundle has a label.
            $bundleInfo[$type][$bundle]['label'] ??= $bundle;
          }
        }

        $this->moduleHandler->alter('entity_bundle_info', $bundleInfo);
        $this->cacheSet($cid, $bundleInfo, Cache::PERMANENT, [
          'entity_types',
          'entity_bundles',
        ]);
      }
    }

    return $bundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedBundles() {
    $this->bundleInfo = [];
    Cache::invalidateTags(['entity_bundles']);
    // Entity bundles are exposed as data types, clear that cache too.
    $this->typedDataManager->clearCachedDefinitions();
  }

}
