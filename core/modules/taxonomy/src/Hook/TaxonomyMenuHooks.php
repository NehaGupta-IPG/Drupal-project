<?php

namespace Drupal\taxonomy\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Menu hook implementations for taxonomy.
 */
class TaxonomyMenuHooks {

  /**
   * Implements hook_local_tasks_alter().
   *
   * @todo Evaluate removing as part of https://www.drupal.org/node/2358923.
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(array &$local_tasks): void {
    $local_task_key = 'config_translation.local_tasks:entity.taxonomy_vocabulary.config_translation_overview';
    if (isset($local_tasks[$local_task_key])) {
      // The config_translation module expects the base route to be
      // entity.taxonomy_vocabulary.edit_form like it is for other configuration
      // entities. Taxonomy uses the overview_form as the base route.
      $local_tasks[$local_task_key]['base_route'] = 'entity.taxonomy_vocabulary.overview_form';
    }
  }

}
