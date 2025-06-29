<?php

namespace Drupal\file\Plugin\migrate\source\d6;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 6 upload source from database.
 *
 * For available configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d6_upload",
 *   source_module = "upload"
 * )
 */
class Upload extends DrupalSqlBase {

  /**
   * The join options between the node and the upload table.
   */
  const JOIN = [
    [
      'field' => 'n.nid',
      'field2' => 'u.nid',
      'operator' => '=',
    ],
    [
      'field' => 'n.vid',
      'field2' => 'u.vid',
      'operator' => '=',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('upload', 'u')
      ->distinct()
      ->fields('u', ['nid', 'vid']);

    if (is_string(static::JOIN)) {
      $query->innerJoin('node', 'n', static::JOIN);
    }
    else {
      $condition = $query->joinCondition();
      foreach (static::JOIN as $join) {
        if (isset($join['field2'])) {
          $condition->compare($join['field'], $join['field2'], $join['operator']);
        }
        else {
          $condition->condition($join['field'], $join['value'], $join['operator']);
        }
      }
      $query->innerJoin('node', 'n', $condition);
    }
    $query->addField('n', 'type');
    $query->addField('n', 'language');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $query = $this->select('upload', 'u')
      ->fields('u', ['fid', 'description', 'list'])
      ->condition('u.nid', $row->getSourceProperty('nid'))
      ->orderBy('u.weight');

    if (is_string(static::JOIN)) {
      $query->innerJoin('node', 'n', static::JOIN);
    }
    else {
      $condition = $query->joinCondition();
      foreach (static::JOIN as $join) {
        if (isset($join['field2'])) {
          $condition->compare($join['field'], $join['field2'], $join['operator']);
        }
        else {
          $condition->condition($join['field'], $join['value'], $join['operator']);
        }
      }
      $query->innerJoin('node', 'n', $condition);
    }
    $row->setSourceProperty('upload', $query->execute()->fetchAll());
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'fid' => $this->t('The file Id.'),
      'nid' => $this->t('The node Id.'),
      'vid' => $this->t('The version Id.'),
      'type' => $this->t('The node type'),
      'language' => $this->t('The node language.'),
      'description' => $this->t('The file description.'),
      'list' => $this->t('Whether the list should be visible on the node page.'),
      'weight' => $this->t('The file weight.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['vid']['type'] = 'integer';
    $ids['vid']['alias'] = 'u';
    return $ids;
  }

}
