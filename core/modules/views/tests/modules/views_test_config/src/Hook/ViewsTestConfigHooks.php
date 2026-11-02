<?php

declare(strict_types=1);

namespace Drupal\views_test_config\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for views_test_config.
 */
class ViewsTestConfigHooks {

  /**
   * Implements hook_ENTITY_TYPE_load().
   */
  #[Hook('view_load')]
  public function viewLoad(array $views): void {
    // Emulate a severely broken view: this kind of view configuration cannot be
    // saved, it can likely be returned only by a corrupt active configuration.
    $broken_view_id = \Drupal::state()->get('views_test_config.broken_view', '');
    if (isset($views[$broken_view_id])) {
      $display =& $views[$broken_view_id]->getDisplay('default');
      $display['display_options']['fields']['id_broken'] = NULL;
    }
  }

  /**
   * Implements hook_views_post_render().
   */
  #[Hook('views_post_render')]
  public function viewsPostRender(ViewExecutable $view, &$output, CachePluginBase $cache): void {
    if (\Drupal::state()->get('views_test_config.views_post_render_cache_tag')) {
      \Drupal::state()->set('views_test_config.views_post_render_called', TRUE);
      // Set a cache key on output to ensure
      // ViewsSelection::stripAdminAndAnchorTagsFromResults correctly handles
      // elements that aren't result rows.
      $output['#cache']['tags'][] = 'foo';
    }
  }

  /**
   * Implements hook_views_plugins_area_alter().
   */
  #[Hook('views_plugins_area_alter')]
  public function viewsPluginsAreaAlter(array &$definitions) : void {
    $this->disableBrokenHandler($definitions, 'area');
  }

  /**
   * Implements hook_views_plugins_argument_alter().
   */
  #[Hook('views_plugins_argument_alter')]
  public function viewsPluginsArgumentAlter(array &$definitions) : void {
    $this->disableBrokenHandler($definitions, 'argument');
  }

  /**
   * Implements hook_views_plugins_field_alter().
   */
  #[Hook('views_plugins_field_alter')]
  public function viewsPluginsFieldAlter(array &$definitions) : void {
    $this->disableBrokenHandler($definitions, 'field');
  }

  /**
   * Implements hook_views_plugins_filter_alter().
   */
  #[Hook('views_plugins_filter_alter')]
  public function viewsPluginsFilterAlter(array &$definitions) : void {
    $this->disableBrokenHandler($definitions, 'filter');
  }

  /**
   * Implements hook_views_plugins_relationship_alter().
   */
  #[Hook('views_plugins_relationship_alter')]
  public function viewsPluginsRelationshipAlter(array &$definitions) : void {
    $this->disableBrokenHandler($definitions, 'relationship');
  }

  /**
   * Implements hook_views_plugins_sort_alter().
   */
  #[Hook('views_plugins_sort_alter')]
  public function viewsPluginsSortAlter(array &$definitions) : void {
    $this->disableBrokenHandler($definitions, 'sort');
  }

  /**
   * Helper method to remove broken handler.
   */
  public function disableBrokenHandler(array &$definitions, string $handler_type): void {
    if (in_array($handler_type, \Drupal::state()->get('views_test_config_disable_broken_handler', []))) {
      unset($definitions['broken']);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   *
   * Puts the data-progress-type attributes named in the
   * views_test_config.progress_type state on the exposed form of list_view.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($form['#id'] !== 'views-exposed-form-list-view-page-1') {
      return;
    }
    $types = \Drupal::state()->get('views_test_config.progress_type', []);
    if (isset($types['button'])) {
      $form['actions']['submit']['#attributes']['data-progress-type'] = $types['button'];
    }
    if (isset($types['form'])) {
      $form['#attributes']['data-progress-type'] = $types['form'];
    }
    if (isset($types['before_form'])) {
      $form['#prefix'] = '<span data-progress-type="' . $types['before_form'] . '"></span>';
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for views_view.
   *
   * Wraps the pager of list_view in an element with the data-progress-type
   * named in the views_test_config.progress_type state.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $types = \Drupal::state()->get('views_test_config.progress_type', []);
    if ($variables['view']->id() === 'list_view' && isset($types['pager']) && !empty($variables['pager'])) {
      $variables['pager']['#prefix'] = '<div data-progress-type="' . $types['pager'] . '">';
      $variables['pager']['#suffix'] = '</div>';
    }
  }

}
