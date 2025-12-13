<?php

declare(strict_types=1);

namespace Drupal\link;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Service for generating entity link suggestions.
 *
 * @internal
 */
class EntityLinkSuggestions {

  use StringTranslationTrait;

  /**
   * The default limit for matches.
   */
  protected const DEFAULT_LIMIT = 100;

  /**
   * Constructs an EntityLinkSuggestions service.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selectionPluginManager
   *   The selection plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    protected readonly SelectionPluginManagerInterface $selectionPluginManager,
    protected readonly EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Gets allowed bundles for entity link suggestions.
   *
   * Filters bundles based on the 'ckeditor5_link_suggestions' configuration.
   *
   * @return array
   *   An array keyed by entity type ID, with values being arrays of bundle IDs
   *   that are allowed for link suggestions.
   */
  public function getAllowedBundles(): array {
    $allowed_bundles = [];
    $all_bundle_info = $this->entityTypeBundleInfo->getAllBundleInfo();
    foreach ($all_bundle_info as $entity_type => $bundles) {
      foreach ($bundles as $key => $bundle) {
        // @todo not sure what to do about the `ckeditor5` id in the key.
        if (!empty($bundle['ckeditor5_link_suggestions'])) {
          $allowed_bundles[$entity_type][$key] = $key;
        }
      }
    }
    return $allowed_bundles;
  }

  /**
   * Gets suggestions for a request with full workflow handling.
   *
   * This method handles the complete workflow from input to suggestions,
   * including bundle filtering, host entity prioritization, and fallback
   * handling.
   *
   * @param string $input
   *   The search input string (will be lowercased).
   * @param string|null $host_entity_type_id
   *   The entity type ID of the host entity, if any.
   * @param string|null $host_entity_langcode
   *   The language code of the host entity. If NULL, will use the current
   *   language.
   * @param \Drupal\Core\Language\LanguageManagerInterface|null $languageManager
   *   Optional language manager for getting default language. If not provided
   *   and $host_entity_langcode is NULL, will throw an exception.
   *
   * @return array
   *   An array of suggestion objects with populated entity data.
   */
  public function getSuggestionsForRequest(
    string $input,
    ?string $host_entity_type_id = NULL,
    ?string $host_entity_langcode = NULL,
    ?LanguageManagerInterface $languageManager = NULL,
  ): array {
    if (empty($input)) {
      return [];
    }

    // Get default language if not provided.
    if ($host_entity_langcode === NULL && $languageManager !== NULL) {
      $host_entity_langcode = $languageManager->getCurrentLanguage()->getId();
    }
    if ($host_entity_langcode === NULL) {
      throw new \InvalidArgumentException('host_entity_langcode must be provided or languageManager must be passed.');
    }

    $allowed_bundles = $this->getAllowedBundles();
    $suggestions = [];

    // First, check if the input matches an entity URL.
    // This handles cases where user pastes or types an exact entity URL.
    // Note: We check the original input (not lowercased) because URLs are
    // case-sensitive.
    $matched_entity = $this->findEntityByUrl($input, array_keys($allowed_bundles), $host_entity_langcode);
    if ($matched_entity) {
      // If we found a matching entity, return it as the only suggestion.
      // This prioritizes exact URL matches over text-based searches.
      return [$this->createSuggestion($matched_entity)];
    }

    // Lowercase the input for text-based searching (but not for URL matching
    // above).
    $input = mb_strtolower($input);

    // First, find suggestions for the host entity type if it's in the allowed
    // bundles and prioritize it.
    if ($host_entity_type_id && in_array($host_entity_type_id, array_keys($allowed_bundles), TRUE)) {
      $suggestions = $this->getSuggestions(
        $host_entity_type_id,
        $allowed_bundles[$host_entity_type_id],
        $input,
        $host_entity_langcode
      );
    }

    // Second, find suggestions for all other entity types, in the specified
    // order.
    $allowed_entity_type_ids = array_keys($allowed_bundles);
    foreach ($allowed_bundles as $entity_type_id => $bundles) {
      if ($host_entity_type_id === $entity_type_id) {
        continue;
      }
      if (in_array($entity_type_id, $allowed_entity_type_ids, TRUE)) {
        $suggestions = array_merge($suggestions, $this->getSuggestions(
          $entity_type_id,
          $bundles,
          $input,
          $host_entity_langcode
        ));
      }
    }

    return $suggestions;
  }

