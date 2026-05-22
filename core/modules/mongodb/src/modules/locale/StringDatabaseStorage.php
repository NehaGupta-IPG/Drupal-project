<?php

namespace Drupal\mongodb\modules\locale;

use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\locale\SourceString;
use Drupal\locale\StringDatabaseStorage as CoreStringDatabaseStorage;
use Drupal\locale\TranslationString;

/**
 * The MongoDB implementation of \Drupal\locale\StringDatabaseStorage.
 */
class StringDatabaseStorage extends CoreStringDatabaseStorage {

  /**
   * {@inheritdoc}
   */
  public function getStrings(array $conditions = [], array $options = []) {
    return $this->dbStringLoad($conditions, $options, 'Drupal\locale\SourceString');
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslations(array $conditions = [], array $options = []) {
    return $this->dbStringLoad($conditions, ['translation' => TRUE] + $options, 'Drupal\locale\TranslationString');
  }

  /**
   * {@inheritdoc}
   */
  public function findString(array $conditions) {
    $values = $this->dbStringSelect($conditions)
      ->execute()
      ->fetchAssoc();

    if (!empty($values)) {
      $string = new SourceString($values);
      $string->setStorage($this);
      return $string;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function findTranslation(array $conditions) {
    $values = $this->dbStringSelect($conditions, ['translation' => TRUE])
      ->execute()
      ->fetchAssoc();

    if (!empty($values)) {
      if (!empty($values['t_language'])) {
        $values['language'] = (is_array($values['t_language']) ? reset($values['t_language']) : $values['t_language']);
      }
      unset($values['t_language']);
      if (!empty($values['t_translation'])) {
        $values['translation'] = (is_array($values['t_translation']) ? reset($values['t_translation']) : $values['t_translation']);
      }
      unset($values['t_translation']);
      if (!empty($values['t_customized'])) {
        $values['customized'] = (is_array($values['t_customized']) ? reset($values['t_customized']) : $values['t_customized']);
      }
      unset($values['t_customized']);

      $string = new TranslationString($values);
      $this->checkVersion($string, \Drupal::VERSION);
      $string->setStorage($this);
      return $string;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLocations(array $conditions = []) {
    $query = $this->connection->select('locales_location', 'l', $this->options)
      ->fields('l');
    foreach ($conditions as $field => $values) {
      if (is_array($values)) {
        foreach ($values as &$value) {
          if (in_array($field, ['lid', 'sid'], TRUE)) {
            $value = (int) $value;
          }
        }
      }
      elseif (in_array($field, ['lid', 'sid'], TRUE)) {
        $values = (int) $values;
      }
      // Cast scalars to array so we can consistently use an IN condition.
      $query->condition($field, (array) $values, 'IN');
    }
    return $query->execute()->fetchAll();
  }

  /**
   * {@inheritdoc}
   */
  public function countStrings() {
    $result = $this->connection->select('locales_source', 'l')
      ->fields('l', ['lid'])
      ->execute()
      ->fetchAll();
    return count($result);
  }

  /**
   * {@inheritdoc}
   */
  public function countTranslations() {
    $query = $this->connection->select('locales_source', 's');
    $query->addJoin('INNER', 'locales_target', 't', $query->joinCondition()->compare('s.lid', 't.lid'));
    $result = $query->execute()->fetchAll();

    $translations = [];
    foreach ($result as $source) {
      if (isset($source->t['language'])) {
        $language = $source->t['language'];
        if (isset($translations[$language])) {
          $translations[$language]++;
        }
        else {
          $translations[$language] = 1;
        }
      }
    }

    return $translations;
  }

  /**
   * {@inheritdoc}
   */
  protected function dbStringKeys($string) {
    $values = parent::dbStringKeys($string);

    // For MongoDB.
    if (isset($values['lid'])) {
      $values['lid'] = (int) $values['lid'];
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  protected function dbStringLoad(array $conditions, array $options, $class) {
    $strings = [];
    $result = $this->dbStringSelect($conditions, $options)->execute();
    foreach ($result as $item) {
      // For MongoDB variables from joined tables are returned in an array,
      // because joined table can return multiple rows.
      if (isset($item->t_language)) {
        $item->language = (is_array($item->t_language) ? reset($item->t_language) : $item->t_language);
      }
      unset($item->t_language);
      if (isset($item->t_translation)) {
        $item->translation = (is_array($item->t_translation) ? reset($item->t_translation) : $item->t_translation);
      }
      unset($item->t_translation);
      if (isset($item->t_customized)) {
        $item->customized = (is_array($item->t_customized) ? reset($item->t_customized) : $item->t_customized);
      }
      unset($item->t_customized);
      if (isset($item->l_sid)) {
        $item->sid = (is_array($item->l_sid) ? reset($item->l_sid) : $item->l_sid);
      }
      unset($item->l_sid);

      /** @var \Drupal\locale\StringInterface $string */
      $string = new $class($item);
      $string->setStorage($this);
      $strings[] = $string;
    }
    return $strings;
  }

  /**
   * {@inheritdoc}
   */
  protected function dbStringSelect(array $conditions, array $options = []) {
    // Start building the query with source table and check whether we need to
    // join the target table too.
    $query = $this->connection->select('locales_source', 's', $this->options)
      ->fields('s');

    // Figure out how to join and translate some options into conditions.
    if (isset($conditions['translated'])) {
      // This is a meta-condition we need to translate into simple ones.
      if ($conditions['translated']) {
        // Select only translated strings.
        $join = 'innerJoin';
      }
      else {
        // Select only untranslated strings.
        $join = 'leftJoin';
        $conditions['translation'] = NULL;
      }
      unset($conditions['translated']);
    }
    else {
      $join = !empty($options['translation']) ? 'leftJoin' : FALSE;
    }

    if ($join) {
      if (isset($conditions['language'])) {
        // If we've got a language condition, we use it for the join.
        $query->$join('locales_target', 't',
          $query->joinCondition()
            ->compare('t.lid', 's.lid')
            ->condition('t.language', $conditions['language'])
        );
        unset($conditions['language']);
      }
      else {
        // Since we don't have a language, join with locale id only.
        $query->$join('locales_target', 't', $query->joinCondition()->compare('t.lid', 's.lid'));
      }
      if (!empty($options['translation'])) {
        // We cannot just add all fields because 'lid' may get null values.
        $query->fields('t', ['language', 'translation', 'customized']);
        $query->addFilterUnwindPath('t');
      }
    }

    // If we have conditions for location's type or name, then we need the
    // location table, for which we add a subquery. We cast any scalar value to
    // array so we can consistently use IN conditions.
    if (isset($conditions['type']) || isset($conditions['name'])) {
      $subquery = $this->connection->select('locales_location', 'l', $this->options)
        ->fields('l', ['sid']);

      foreach (['type', 'name'] as $field) {
        if (isset($conditions[$field])) {
          if (is_array($conditions[$field])) {
            $subquery->condition($field, (array) $conditions[$field], 'IN');
          }
          else {
            $subquery->condition($field, $conditions[$field]);
          }

          unset($conditions[$field]);
        }
      }
      $location_sids = $subquery->execute()->fetchCol();

      if (!empty($location_sids)) {
        foreach ($location_sids as &$location_sid) {
          $location_sid = (int) $location_sid;
        }

        $query->condition('s.lid', $location_sids, 'IN');
      }
    }

    // Add conditions for both tables.
    foreach ($conditions as $field => $value) {
      $table_alias = $this->dbFieldTable($field);
      $field_alias = $table_alias . '.' . $field;
      if (is_null($value)) {
        $query->isNull($field_alias);
      }
      elseif ($table_alias == 't' && $join === 'leftJoin') {
        // Conditions for target fields when doing an outer join only make
        // sense if we add also OR field IS NULL.
        $query->condition(($this->connection->condition('OR'))
          ->condition($field_alias, (array) $value, 'IN')
          ->isNull($field_alias)
        );
      }
      else {
        if (empty($value)) {
          if (in_array($field, ['context', 'version', 'source', 'language', 'translation', 'name', 'type'])) {
            $query->condition($field_alias, '', '=');
          }
          elseif (in_array($field, ['customized'])) {
            $query->condition($field_alias, FALSE);
          }
          else {
            $query->condition($field_alias, 0, '=');
          }
        }
        else {
          if (in_array($field, ['context', 'version', 'source', 'language', 'translation', 'name', 'type'])) {
            $query->condition($field_alias, (array) $value, 'IN');
          }
          elseif (in_array($field, ['customized'])) {
            $query->condition($field_alias, (bool) $value);
          }
          else {
            $value = (array) $value;
            foreach ($value as &$val) {
              $val = (int) $val;
            }
            $query->condition($field_alias, $value, 'IN');
          }
        }
      }
    }

    // Process other options, string filter, query limit, etc.
    if (!empty($options['filters'])) {
      if (count($options['filters']) > 1) {
        $filter = $this->connection->condition('OR');
        $query->condition($filter);
      }
      else {
        // If we have a single filter, just add it to the query.
        $filter = $query;
      }
      foreach ($options['filters'] as $field => $string) {
        $filter->condition($this->dbFieldTable($field) . '.' . $field, '%' . $this->connection->escapeLike($string) . '%', 'LIKE');
      }
    }

    if (!empty($options['pager limit'])) {
      $query = $query->extend(PagerSelectExtender::class)->limit($options['pager limit']);
    }

    return $query;
  }

}
