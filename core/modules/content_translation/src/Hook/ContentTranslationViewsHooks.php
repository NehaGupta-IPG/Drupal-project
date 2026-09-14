<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * View Hook implementations for content_translation.
 */
class ContentTranslationViewsHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ContentTranslationManagerInterface $contentTranslationManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    // Add the content translation entity link definition to Views data for
    // entity types having translation enabled.
    $entity_types = $this->entityTypeManager->getDefinitions();
    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $manager */
    $manager = $this->contentTranslationManager;
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $base_table = $entity_type->getBaseTable();
      if (isset($base_table, $data[$base_table]) && $entity_type->hasLinkTemplate('drupal:content-translation-overview') && $manager->isEnabled($entity_type_id)) {
        $t_arguments = ['@entity_type_label' => $entity_type->getLabel()];
        $data[$base_table]['translation_link'] = [
          'field' => [
            'title' => $this->t('Link to translate @entity_type_label', $t_arguments),
            'help' => $this->t('Provide a translation link to the @entity_type_label.', $t_arguments),
            'id' => 'content_translation_link',
          ],
        ];
      }
    }
  }

}
