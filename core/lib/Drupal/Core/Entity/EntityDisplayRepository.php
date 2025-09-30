<?php

namespace Drupal\Core\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a repository for entity display objects (view modes and form modes).
 */
class EntityDisplayRepository implements EntityDisplayRepositoryInterface {

  use UseCacheBackendTrait;
  use StringTranslationTrait;

  /**
   * Static cache of display modes information.
   *
   * @var array
   */
  protected $displayModeInfo = [];

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new EntityDisplayRepository.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, CacheBackendInterface $cache_backend, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->cacheBackend = $cache_backend;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllViewModes() {
    return $this->getAllDisplayModesByEntityType('view_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModes($entity_type_id) {
    return $this->getDisplayModesByEntityType('view_mode', $entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllFormModes() {
    return $this->getAllDisplayModesByEntityType('form_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormModes($entity_type_id) {
    return $this->getDisplayModesByEntityType('form_mode', $entity_type_id);
  }

  /**
   * Gets the entity display mode info for all entity types.
   *
   * @param string $display_type
   *   The display type to be retrieved. It can be "view_mode" or "form_mode".
   *
   * @return array
   *   The display mode info for all entity types.
   */
  protected function getAllDisplayModesByEntityType($display_type) {
    if (!isset($this->displayModeInfo[$display_type])) {
      $key = 'entity_' . $display_type . '_info';
      $entity_type_id = 'entity_' . $display_type;
      $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)->getId();
      if ($cache = $this->cacheGet("$key:$langcode")) {
        $this->displayModeInfo[$display_type] = $cache->data;
      }
      else {
        $this->displayModeInfo[$display_type] = [];
        foreach ($this->entityTypeManager->getStorage($entity_type_id)->loadMultiple() as $display_mode) {
          [$display_mode_entity_type, $display_mode_name] = explode('.', $display_mode->id(), 2);
          $this->displayModeInfo[$display_type][$display_mode_entity_type][$display_mode_name] = $display_mode->toArray();
        }
        $this->moduleHandler->alter($key, $this->displayModeInfo[$display_type]);
        $this->cacheSet("$key:$langcode", $this->displayModeInfo[$display_type], CacheBackendInterface::CACHE_PERMANENT, [
          'entity_types',
          'entity_field_info',
        ]);
      }
    }

    return $this->displayModeInfo[$display_type];
  }

  /**
   * Gets the entity display mode info for a specific entity type.
   *
   * @param string $display_type
   *   The display type to be retrieved. It can be "view_mode" or "form_mode".
   * @param string $entity_type_id
   *   The entity type whose display mode info should be returned.
   *
   * @return array
   *   The display mode info for a specific entity type.
   */
  protected function getDisplayModesByEntityType($display_type, $entity_type_id) {
    if (isset($this->displayModeInfo[$display_type][$entity_type_id])) {
      return $this->displayModeInfo[$display_type][$entity_type_id];
    }
    else {
      $display_modes = $this->getAllDisplayModesByEntityType($display_type);
      if (isset($display_modes[$entity_type_id])) {
        return $display_modes[$entity_type_id];
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModeOptions($entity_type) {
    return $this->getDisplayModeOptions('view_mode', $entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormModeOptions($entity_type_id) {
    return $this->getDisplayModeOptions('form_mode', $entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModeOptionsByBundle($entity_type_id, $bundle) {
    return $this->getDisplayModeOptionsByBundle('view_mode', $entity_type_id, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormModeOptionsByBundle($entity_type_id, $bundle) {
    return $this->getDisplayModeOptionsByBundle('form_mode', $entity_type_id, $bundle);
  }

  /**
   * Gets an array of display mode options.
   *
   * @param string $display_type
   *   The display type to be retrieved. It can be "view_mode" or "form_mode".
   * @param string $entity_type_id
   *   The entity type whose display mode options should be returned.
   *
   * @return array
   *   An array of display mode labels, keyed by the display mode ID.
   */
  protected function getDisplayModeOptions($display_type, $entity_type_id) {
    $options = ['default' => $this->t('Default')];
    foreach ($this->getDisplayModesByEntityType($display_type, $entity_type_id) as $mode => $settings) {
      $options[$mode] = $settings['label'];
    }
    return $options;
  }

  /**
   * Returns an array of enabled display mode options by bundle.
   *
   * @param string $display_type
   *   The display type to be retrieved. It can be "view_mode" or "form_mode".
   * @param string $entity_type_id
   *   The entity type whose display mode options should be returned.
   * @param string $bundle
   *   The name of the bundle.
   *
   * @return array
   *   An array of display mode labels, keyed by the display mode ID.
   */
  protected function getDisplayModeOptionsByBundle($display_type, $entity_type_id, $bundle) {
    // Collect all the entity's display modes.
    $options = $this->getDisplayModeOptions($display_type, $entity_type_id);

    // Filter out modes for which the entity display is disabled
    // (or non-existent).
    $load_ids = [];
    // Get the list of available entity displays for the current bundle.
    foreach (array_keys($options) as $mode) {
      $load_ids[] = $entity_type_id . '.' . $bundle . '.' . $mode;
    }

    // Load the corresponding displays.
    $displays = $this->entityTypeManager
      ->getStorage($display_type == 'form_mode' ? 'entity_form_display' : 'entity_view_display')
      ->loadMultiple($load_ids);

    // Unset the display modes that are not active or do not exist.
    foreach (array_keys($options) as $mode) {
      $display_id = $entity_type_id . '.' . $bundle . '.' . $mode;
      if (!isset($displays[$display_id]) || !$displays[$display_id]->status()) {
        unset($options[$mode]);
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function clearDisplayModeInfo() {
    $this->displayModeInfo = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewDisplay($entity_type, $bundle, $view_mode = self::DEFAULT_DISPLAY_MODE) {
    $storage = $this->entityTypeManager->getStorage('entity_view_display');

    // Try loading the display from configuration; if not found, create a fresh
    // display object. We do not preemptively create new entity_view_display
    // configuration entries for each existing entity type and bundle whenever a
    // new view mode becomes available. Instead, configuration entries are only
    // created when a display object is explicitly configured and saved.
    $entity_view_display = $storage->load($entity_type . '.' . $bundle . '.' . $view_mode);
    if (!$entity_view_display) {
      $entity_view_display = $storage->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $view_mode,
        'status' => TRUE,
      ]);
    }
    return $entity_view_display;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplay($entity_type, $bundle, $form_mode = self::DEFAULT_DISPLAY_MODE) {
    $storage = $this->entityTypeManager->getStorage('entity_form_display');

    // Try loading the entity from configuration; if not found, create a fresh
    // entity object. We do not preemptively create new entity form display
    // configuration entries for each existing entity type and bundle whenever a
    // new form mode becomes available. Instead, configuration entries are only
    // created when an entity form display is explicitly configured and saved.
    $entity_form_display = $storage->load($entity_type . '.' . $bundle . '.' . $form_mode);
    if (!$entity_form_display) {
      $entity_form_display = $storage->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $form_mode,
        'status' => TRUE,
      ]);
    }
    return $entity_form_display;
  }

  public function collectFormDisplay(FieldableEntityInterface $entity, $form_mode, $default_fallback = TRUE): EntityFormDisplayInterface {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $storage = $this->entityTypeManager->getStorage('entity_form_display');

    $this->moduleHandler->alter(
      [$entity_type . '_form_mode', 'entity_form_mode'],
      $form_mode,
      $entity
    );

    // Check the existence and status of:
    // - the display for the form mode,
    // - the 'default' display.
    // @todo we only have 2 candidate IDs, should we just call `getFormDisplay`
    //   and if the result `isNew` we check for $default_fallback and call it
    //   again?
    if ($form_mode != 'default') {
      $candidate_ids[] = $entity_type . '.' . $bundle . '.' . $form_mode;
    }
    if ($default_fallback) {
      $candidate_ids[] = $entity_type . '.' . $bundle . '.default';
    }
    $results = $storage->getQuery()
      ->condition('id', $candidate_ids)
      ->condition('status', TRUE)
      ->execute();

    // Load the first valid candidate display, if any.
    foreach ($candidate_ids as $candidate_id) {
      if (isset($results[$candidate_id])) {
        $display = $storage->load($candidate_id);
        break;
      }
    }
    // Else create a fresh runtime object.
    if (empty($display)) {
      $display = $storage->create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $default_fallback ? $form_mode : EntityDisplayBase::CUSTOM_MODE,
        'status' => TRUE,
      ]);
    }

    // Let the display know which form mode was originally requested.
    $display->originalMode = $form_mode;

    // Let modules alter the display.
    $display_context = [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'form_mode' => $form_mode,
    ];
    $this->moduleHandler->alter('entity_form_display', $display, $display_context);

    return $display;
  }

  public function collectViewDisplays($entities, $view_mode): array {
    if (empty($entities)) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('entity_view_display');

    // Collect entity type and bundles.
    $entity_type = current($entities)->getEntityTypeId();
    $bundles = [];
    foreach ($entities as $entity) {
      $bundles[$entity->bundle()] = TRUE;
    }
    $bundles = array_keys($bundles);

    // For each bundle, check the existence and status of:
    // - the display for the view mode,
    // - the 'default' display.
    $candidate_ids = [];
    foreach ($bundles as $bundle) {
      if ($view_mode != 'default') {
        $candidate_ids[$bundle][] = $entity_type . '.' . $bundle . '.' . $view_mode;
      }
      $candidate_ids[$bundle][] = $entity_type . '.' . $bundle . '.default';
    }
    $results = $storage->getQuery()
      ->condition('id', NestedArray::mergeDeepArray($candidate_ids))
      ->condition('status', TRUE)
      ->execute();

    // For each bundle, select the first valid candidate display, if any.
    $load_ids = [];
    foreach ($bundles as $bundle) {
      foreach ($candidate_ids[$bundle] as $candidate_id) {
        if (isset($results[$candidate_id])) {
          $load_ids[$bundle] = $candidate_id;
          break;
        }
      }
    }

    // Load the selected displays.
    $displays = $storage->loadMultiple($load_ids);

    $displays_by_bundle = [];
    foreach ($bundles as $bundle) {
      // Use the selected display if any, or create a fresh runtime object.
      if (isset($load_ids[$bundle])) {
        $display = $displays[$load_ids[$bundle]];
      }
      else {
        $display = $storage->create([
          'targetEntityType' => $entity_type,
          'bundle' => $bundle,
          'mode' => $view_mode,
          'status' => TRUE,
        ]);
      }

      // Let the display know which view mode was originally requested.
      $display->originalMode = $view_mode;

      // Let modules alter the display.
      $display_context = [
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'view_mode' => $view_mode,
      ];
      $this->moduleHandler->alter('entity_view_display', $display, $display_context);

      $displays_by_bundle[$bundle] = $display;
    }

    return $displays_by_bundle;
  }

  public function collectViewDisplay(FieldableEntityInterface $entity, $view_mode): EntityViewDisplayInterface {
    $displays = $this->collectViewDisplays([$entity], $view_mode);
    return $displays[$entity->bundle()];
  }

}