  /**
   * Gets the suggestions.
   *
   * @param string $target_entity_type_id
   *   An entity type to get suggestions for.
   * @param null|string[] $target_bundles
   *   NULL to allow all bundles, a list of bundle names to restrict to those
   *   bundles.
   * @param string $string
   *   The string to search.
   * @param string $host_entity_langcode
   *   The langcode of the host entity.
   *
   * @return array
   *   An array of suggestion objects with populated entity data.
   *
   * @see \Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection::defaultConfiguration()
   */
  public function getSuggestions(
    string $target_entity_type_id,
    ?array $target_bundles,
    string $string,
    string $host_entity_langcode,
  ): array {
    // If the user input is a current entity URL, don't get more suggestions.
    if ($entity_id = $this->findEntityIdByUrl($target_entity_type_id, $string)) {
      $entity = $this->entityTypeManager->getStorage($target_entity_type_id)->load($entity_id);
      if ($entity?->language()->getId() === $host_entity_langcode) {
        return [$this->createSuggestion($entity)];
      }
    }

    // Do not call ::getPluginId() or ::getInstance() because this favors a
    // "link_target" variant of the default selection plugin for the given
    // entity type, if it exists.
    $selection_handler_groups = $this->selectionPluginManager->getSelectionGroups($target_entity_type_id);
    if (!array_key_exists('default', $selection_handler_groups)) {
      return [];
    }
    // Sort the selection plugins by weight and select the best match.
    uasort($selection_handler_groups['default'], ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
    end($selection_handler_groups['default']);
    // Select the link_target variant of the default selection plugin for the
    // entity type, if it exists. Otherwise, select the next best match.
    $link_target_selection_plugin_id = "default:{$target_entity_type_id}_link_target";
    $plugin_id = array_key_exists($link_target_selection_plugin_id, $selection_handler_groups['default'])
      ? $link_target_selection_plugin_id
      : key($selection_handler_groups['default']);
    $selection = $this->selectionPluginManager->createInstance($plugin_id, [
      'target_type' => $target_entity_type_id,
      'target_bundles' => $target_bundles,
    ]);

    $entities_by_bundle = $selection->getReferenceableEntities($string, 'CONTAINS', self::DEFAULT_LIMIT);
    // DefaultSelection::getReferenceableEntities() loads entities and even
    // their translation but then only keeps bundle, entity ID and label. Reload
    // them to generate rich results. Note that performance overhead of this is
    // minimal because all this data is statically cached already anyway.
    $entity_ids = array_reduce($entities_by_bundle, function ($flattened, $bundle_entities) {
      return array_merge($flattened, array_keys($bundle_entities));
    }, []);
    $entities = $this->entityTypeManager->getStorage($target_entity_type_id)->loadMultiple($entity_ids);

    $suggestions = [];
    foreach ($entities as $entity) {
      $entity_translation = $entity;
      if ($entity->getEntityType()->isTranslatable() && method_exists($entity, 'hasTranslation') && method_exists($entity, 'getTranslation')) {
        if (call_user_func([$entity, 'hasTranslation'], $host_entity_langcode)) {
          $entity_translation = call_user_func([$entity, 'getTranslation'], $host_entity_langcode);
        }
      }
      if ($entity_translation->language()->getId() === $host_entity_langcode) {
        $suggestions[] = $this->createSuggestion($entity_translation);
      }
    }

    return $suggestions;
  }

  /**
   * Creates a suggestion.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The matched entity.
   *
   * @return array
   *   A suggestion object with populated entity data.
   */
  protected function createSuggestion(EntityInterface $entity): array {
    $url = $entity->toUrl('canonical')->toString();
    return [
      'description' => $this->computeDescription($entity) ?? '',
      'entity_type_id' => $entity->getEntityTypeId(),
      'entity_uuid' => $entity->uuid(),
      'group' => $this->computeGroup($entity),
      'label' => $entity->label(),
      // Use the canonical URI as a valid fallback for the href. The
      // text_format filter will transform this to the final URL (e.g., alias).
      'path' => $url,
      'href' => $url,
    ];
  }

  /**
   * Computes a suggestion description.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The suggested entity for which to compute a description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   A suggestion description.
   */
  protected function computeDescription(EntityInterface $entity): ?TranslatableMarkup {
    $entity_type = $entity->getEntityType();
    $owner = NULL;
    if ($entity_type->hasKey('owner') && method_exists($entity, 'getOwner')) {
      $owner_entity = call_user_func([$entity, 'getOwner']);
      $owner = $owner_entity ? $owner_entity->getDisplayName() : NULL;
    }
    $creation_datetime = NULL;
    if (method_exists($entity, 'getCreatedTime')) {
      $created_time = call_user_func([$entity, 'getCreatedTime']);
      $creation_datetime = $created_time ? $this->dateFormatter->format($created_time, 'medium') : NULL;
    }

    $arg_owner = ['@owner' => $owner];
    $arg_creation_datetime = ['@creation-datetime' => $creation_datetime];

    if ($owner && $creation_datetime) {
      return $this->t('by @owner on @creation-datetime', $arg_owner + $arg_creation_datetime);
    }
    elseif ($owner) {
      return $this->t('by @owner', $arg_owner);
    }
    elseif ($creation_datetime) {
      return $this->t('on @creation-datetime', $arg_creation_datetime);
    }
    else {
      return NULL;
    }
  }

  /**
   * Computes a suggestion group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The suggested entity for which to compute the group.
   *
   * @return string
   *   A suggestion group.
   */
  protected function computeGroup(EntityInterface $entity): string {
    // If the entity type does not have bundles, the group is very simple.
    if ($entity->getEntityType()->getBundleEntityType() === NULL) {
      return $entity->getEntityType()->getLabel();
    }

    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());

    return $entity->getEntityType()->getLabel() . ' - ' . $bundles[$entity->bundle()]['label'];
  }

