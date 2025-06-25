<?php

namespace Drupal\node\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\views\Analyzer;
use Drupal\user\RoleInterface;
use Drupal\user\Entity\Role;
use Drupal\views\ViewExecutable;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Views hook implementations for node.
 */
class NodeViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter', order: Order::First)]
  public function viewsDataAlter(array &$data): void {
    $data['users_field_data']['uid']['relationship'] = [
      'title' => $this->t('Content authored'),
      'help' => $this->t('Relate content to the user who created it. This relationship will create one record for each content item created by the user.'),
      'id' => 'standard',
      'base' => 'node_field_data',
      'base field' => 'uid',
      'field' => 'uid',
      'label' => $this->t('nodes'),
    ];

    $data['users_field_data']['uid_representative'] = [
      'relationship' => [
        'title' => $this->t('Representative node'),
        'label'  => $this->t('Representative node'),
        'help' => $this->t('Obtains a single representative node for each user, according to a chosen sort criterion.'),
        'id' => 'groupwise_max',
        'relationship field' => 'uid',
        'outer field' => 'users_field_data.uid',
        'argument table' => 'users_field_data',
        'argument field' => 'uid',
        'base' => 'node_field_data',
        'field' => 'nid',
        'relationship' => 'node_field_data:uid',
      ],
    ];

    if (\Drupal::moduleHandler()->moduleExists('taxonomy')) {
      $data['node_field_data']['term_node_tid'] = [
        'title' => $this->t('Taxonomy terms on node'),
        'help' => $this->t('Relate nodes to taxonomy terms, specifying which vocabulary or vocabularies to use. This relationship will cause duplicated records if there are multiple terms.'),
        'relationship' => [
          'id' => 'node_term_data',
          'label' => $this->t('term'),
          'base' => 'taxonomy_term_field_data',
        ],
        'field' => [
          'title' => $this->t('All taxonomy terms'),
          'help' => $this->t('Display all taxonomy terms associated with a node from specified vocabularies.'),
          'id' => 'taxonomy_index_tid',
          'no group by' => TRUE,
          'click sortable' => FALSE,
        ],
      ];

      $data['node_field_data']['term_node_tid_depth'] = [
        'help' => $this->t('Display content if it has the selected taxonomy terms, or children of the selected terms. Due to additional complexity, this has fewer options than the versions without depth.'),
        'real field' => 'nid',
        'argument' => [
          'title' => $this->t('Has taxonomy term ID (with depth)'),
          'id' => 'taxonomy_index_tid_depth',
          'accept depth modifier' => TRUE,
        ],
        'filter' => [
          'title' => $this->t('Has taxonomy terms (with depth)'),
          'id' => 'taxonomy_index_tid_depth',
        ],
      ];

      $data['node_field_data']['term_node_tid_depth_modifier'] = [
        'title' => $this->t('Has taxonomy term ID depth modifier'),
        'help' => $this->t('Allows the "depth" for Taxonomy: Term ID (with depth) to be modified via an additional contextual filter value.'),
        'argument' => [
          'id' => 'taxonomy_index_tid_depth_modifier',
        ],
      ];
    }
  }

  /**
   * Implements hook_field_views_data_alter().
   *
   * Views integration for entity reference fields referencing taxonomy terms.
   * Adds a term relationship to the default field data.
   *
   * @see views_field_default_views_data()
   */
  #[Hook('views_field_views_data_alter')]
  public function viewsFieldViewsDataAlter(array &$data, FieldStorageConfigInterface $field_storage): void {
    if (\Drupal::moduleHandler()->moduleExists('taxonomy')) {
      if ($field_storage->getType() == 'entity_reference' && $field_storage->getSetting('target_type') == 'taxonomy_term') {
        foreach ($data as $table_name => $table_data) {
          foreach ($table_data as $field_name => $field_data) {
            if (isset($field_data['filter']) && $field_name != 'delta') {
              $data[$table_name][$field_name]['filter']['id'] = 'taxonomy_index_tid';
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_views_analyze().
   */
  #[Hook('views_analyze')]
  public function viewsAnalyze(ViewExecutable $view): array {
    $ret = [];
    // Check for something other than the default display:
    if ($view->storage->get('base_table') == 'node') {
      foreach ($view->displayHandlers as $display) {
        if (!$display->isDefaulted('access') || !$display->isDefaulted('filters')) {
          // Check for no access control.
          $access = $display->getOption('access');
          if (empty($access['type']) || $access['type'] == 'none') {
            $anonymous_role = Role::load(RoleInterface::ANONYMOUS_ID);
            $anonymous_has_access = $anonymous_role && $anonymous_role->hasPermission('access content');
            $authenticated_role = Role::load(RoleInterface::AUTHENTICATED_ID);
            $authenticated_has_access = $authenticated_role && $authenticated_role->hasPermission('access content');
            if (!$anonymous_has_access || !$authenticated_has_access) {
              $ret[] = Analyzer::formatMessage($this->t('Some roles lack permission to access content, but display %display has no access control.', ['%display' => $display->display['display_title']]), 'warning');
            }
            $filters = $display->getOption('filters');
            foreach ($filters as $filter) {
              if ($filter['table'] == 'node' && ($filter['field'] == 'status' || $filter['field'] == 'status_extra')) {
                continue 2;
              }
            }
            $ret[] = Analyzer::formatMessage($this->t('Display %display has no access control but does not contain a filter for published nodes.', ['%display' => $display->display['display_title']]), 'warning');
          }
        }
      }
    }
    foreach ($view->displayHandlers as $display) {
      if ($display->getPluginId() == 'page') {
        if ($display->getOption('path') == 'node/%') {
          $ret[] = Analyzer::formatMessage($this->t('Display %display has set node/% as path. This will not produce what you want. If you want to have multiple versions of the node view, use Layout Builder.', ['%display' => $display->display['display_title']]), 'warning');
        }
      }
    }
    return $ret;
  }

  /**
   * Implements hook_views_query_substitutions().
   */
  #[Hook('views_query_substitutions')]
  public function viewsQuerySubstitutions(ViewExecutable $view): array {
    $account = \Drupal::currentUser();
    return [
      '***ADMINISTER_NODES***' => intval($account->hasPermission('administer nodes')),
      '***VIEW_OWN_UNPUBLISHED_NODES***' => intval($account->hasPermission('view own unpublished content')),
      '***BYPASS_NODE_ACCESS***' => intval($account->hasPermission('bypass node access')),
    ];
  }

}
