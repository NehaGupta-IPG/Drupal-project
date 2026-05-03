<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Language hook implementations for content_translation.
 */
readonly class ContentTranslationLanguageHooks {

  public function __construct(
    private readonly ContentTranslationManagerInterface $contentTranslationManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_language_types_info_alter().
   */
  #[Hook('language_types_info_alter')]
  public function languageTypesInfoAlter(array &$language_types): void {
    // Make content language negotiation configurable by removing the 'locked'
    // flag.
    $language_types[LanguageInterface::TYPE_CONTENT]['locked'] = FALSE;
    unset($language_types[LanguageInterface::TYPE_CONTENT]['fixed']);
  }

  /**
   * Implements hook_language_fallback_candidates_OPERATION_alter().
   *
   * Performs language fallback for inaccessible translations.
   */
  #[Hook('language_fallback_candidates_entity_view_alter')]
  public function languageFallbackCandidatesEntityViewAlter(&$candidates, $context): void {
    $entity = $context['data'];
    $entity_type_id = $entity->getEntityTypeId();
    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $manager */
    $manager = $this->contentTranslationManager;
    if ($manager->isEnabled($entity_type_id, $entity->bundle())) {
      /** @var \Drupal\content_translation\ContentTranslationHandlerInterface $handler */
      $handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'translation');
      foreach ($entity->getTranslationLanguages() as $langcode => $language) {
        $metadata = $manager->getTranslationMetadata($entity->getTranslation($langcode));
        if (!$metadata->isPublished()) {
          $access = $handler->getTranslationAccess($entity, 'update');
          $entity->addCacheableDependency($access);
          if (!$access->isAllowed()) {
            // If the user has no translation update access, also check view
            // access for that translation, to allow other modules to allow
            // access to unpublished translations.
            $access = $entity->getTranslation($langcode)->access('view', NULL, TRUE);
            $entity->addCacheableDependency($access);
            if (!$access->isAllowed()) {
              unset($candidates[$langcode]);
            }
          }
        }
      }
    }
  }

}
