<?php

namespace Drupal\mongodb\Menu;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorage as CoreMenuTreeStorage;

// cspell:ignore mlid

/**
 * The MongoDB implementation of \Drupal\Core\Menu\MenuTreeStorage.
 */
class MenuTreeStorage extends CoreMenuTreeStorage {

  /**
   * List of integer fields.
   *
   * @var array
   */
  protected $integerFields;

  /**
   * List of boolean fields.
   *
   * @var array
   */
  protected $booleanFields;

  /**
   * {@inheritdoc}
   */
  protected $tableExists = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function safeExecuteSelect(SelectInterface $query) {
    if (!$this->tableExists) {
      $this->tableExists = $this->ensureTableExists();
    }

    return parent::safeExecuteSelect($query);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave(array $link) {
    $affected_menus = [];

    // Get the existing definition if it exists. This does not use
    // self::loadFull() to avoid the unserialization of fields with 'serialize'
    // equal to TRUE as defined in self::schemaDefinition(). The makes $original
    // easier to compare with the return value of self::preSave().
    $query = $this->connection->select($this->table, $this->options);
    $query->fields($this->table);
    $query->condition('id', $link['id']);
    $original = $this->safeExecuteSelect($query)->fetchAssoc();

    if ($original) {
      $link['mlid'] = $mlid = $original['mlid'];
      $link['has_children'] = $original['has_children'];
      $affected_menus[$original['menu_name']] = $original['menu_name'];
      $fields = $this->preSave($link, $original);
      // If $link matches the $original data then exit early as there are no
      // changes to make. Use array_diff_assoc() to check if they match because:
      // - Some of the data types of the values are not the same. The values
      //   in $original are all strings because they have come from database but
      //   $fields contains typed values.
      // - MenuTreeStorage::preSave() removes the 'mlid' from $fields.
      // - The order of the keys in $original and $fields is different.
      if (array_diff_assoc($fields, $original) == [] && array_diff_assoc($original, $fields) == ['mlid' => $link['mlid']]) {
        return $affected_menus;
      }

      // MongoDB needs integer values to be real integers.
      foreach ($this->integerFields() as $integer_field) {
        $fields[$integer_field] = (int) $fields[$integer_field];
      }

      $query = $this->connection->update($this->table, $this->options);
      $query->condition('mlid', (int) $mlid);
      $query->fields($fields)
        ->execute();

      // Need to check both parent and menu_name, since parent can be empty in
      // any menu.
      if ($link['parent'] != $original['parent'] || $link['menu_name'] != $original['menu_name']) {
        $this->moveChildren($fields, $original);
      }

      $this->updateParentalStatus($fields);
      $this->updateParentalStatus($original);
    }
    else {
      // Generate a new mlid.
      $sequences = \Drupal::service('mongodb.sequences');
      $link['mlid'] = $mlid = $sequences->nextId($this->table);
      $fields = $this->preSave($link, []);

      // MenuTreeStorage::preSave() removed the mlid value for use in an update
      // query, but we need doing an insert query.
      $fields['mlid'] = $mlid;

      // All the serializable fields are also not null fields.
      if (!isset($fields['title'])) {
        $fields['title'] = serialize('');
      }
      if (!isset($fields['description'])) {
        $fields['description'] = serialize('');
      }
      if (!isset($fields['route_parameters'])) {
        $fields['route_parameters'] = serialize([]);
      }
      if (!isset($fields['options'])) {
        $fields['options'] = serialize([]);
      }
      if (!isset($fields['metadata'])) {
        $fields['metadata'] = serialize([]);
      }

      // We may be moving the link to a new menu.
      $affected_menus[$fields['menu_name']] = $fields['menu_name'];

      $insert_id = (int) $this->connection->insert($this->table, $this->options)
        ->fields($fields)
        ->execute();

      if ($mlid != $insert_id) {
        // Throw exception.
      }

      $this->updateParentalStatus($link);
    }

    return $affected_menus;
  }

  /**
   * {@inheritdoc}
   */
  protected function doFindChildrenRelativeDepth(array $original) {
    // Fot MongoDB integer values must be real integers.
    foreach ($this->integerFields() as $integer_field) {
      $original[$integer_field] = (int) $original[$integer_field];
    }

    return parent::doFindChildrenRelativeDepth($original);
  }

  /**
   * {@inheritdoc}
   */
  protected function moveChildren($fields, $original) {
    $query = $this->connection->select($this->table, $this->options);
    $query->fields($this->table, ['mlid', 'id']);
    $query->condition('menu_name', $original['menu_name']);
    $query->condition('parent', $original['id']);
    $children = $this->safeExecuteSelect($query)->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($children as $child) {
      // Get the mlid and id values from the child and remove them for the
      // update query.
      $mlid = (int) $child['mlid'];
      unset($child['mlid']);
      $id = $child['id'];
      unset($child['id']);

      $parent = 0;
      for ($i = 1; $i <= $this->maxDepth(); $i++) {
        if ($fields["p$i"] == $original['mlid']) {
          $parent = $i;
        }

        if (($parent < 1) || ($parent == $i)) {
          $child["p$i"] = (int) $fields["p$i"];
        }
        elseif (($parent + 1) == $i) {
          $child["p$i"] = $mlid;
          $fields["p$i"] = $mlid;
        }
        else {
          $child["p$i"] = 0;
        }
      }
      $child['depth'] = $parent + 1;
      $child['menu_name'] = $fields['menu_name'];

      $query = $this->connection->update($this->table, $this->options);
      $query->fields($child);
      $query->condition('mlid', $mlid);
      $query->execute();

      // Add the mlid and id values back for the recursive call.
      $child['mlid'] = $mlid;
      $child['id'] = $id;

      // Recursively call this method for each child.
      $this->moveChildren($fields, $child);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function updateParentalStatus(array $link) {
    // If parent is empty, there is nothing to update.
    if (!empty($link['parent'])) {
      // Check if at least one visible child exists in the table.
      $query = $this->connection->select($this->table, 'm', $this->options);
      $query->fields('m', ['_id']);
      $query->range(0, 1);
      $query
        ->condition('menu_name', $link['menu_name'])
        ->condition('parent', $link['parent'])
        ->condition('enabled', TRUE);
      $result = $query->execute()->fetchField();

      $this->connection->update($this->table, $this->options)
        ->fields(['has_children' => (bool) $result])
        ->condition('id', $link['parent'])
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRootPathIds($id) {
    $subquery = $this->connection->select($this->table, $this->options);
    // @todo Consider making this dynamic based on static::MAX_DEPTH or from the
    //   schema if that is generated using static::MAX_DEPTH.
    //   https://www.drupal.org/node/2302043
    $subquery->fields($this->table, ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'p9']);
    $subquery->condition('id', $id);
    $result = current($subquery->execute()->fetchAll(\PDO::FETCH_ASSOC));
    $ids = array_filter($result);
    if ($ids) {
      // MongoDB needs integer values to be real integer.
      foreach ($ids as &$id) {
        $id = (int) $id;
      }
      $query = $this->connection->select($this->table, $this->options);
      $query->fields($this->table, ['id']);
      $query->orderBy('depth', 'DESC');
      $query->condition('mlid', $ids, 'IN');

      // @todo Cache this result in memory if we find it is being used more
      //   than once per page load. https://www.drupal.org/node/2302185
      return $this->safeExecuteSelect($query)->fetchAllKeyed(0, 0);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function loadLinks($menu_name, MenuTreeParameters $parameters) {
    $query = $this->connection->select($this->table, $this->options);
    $query->fields($this->table);

    // Allow a custom root to be specified for loading a menu link tree. If
    // omitted, the default root (i.e. the actual root, '') is used.
    if ($parameters->root !== '') {
      $root = $this->loadFull($parameters->root);

      // If the custom root does not exist, we cannot load the links below it.
      if (!$root) {
        return [];
      }

      // When specifying a custom root, we only want to find links whose
      // parent IDs match that of the root; that's how we ignore the rest of the
      // tree. In other words: we exclude everything unreachable from the
      // custom root.
      for ($i = 1; $i <= $root['depth']; $i++) {
        $query->condition("p$i", (int) $root["p$i"]);
      }

      // When specifying a custom root, the menu is determined by that root.
      $menu_name = $root['menu_name'];

      // If the custom root exists, then we must rewrite some of our
      // parameters; parameters are relative to the root (default or custom),
      // but the queries require absolute numbers, so adjust correspondingly.
      if (isset($parameters->minDepth)) {
        $parameters->minDepth += $root['depth'];
      }
      else {
        $parameters->minDepth = $root['depth'];
      }
      if (isset($parameters->maxDepth)) {
        $parameters->maxDepth += $root['depth'];
      }
    }

    // If no minimum depth is specified, then set the actual minimum depth,
    // depending on the root.
    if (!isset($parameters->minDepth)) {
      if ($parameters->root !== '' && $root) {
        $parameters->minDepth = $root['depth'];
      }
      else {
        $parameters->minDepth = 1;
      }
    }

    for ($i = 1; $i <= $this->maxDepth(); $i++) {
      $query->orderBy('p' . $i, 'ASC');
    }

    $query->condition('menu_name', $menu_name);

    if (!empty($parameters->expandedParents)) {
      $query->condition('parent', $parameters->expandedParents, 'IN');
    }
    if (isset($parameters->minDepth) && $parameters->minDepth > 1) {
      $query->condition('depth', (int) $parameters->minDepth, '>=');
    }
    if (isset($parameters->maxDepth)) {
      $query->condition('depth', (int) $parameters->maxDepth, '<=');
    }

    // Add custom query conditions, if any were passed.
    if (!empty($parameters->conditions)) {
      // Only allow conditions that are testing definition fields.
      $parameters->conditions = array_intersect_key($parameters->conditions, array_flip($this->definitionFields()));
      $serialized_fields = $this->serializedFields();
      foreach ($parameters->conditions as $column => $value) {
        if (is_array($value)) {
          $operator = $value[1];
          $value = $value[0];
        }
        else {
          $operator = '=';
        }
        if (in_array($column, $serialized_fields)) {
          $value = serialize($value);
        }
        // For MongoDB integer values must be real integers.
        if (in_array($column, $this->integerFields())) {
          $query->condition($column, (int) $value, $operator);
        }
        // For MongoDB boolean values must be real booleans.
        elseif (in_array($column, $this->booleanFields())) {
          $query->condition($column, (bool) $value, $operator);
        }
        else {
          $query->condition($column, $value, $operator);
        }
      }
    }

    return $this->safeExecuteSelect($query)->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  /**
   * Determines integer fields in the storage.
   *
   * @return array
   *   A list of integer fields in the database.
   */
  protected function integerFields() {
    if (empty($this->integerFields)) {
      $schema = static::schemaDefinition();
      foreach ($schema['fields'] as $name => $field) {
        if (!empty($field['type']) && $field['type'] == 'int') {
          $this->integerFields[] = $name;
        }
      }
    }
    return $this->integerFields;
  }

  /**
   * Determines the boolean fields in the storage.
   *
   * @return array
   *   A list of boolean fields in the database.
   */
  protected function booleanFields() {
    if (empty($this->booleanFields)) {
      $schema = static::schemaDefinition();
      foreach ($schema['fields'] as $name => $field) {
        if (!empty($field['type']) && $field['type'] == 'bool') {
          $this->booleanFields[] = $name;
        }
      }
    }
    return $this->booleanFields;
  }

  /**
   * {@inheritdoc}
   */
  protected static function schemaDefinition() {
    $schema = parent::schemaDefinition();
    $schema['fields']['enabled'] = [
      'description' => 'A flag for whether the link should be rendered in menus. (FALSE = a disabled menu item that may be shown on admin screens, TRUE = a normal, visible link)',
      'type' => 'bool',
      'not null' => TRUE,
      'default' => TRUE,
    ];
    $schema['fields']['discovered'] = [
      'description' => 'A flag for whether the link was discovered, so can be purged on rebuild',
      'type' => 'bool',
      'not null' => TRUE,
      'default' => FALSE,
    ];
    $schema['fields']['expanded'] = [
      'description' => 'Flag for whether this link should be rendered as expanded in menus - expanded links always have their child links displayed, instead of only when the link is in the active trail (TRUE = expanded, FALSE = not expanded)',
      'type' => 'bool',
      'not null' => TRUE,
      'default' => FALSE,
    ];
    $schema['fields']['has_children'] = [
      'description' => 'Flag indicating whether any enabled links have this link as a parent (TRUE = enabled children exist, FALSE = no enabled children).',
      'type' => 'bool',
      'not null' => TRUE,
      'default' => FALSE,
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  protected function findNoLongerExistingLinks(array $definitions) {
    if ($definitions) {
      $query = $this->connection->select($this->table, NULL, $this->options);
      $query->addField($this->table, 'id');
      $query->condition('discovered', TRUE);
      $query->condition('id', array_keys($definitions), 'NOT IN');
      // Starting from links with the greatest depth will minimize the amount
      // of re-parenting done by the menu storage.
      $query->orderBy('depth', 'DESC');
      $result = $query->execute()->fetchCol();
    }
    else {
      $result = [];
    }
    return $result;
  }

}
