<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\comment\Plugin\views\argument\UserUid;
use Drupal\views\Views;

/**
 * Overriding the views argument plugin "argument_comment_user_uid".
 */
class CommentUserUid extends UserUid {

  /**
   * {@inheritdoc}
   */
  public function title() {
    if (!$this->argument) {
      $title = \Drupal::config('user.settings')->get('anonymous');
    }
    else {
      $user_translations = $this->database->select('users', 'u')
        ->fields('u', ['user_translations'])
        ->condition('uid', (int) $this->argument)
        ->execute()
        ->fetchField();
      $title = '';
      if (!empty($user_translations) && is_array($user_translations)) {
        foreach ($user_translations as $user_translation) {
          if (isset($user_translation['default_langcode']) && $user_translation['default_langcode'] && isset($user_translation['name'])) {
            $title = $user_translation['name'];
          }
        }
      }
    }
    if (empty($title)) {
      return $this->t('No user');
    }

    return $title;
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    if ($this->table == $this->view->storage->get('base_table')) {
      $field = $this->realField;
    }
    else {
      $field = "$this->tableAlias.$this->realField";
    }
    $this->argument = (int) $this->argument;

    if ($this->table != 'comment') {
      $entity_id = $this->definition['entity_id'];
      $entity_type = $this->definition['entity_type'];

      $def = [];
      $def['table'] = 'comment';
      $def['field'] = 'comment_translations.entity_id';
      $def['left_table'] = $this->tableAlias;
      $def['left_field'] = $entity_id;
      $def['extra'] = [
        [
          'field' => 'comment_translations.entity_type',
          'value' => $entity_type,
        ],
      ];

      $join = Views::pluginManager('join')->createInstance('standard', $def);

      $alias = $this->query->addRelationship('comment', $join, $this->tableAlias, $this->relationship);

      $condition = ($this->view->query->getConnection()->condition('OR'))
        ->condition($field, $this->argument)
        ->condition("$alias.comment_translations.uid", $this->argument);

      $this->query->addCondition(0, $condition);
    }
    else {
      $this->query->addCondition(0, $field, $this->argument);
    }
  }

}
