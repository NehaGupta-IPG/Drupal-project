<?php

namespace Drupal\Core\Entity;

use Drupal\Component\Gettext\PoItem;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Config\Entity\EntityBundleWithPluralLabelsInterface;
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
  public function getBundleInfo($entity_type_id) {
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
    if (empty($this->bundleInfo)) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
      if ($cache = $this->cacheGet("entity_bundle_info:$langcode")) {
        $this->bundleInfo = $cache->data;
      }
      else {
        $this->bundleInfo = $this->moduleHandler->invokeAll('entity_bundle_info');
        foreach ($this->entityTypeManager->getDefinitions() as $type => $entity_type) {
          // First look for entity types that act as bundles for others, load
          // them and add them as bundles.
          if ($bundle_entity_type = $entity_type->getBundleEntityType()) {
            foreach ($this->entityTypeManager->getStorage($bundle_entity_type)->loadMultiple() as $entity) {
              $this->bundleInfo[$type][$entity->id()]['label'] = $entity->label();
              if ($entity instanceof EntityBundleWithPluralLabelsInterface) {
                $this->bundleInfo[$type][$entity->id()] += [
                  'label_singular' => $entity->getSingularLabel(),
                  'label_plural' => $entity->getPluralLabel(),
                  'label_count' => $entity->getCountLabel(),
                ];
              }
            }
          }
          // If entity type bundles are not supported and
          // hook_entity_bundle_info() has not already set up bundle
          // information, use the entity type name and label.
          elseif (!isset($this->bundleInfo[$type])) {
            $this->bundleInfo[$type][$type]['label'] = $entity_type->getLabel();
          }
        }
        $this->moduleHandler->alter('entity_bundle_info', $this->bundleInfo);
        $this->cacheSet("entity_bundle_info:$langcode", $this->bundleInfo, Cache::PERMANENT, [
          'entity_types',
          'entity_bundles',
        ]);
      }
    }

    return $this->bundleInfo;
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

  /**
   * {@inheritdoc}
   */
  public function getBundleCountLabel(string $entity_type_id, string $bundle, int $count, $variant): ?string {
    if (!$bundles_info = $this->getBundleInfo($entity_type_id)) {
      throw new \InvalidArgumentException("The '{$entity_type_id}' doesn't exist.");
    }
    $bundle_info = $bundles_info[$bundle] ?? NULL;
    if (!$bundle_info) {
      throw new \InvalidArgumentException("The '{$entity_type_id}' entity type bundle '{$bundle}' doesn't exist.");
    }

    if (!empty($bundle_info['label_count'])) {
      if (empty($bundle_info['label_count'][$variant])) {
        throw new \InvalidArgumentException("There's no variant '{$variant}' defined in label_count for bundle '{$bundle}' of '{$entity_type_id}' entity type.");
      }

      $index = static::getPluralIndex($count);
      if ($index === -1) {
        // If the index cannot be computed, fallback to a single plural variant.
        $index = $count > 1 ? 1 : 0;
      }

      $label_count = explode(PoItem::DELIMITER, $bundle_info['label_count'][$variant]);
      if (!empty($label_count[$index])) {
        return new FormattableMarkup($label_count[$index], ['@count' => $count]);
      }
    }

    return NULL;
  }

  /**
   * Gets the plural index through the gettext formula.
   *
   * @param int $count
   *   Number to return plural for.
   *
   * @return int
   *   The numeric index of the plural variant to use for the current language
   *   and the given $count number or -1 if the language was not found or does
   *   not have a plural formula.
   *
   * @todo Remove this method when https://www.drupal.org/node/2766857 gets in.
   */
  protected static function getPluralIndex(int $count): int {
    // We have to test both if the function and the service exist since in
    // certain situations it is possible that locale code might be loaded but
    // the service does not exist. For example, where the parent test site has
    // locale installed but the child site does not.
    // @todo Refactor in https://www.drupal.org/node/2660338 so this code does
    //   not depend on knowing that the Locale module exists.
    if (function_exists('locale_get_plural') && \Drupal::hasService('locale.plural.formula')) {
      return locale_get_plural($count);
    }
    return -1;
  }

}