  /**
   * Finds entity ID from the given input.
   *
   * @param string $target_entity_type_id
   *   An entity type to get suggestions for.
   * @param string $user_input
   *   The string to url parse.
   *
   * @return string|null
   *   An entity ID parsed from the user input, otherwise NULL.
   */
  protected function findEntityIdByUrl(string $target_entity_type_id, string $user_input): ?string {
    $expected_url_prefix = "/$target_entity_type_id/";
    if (str_starts_with($user_input, $expected_url_prefix)) {
      return substr($user_input, strlen($expected_url_prefix));
    }
    return NULL;
  }

  /**
   * Finds an entity by URL across multiple entity types.
   *
   * This checks if the input string matches an entity URL for any of the
   * allowed entity types. This handles both canonical URLs and path aliases.
   *
   * @param string $input
   *   The input string (URL or path).
   * @param array $allowed_entity_type_ids
   *   Array of entity type IDs to check.
   * @param string $host_entity_langcode
   *   The language code to match.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The matched entity, or NULL if not found.
   */
  protected function findEntityByUrl(string $input, array $allowed_entity_type_ids, string $host_entity_langcode): ?EntityInterface {
    // Normalize the input - ensure it starts with / if it's a path.
    $scheme = parse_url($input, PHP_URL_SCHEME);
    if (!$scheme && !str_starts_with($input, '/')) {
      $input = '/' . $input;
    }

    // Try to resolve as a path to an entity (handles aliases).
    if (!$scheme || $scheme === 'internal') {
      try {
        $uri = $scheme === 'internal' ? $input : 'internal:' . $input;
        $route_name = Url::fromUri($uri)->getRouteName();
        $params = array_filter(Url::fromUri($uri)->getRouteParameters());

        foreach ($params as $possibly_an_entity_type => $possibly_an_entity_id) {
          // Only check allowed entity types.
          if (!in_array($possibly_an_entity_type, $allowed_entity_type_ids, TRUE)) {
            continue;
          }

          // Return only the entity, if this is a canonical route.
          if ($route_name === 'entity.' . $possibly_an_entity_type . '.canonical') {
            $entity = $this->entityTypeManager->getStorage($possibly_an_entity_type)
              ->load($possibly_an_entity_id);
            if ($entity instanceof EntityInterface) {
              // Get the appropriate translation.
              $entity_translation = $entity;
              if ($entity->getEntityType()->isTranslatable() && method_exists($entity, 'hasTranslation') && method_exists($entity, 'getTranslation')) {
                if (call_user_func([$entity, 'hasTranslation'], $host_entity_langcode)) {
                  $entity_translation = call_user_func([$entity, 'getTranslation'], $host_entity_langcode);
                }
              }
              // Only return if language matches.
              if ($entity_translation->language()->getId() === $host_entity_langcode) {
                return $entity_translation;
              }
            }
          }
        }
      }
      catch (\Exception) {
        // If URL resolution fails, continue to check simple patterns.
      }
    }

    // Fallback: Check simple URL patterns like "/entity_type/id" for each
    // allowed entity type.
    foreach ($allowed_entity_type_ids as $entity_type_id) {
      $entity_id = $this->findEntityIdByUrl($entity_type_id, $input);
      if ($entity_id) {
        $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
        if ($entity instanceof EntityInterface) {
          // Get the appropriate translation.
          $entity_translation = $entity;
          if ($entity->getEntityType()->isTranslatable() && method_exists($entity, 'hasTranslation') && method_exists($entity, 'getTranslation')) {
            if (call_user_func([$entity, 'hasTranslation'], $host_entity_langcode)) {
              $entity_translation = call_user_func([$entity, 'getTranslation'], $host_entity_langcode);
            }
          }
          // Only return if language matches.
          if ($entity_translation->language()->getId() === $host_entity_langcode) {
            return $entity_translation;
          }
        }
      }
    }

    return NULL;
  }

}
