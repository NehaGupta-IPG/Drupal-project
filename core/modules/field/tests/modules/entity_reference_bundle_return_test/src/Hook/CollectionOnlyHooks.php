<?php

declare(strict_types=1);

namespace Drupal\entity_reference_bundle_return_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\LocalActionWithDestination;
use Drupal\entity_reference_bundle_return_test\CollectionOnlyVocabularyRouteProvider;

/**
 * Provides a collection-only bundle management definition.
 */
class CollectionOnlyHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    if (\Drupal::state()->get('entity_reference_bundle_return_test.collection_only')) {
      $definition = $entity_types['taxonomy_vocabulary'];
      $links = $definition->getLinkTemplates();
      unset($links['add-form']);
      $definition->set('links', $links);
      $handlers = $definition->getHandlerClasses();
      $handlers['route_provider']['html'] = CollectionOnlyVocabularyRouteProvider::class;
      $definition->setHandlerClass('route_provider', $handlers['route_provider']);
    }
  }

  /**
   * Implements hook_menu_local_actions_alter().
   */
  #[Hook('menu_local_actions_alter')]
  public function menuLocalActionsAlter(array &$local_actions): void {
    $local_actions['entity.taxonomy_vocabulary.add_form']['class'] = LocalActionWithDestination::class;
  }

}
