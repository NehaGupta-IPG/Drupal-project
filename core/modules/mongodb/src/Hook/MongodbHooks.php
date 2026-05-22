<?php

namespace Drupal\mongodb\Hook;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for MongoDB.
 */
class MongodbHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.mongodb':
        $output = '';
        $output .= '<h2>' . t('About') . '</h2>';
        $output .= '<p>' . t('The MongoDB module provides the connection between Drupal and a MongoDB database. For more information, see the <a href=":mongodb">online documentation for the MongoDB module</a>.', [
          ':mongodb' => 'https://git.drupalcode.org/project/mongodb/-/tree/3.x?ref_type=heads',
        ]) . '</p>';
        return $output;
    }
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) : void {
    $connection = NULL;
    if (Database::getConnectionInfo()) {
      $connection = Database::getConnection();
    }

    if ($connection && $connection->driver() == 'mongodb') {
      if (!empty($entity_types['content_moderation_state'])) {
        $entity_types['content_moderation_state']->setHandlerClass('storage_schema', 'Drupal\mongodb\modules\content_moderation\ContentModerationStateStorageSchema');
      }

      if (!empty($entity_types['node'])) {
        $entity_types['node']->setStorageClass('Drupal\mongodb\modules\node\NodeStorage');
      }

      if (!empty($entity_types['paragraph'])) {
        $entity_types['paragraph']->setHandlerClass('storage_schema', 'Drupal\mongodb\modules\paragraphs\ParagraphStorageSchema');
      }

      if (!empty($entity_types['user'])) {
        $entity_types['user']->setStorageClass('Drupal\mongodb\modules\user\UserStorage');
      }

      if (!empty($entity_types['view'])) {
        $entity_types['view']->setStorageClass('Drupal\mongodb\modules\views\ViewStorage');
        $entity_types['view']->setClass('Drupal\mongodb\modules\views\View');
      }
    }
  }

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(&$info): void {
    if (isset($info['boolean']['class'])) {
      $info['boolean']['class'] = 'Drupal\mongodb\Plugin\Field\FieldType\BooleanItem';
      $info['boolean']['provider'] = 'mongodb';
    }
    if (isset($info['changed']['class'])) {
      $info['changed']['class'] = 'Drupal\mongodb\Plugin\Field\FieldType\ChangedItem';
      $info['changed']['provider'] = 'mongodb';
    }
    if (isset($info['created']['class'])) {
      $info['created']['class'] = 'Drupal\mongodb\Plugin\Field\FieldType\CreatedItem';
      $info['created']['provider'] = 'mongodb';
    }
    if (isset($info['timestamp']['class'])) {
      $info['timestamp']['class'] = 'Drupal\mongodb\Plugin\Field\FieldType\TimestampItem';
      $info['timestamp']['provider'] = 'mongodb';
    }
  }

  /**
   * Implements hook_search_plugin_alter().
   */
  #[Hook('search_plugin_alter')]
  public function searchPluginAlter(array &$plugins): void {
    if (isset($plugins['help_search'])) {
      $plugins['help_search']['class'] = 'Drupal\mongodb\Plugin\Search\HelpSearch';
      $plugins['help_search']['provider'] = 'mongodb';
    }
    if (isset($plugins['node_search'])) {
      $plugins['node_search']['class'] = 'Drupal\mongodb\Plugin\Search\NodeSearch';
      $plugins['node_search']['provider'] = 'mongodb';
    }
    if (isset($plugins['user_search'])) {
      $plugins['user_search']['class'] = 'Drupal\mongodb\Plugin\Search\UserSearch';
      $plugins['user_search']['provider'] = 'mongodb';
    }
  }

  /**
   * Implements hook_query_TAG_alter().
   */
  #[Hook('query_entity_reference_alter')]
  public function queryEntityReferenceAlter(AlterableInterface $query): void {
    if (\Drupal::moduleHandler()->moduleExists('block_content')) {
      if ($query instanceof SelectInterface && $query->getMetaData('entity_type') === 'block_content' && $query->hasTag('block_content_access')) {
        if (!$this->mongodb_block_content_has_reusable_condition($query->conditions(), $query->getTables())) {
          $query->condition('block_content_current_revision.reusable', TRUE);
        }
      }
    }
  }

  /**
   * Utility function is the MongoDB version of _block_content_has_reusable_condition.
   */
  protected function mongodb_block_content_has_reusable_condition(array $condition, array $tables) {
    // If this is a condition group call this function recursively for each nested
    // condition until a condition is found that return TRUE.
    if (isset($condition['#conjunction'])) {
      foreach (array_filter($condition, 'is_array') as $nested_condition) {
        if ($this->mongodb_block_content_has_reusable_condition($nested_condition, $tables)) {
          return TRUE;
        }
      }
      return FALSE;
    }
    if (isset($condition['field'])) {
      $field = $condition['field'];
      if (is_object($field) && $field instanceof ConditionInterface) {
        return $this->mongodb_block_content_has_reusable_condition($field->conditions(), $tables);
      }
      $base_table = \Drupal::entityTypeManager()->getDefinition('block_content')->getBaseTable();
      foreach ($tables as $table) {
        if ($table['table'] === $base_table && $field === 'block_content_current_revision.reusable') {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
