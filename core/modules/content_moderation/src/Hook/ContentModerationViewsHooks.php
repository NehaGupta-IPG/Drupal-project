<?php

namespace Drupal\content_moderation\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\ViewsData;

/**
 * Hook implementations for content_moderation.
 */
class ContentModerationViewsHooks {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModerationInformationInterface $moderationInformation,
  ) {}

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $viewsData = new ViewsData(
      $this->entityTypeManager,
      $this->moderationInformation
    );
    return $viewsData->getViewsData();
  }

 /**
   * Implements hook_views_data_alter().
   * 
   * This hook removes extra language field in order to fix the issue #2846605
   * where unpublished translations of moderated content are not listed in 
   * node_field_data table. It will list only published translations. 
   * This is a problem because nid relationship has extra condition to match the language 
   * 
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    if (isset($data['node_field_revision']['nid']['relationship']['extra'])) {
      unset($data['node_field_revision']['nid']['relationship']['extra']);
    }
  }

}
