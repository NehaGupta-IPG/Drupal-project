<?php

declare(strict_types=1);

namespace Drupal\block_content\Hook;

use Drupal\block\BlockInterface;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides hooks for block_content integrating with block module.
 */
class BlockContentBlockHooks {

  use StringTranslationTrait;

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter() for block templates.
   */
  #[Hook('theme_suggestions_block_alter')]
  public function themeSuggestionsBlockAlter(array &$suggestions, array $variables): void {
    $suggestions_new = [];
    $content = $variables['elements']['content'];
    $block_content = $variables['elements']['content']['#block_content'] ?? NULL;
    if ($block_content instanceof BlockContentInterface) {
      $bundle = $content['#block_content']->bundle();
      $view_mode = strtr($variables['elements']['content']['#view_mode'], '.', '_');
      $suggestions_new[] = 'block__block_content__view__' . $view_mode;
      $suggestions_new[] = 'block__block_content__type__' . $bundle;
      $suggestions_new[] = 'block__block_content__view_type__' . $bundle . '__' . $view_mode;
      if (!empty($variables['elements']['#id'])) {
        $suggestions_new[] = 'block__block_content__id__' . $variables['elements']['#id'];
        $suggestions_new[] = 'block__block_content__id_view__' . $variables['elements']['#id'] . '__' . $view_mode;
      }
      // Remove duplicate block__block_content.
      $suggestions = array_unique($suggestions);
      array_splice($suggestions, 1, 0, $suggestions_new);
    }
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity) : array {
    $operations = [];
    if ($entity instanceof BlockInterface) {
      $plugin = $entity->getPlugin();
      if ($plugin->getBaseId() === 'block_content') {
        $custom_block = $this->entityTypeManager->getStorage('block_content')->loadByProperties(['uuid' => $plugin->getDerivativeId()]);
        $custom_block = reset($custom_block);
        if ($custom_block && $custom_block->access('update')) {
          // Show a link to edit Block Content when getting the operations for
          // a Block config.
          $operations['block-edit'] = [
            'title' => $this->t('Edit block'),
            'url' => $custom_block->toUrl('edit-form')->setOptions([]),
            'weight' => 50,
          ];
        }
      }
    }
    return $operations;
  }

}
