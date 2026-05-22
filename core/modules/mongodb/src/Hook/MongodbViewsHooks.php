<?php

namespace Drupal\mongodb\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\field\FieldStorageConfigInterface;

// cspell:ignore databasedriver

/**
 * Hook implementations for MongoDB.
 */
class MongodbViewsHooks {

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(&$data): void {
    // Overrides for the module dblog.
    if (isset($data['watchdog']['uid']['relationship']['base'])) {
      $data['watchdog']['uid']['relationship']['base'] = 'users';
    }

    // Overrides for the module history.
    if (isset($data['history']['table']['join']['node_field_data'])) {
      $data['history']['table']['join']['node'] = $data['history']['table']['join']['node_field_data'];
      unset($data['history']['table']['join']['node_field_data']);
    }

    // Overrides for the module taxonomy.
    if (isset($data['node_field_data']['term_node_tid'])) {
      $data['node_field_data']['term_node_tid']['relationship']['base'] = 'taxonomy_term_data';
      $data['node_field_data']['term_node_tid']['relationship']['id'] = 'node';
      $data['node']['term_node_tid'] = $data['node_field_data']['term_node_tid'];
      unset($data['node_field_data']['term_node_tid']);
    }
    if (isset($data['node_field_data']['term_node_tid_depth'])) {
      $data['node']['term_node_tid_depth'] = $data['node_field_data']['term_node_tid_depth'];
      unset($data['node_field_data']['term_node_tid_depth']);
    }
    if (isset($data['node_field_data']['term_node_tid_depth_modifier'])) {
      $data['node']['term_node_tid_depth_modifier'] = $data['node_field_data']['term_node_tid_depth_modifier'];
      unset($data['node_field_data']['term_node_tid_depth_modifier']);
    }

    // Overrides for the module content_moderation.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $moderation_information = \Drupal::service('content_moderation.moderation_information');
      $entity_type_manager = \Drupal::entityTypeManager();
      $entity_types_with_moderation = array_filter($entity_type_manager->getDefinitions(), function (EntityTypeInterface $type) use ($moderation_information) {
        return $moderation_information->isModeratedEntityType($type);
      });
      foreach ($entity_types_with_moderation as $entity_type) {
        if (isset($data[$entity_type->getDataTable()]['moderation_state'])) {
          $data[$entity_type->getBaseTable()]['moderation_state'] = $data[$entity_type->getDataTable()]['moderation_state'];
          unset($data[$entity_type->getDataTable()]['moderation_state']);
        }
        if (isset($data[$entity_type->getRevisionDataTable()]['moderation_state'])) {
          $data[$entity_type->getBaseTable()]['moderation_state'] = $data[$entity_type->getRevisionDataTable()]['moderation_state'];
          unset($data[$entity_type->getRevisionDataTable()]['moderation_state']);
        }
        if (isset($data[$entity_type->getRevisionTable()]['moderation_state'])) {
          $data[$entity_type->getBaseTable()]['moderation_state'] = $data[$entity_type->getRevisionTable()]['moderation_state'];
          unset($data[$entity_type->getRevisionTable()]['moderation_state']);
        }
      }
    }
  }

  /**
   * Implements hook_field_views_data_alter().
   */
  #[Hook('field_views_data_alter')]
  public function fieldViewsDataAlter(&$data, FieldStorageConfigInterface $field_storage, $module): void {
    // Override for the module "datetime".
    $column_name = $field_storage->getName() . '_' . $field_storage->getMainPropertyName();
    foreach ($data as $table_name => $table_data) {
      if (!empty($data[$table_name][$column_name]['real field'])) {
        $real_field = $data[$table_name][$column_name]['real field'];
        foreach (['year', 'month', 'day', 'week', 'year_month', 'full_date'] as $date_type) {
          if (isset($data[$table_name][$column_name . '_' . $date_type]['argument'])) {
            // Unset the "field" value and add the "real field" value.
            unset($data[$table_name][$column_name . '_' . $date_type]['argument']['field']);
            $data[$table_name][$column_name . '_' . $date_type]['argument']['real field'] = $real_field;
          }
        }
      }
    }
  }

  /**
   * Implements hook_views_data_DATABASEDRIVER_alter().
   */
  #[Hook('views_data_DATABASEDRIVER_alter')]
  public function viewsDataMongodbAlter(&$data): void {

    // Override for the module "comment".
    if (\Drupal::hasService('comment.manager')) {
      foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
        if ($entity_type_id == 'comment' || !$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
          continue;
        }
        $fields = \Drupal::service('comment.manager')->getFields($entity_type_id);
        $base_table = $entity_type->getBaseTable();
        $data_table = $entity_type->getDataTable();

        if ($fields) {
          if (isset($data[$data_table]['comments_link'])) {
            $data[$base_table]['comments_link'] = $data[$data_table]['comments_link'];
            unset($data[$data_table]['comments_link']);
          }

          if (isset($data[$data_table]['uid_touch'])) {
            if (isset($data[$data_table]['uid_touch']['argument']['name table'])) {
              $data[$data_table]['uid_touch']['argument']['name table'] = 'users';
            }
            if (isset($data[$data_table]['uid_touch']['filter']['name table'])) {
              $data[$data_table]['uid_touch']['filter']['name table'] = 'users';
            }
            $data[$base_table]['uid_touch'] = $data[$data_table]['uid_touch'];
            unset($data[$data_table]['uid_touch']);
          }

          foreach ($fields as $field_name => $field) {
            if (isset($data[$data_table][$field_name . '_cid'])) {
              if (isset($data[$data_table][$field_name . '_cid']['relationship']['base'])) {
                $data[$data_table][$field_name . '_cid']['relationship']['base'] = 'users';
              }
              $data[$base_table][$field_name . '_cid'] = $data[$data_table][$field_name . '_cid'];
              unset($data[$data_table][$field_name . '_cid']);
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_views_plugins_argument_alter().
   */
  #[Hook('views_plugins_argument_alter')]
  public function viewsPluginsArgumentAlter(array &$plugins) : void {
    foreach ($plugins as $id => $plugin) {
      switch ($id) {
        case 'date_fulldate':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\FullDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date_year_month':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\YearMonthDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'many_to_one':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\ManyToOne';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'null':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\NullArgument';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date_month':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\MonthDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'language':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\LanguageArgument';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date_year':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\YearDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date_week':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\WeekDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\Date';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'number_list_field':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\NumberListField';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'numeric':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\NumericArgument';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'formula':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\Formula';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'groupby_numeric':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\GroupByNumeric';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'string':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\StringArgument';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'string_list_field':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\StringListField';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'standard':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\Standard';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date_day':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DayDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module "comment".
        case 'argument_comment_user_uid':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\CommentUserUid';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module "datetime".
        case 'datetime':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DatetimeDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'datetime_day':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DatetimeDayDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'datetime_full_date':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DatetimeFullDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'datetime_month':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DatetimeMonthDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'datetime_week':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DatetimeWeekDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'datetime_year':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DatetimeYearDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'datetime_year_month':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\DatetimeYearMonthDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module "user".
        case 'user_uid':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\Uid';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'user__roles_rid':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\RolesRid';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module "node".
        case 'node_nid':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\Nid';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_type':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\Type';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_uid_revision':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\UidRevision';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_vid':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\Vid';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module taxonomy.
        case 'taxonomy_index_tid_depth':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\argument\TaxonomyIndexTidDepth';
          $plugins[$id]['provider'] = 'mongodb';
          break;

      }
    }
  }

  /**
   * Implements hook_views_plugins_field_alter().
   */
  #[Hook('views_plugins_field_alter')]
  public function viewsPluginsFieldAlter(array &$plugins) : void {
    foreach ($plugins as $id => $plugin) {
      switch ($id) {
        case 'entity_label':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\EntityLabel';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'machine_name':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\MachineName';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'markup':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Markup';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'entity_link_edit':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\EntityLinkEdit';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'file_size':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\FileSize';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Date';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'entity_operations':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\EntityOperations';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'numeric':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\NumericField';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'entity_link':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\EntityLink';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'bulk_form':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\BulkForm';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'rendered_entity':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\RenderedEntity';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'custom':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Custom';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'boolean':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Boolean';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'entity_link_delete':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\EntityLinkDelete';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'url':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Url';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'standard':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Standard';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'language':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\LanguageField';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'counter':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Counter';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'dropbutton':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Dropbutton';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'serialized':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Serialized';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'time_interval':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\TimeInterval';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'field':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\EntityField';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module comment.
        case 'node_new_comments':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\NodeNewComments';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module content_moderation.
        case 'moderation_state_field':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\ModerationStateField';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module history.
        case 'history_user_timestamp':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\HistoryUserTimestamp';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module node.
        case 'node_revision_link':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\RevisionLink';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_revision_link_delete':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\RevisionLinkDelete';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_revision_link_revert':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\RevisionLinkRevert';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_bulk_form':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\NodeBulkForm';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Node';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module user.
        case 'user_bulk_form':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\UserBulkForm';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'user_data':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\UserData';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'user_roles':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Roles';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'user_permissions':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\field\Permissions';
          $plugins[$id]['provider'] = 'mongodb';
          break;

      }
    }
  }

  /**
   * Implements hook_views_plugins_filter_alter().
   */
  #[Hook('views_plugins_filter_alter')]
  public function viewsPluginsFilterAlter(array &$plugins) : void {
    foreach ($plugins as $id => $plugin) {
      switch ($id) {
        case 'combine':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Combine';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'latest_translation_affected_revision':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\LatestTranslationAffectedRevision';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'date':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Date';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'bundle':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Bundle';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'groupby_numeric':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\GroupByNumeric';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'list_field':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\ListField';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'in_operator':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\InOperator';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'language':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\LanguageFilter';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'string':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\StringFilter';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'many_to_one':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\ManyToOne';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'standard':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Standard';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'numeric':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\NumericFilter';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'equality':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Equality';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'boolean_string':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\BooleanOperatorString';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'boolean':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\BooleanOperator';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'latest_revision':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\LatestRevision';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module content_moderation.
        case 'moderation_state_filter':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\ModerationStateFilter';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module history.
        case 'history_user_timestamp':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\HistoryUserTimestamp';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module media.
        case 'media_status':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\MediaStatus';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module taxonomy.
        case 'taxonomy_index_tid':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\TaxonomyIndexTid';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'taxonomy_index_tid_depth':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\TaxonomyIndexTidDepth';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module user.
        case 'user_current':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Current';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'user_roles':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Roles';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'user_name':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\UserName';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'user_permissions':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Permissions';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module dblog.
        case 'dblog_types':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\DblogTypes';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module datetime.
        case 'datetime':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\DatetimeDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module node.
        case 'node_access':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Access';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_status':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\Status';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_uid_revision':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\UidRevision';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Below are only used in testing.
        case 'test_filter':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\FilterTest';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'boolean_default':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\FilterBooleanOperatorDefaultTest';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'views_test_test_cache_context':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\filter\ViewsTestCacheContextFilter';
          $plugins[$id]['provider'] = 'mongodb';
          break;

      }
    }
  }

  /**
   * Implements hook_views_plugins_join_alter().
   */
  #[Hook('views_plugins_join_alter')]
  public function viewsPluginsJoinAlter(array &$plugins) : void {
    foreach ($plugins as $id => $plugin) {
      switch ($id) {
        case 'casted_int_field_join':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\join\CastedIntFieldJoin';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'subquery':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\join\Subquery';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'field_or_language_join':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\join\FieldOrLanguageJoin';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'standard':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\join\Standard';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Below are only used in testing.
        case 'join_test':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\join\JoinTest';
          $plugins[$id]['provider'] = 'mongodb';
          break;

      }
    }
  }

  /**
   * Implements hook_views_plugins_query_alter().
   */
  #[Hook('views_plugins_query_alter')]
  public function viewsPluginsQueryAlter(array &$plugins) : void {
    if (isset($plugins['views_query'])) {
      $plugins['views_query']['class'] = 'Drupal\mongodb\Plugin\views\query\ViewsQuery';
      $plugins['views_query']['provider'] = 'mongodb';
    }
  }

  /**
   * Implements hook_views_plugins_relationship_alter().
   */
  #[Hook('views_plugins_relationship_alter')]
  public function viewsPluginsRelationshipAlter(array &$plugins) : void {
    foreach ($plugins as $id => $plugin) {
      switch ($id) {
        case 'entity_reverse':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\relationship\EntityReverse';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'groupwise_max':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\relationship\GroupwiseMax';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'node_term_data':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\relationship\NodeTermData';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        case 'standard':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\relationship\Standard';
          $plugins[$id]['provider'] = 'mongodb';
          break;
      }
    }
  }

  /**
   * Implements hook_views_plugins_row_alter().
   */
  #[Hook('views_plugins_row_alter')]
  public function viewsPluginsRowAlter(array &$plugins) : void {
    foreach ($plugins as $id => $plugin) {
      switch ($id) {
        case 'entity:comment':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\row\EntityRow';
          $plugins[$id]['provider'] = 'mongodb';
          $plugins[$id]['base'] = ['comment'];
          break;

        case 'entity:node':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\row\NodeRow';
          $plugins[$id]['provider'] = 'mongodb';
          $plugins[$id]['base'] = ['node'];
          break;

        case 'entity:user':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\row\UserRow';
          $plugins[$id]['provider'] = 'mongodb';
          $plugins[$id]['base'] = ['users'];
          break;

        case 'comment_rss':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\row\CommentRss';
          $plugins[$id]['provider'] = 'mongodb';
          $plugins[$id]['base'] = ['comment'];
          break;

        case 'node_rss':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\row\NodeRss';
          $plugins[$id]['provider'] = 'mongodb';
          $plugins[$id]['base'] = ['node'];
          break;

      }
    }
  }

  /**
   * Implements hook_views_plugins_sort_alter().
   */
  #[Hook('views_plugins_sort_alter')]
  public function viewsPluginsSortAlter(array &$plugins) : void {
    foreach ($plugins as $id => $plugin) {
      switch ($id) {
        case 'date':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\sort\Date';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module datetime.
        case 'datetime':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\sort\DatetimeDate';
          $plugins[$id]['provider'] = 'mongodb';
          break;

        // Overrides for the module content_moderation.
        case 'moderation_state_sort':
          $plugins[$id]['class'] = 'Drupal\mongodb\Plugin\views\sort\ModerationStateSort';
          $plugins[$id]['provider'] = 'mongodb';
          break;

      }
    }
  }

}
