<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\query;

use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Adapts legacy duck-typed query plugins to SqlQueryInterface.
 *
 * @internal
 */
final class LegacySqlQueryAdapter extends LegacyHandlerQueryAdapter implements SqlQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function setCountField($table, $field, $alias = NULL) {
    return $this->invokeSql('setCountField', func_get_args());
  }

  /**
   * {@inheritdoc}
   */
  public function clearFields() {
    return $this->invokeSql('clearFields', []);
  }

  /**
   * {@inheritdoc}
   */
  public function addGroupBy($clause) {
    return $this->invokeSql('addGroupBy', [$clause]);
  }

  /**
   * {@inheritdoc}
   */
  public function addWhereExpression($group, $snippet, $args = []) {
    return $this->invokeSql('addWhereExpression', [$group, $snippet, $args]);
  }

  /**
   * {@inheritdoc}
   */
  public function addHavingExpression($group, $snippet, $args = []) {
    return $this->invokeSql('addHavingExpression', [$group, $snippet, $args]);
  }

  /**
   * {@inheritdoc}
   */
  public function ensureTable($table, $relationship = NULL, ?JoinPluginBase $join = NULL) {
    return $this->invokeSql('ensureTable', [$table, $relationship, $join]);
  }

  /**
   * {@inheritdoc}
   */
  public function addTable($table, $relationship = NULL, ?JoinPluginBase $join = NULL, $alias = NULL) {
    return $this->invokeSql('addTable', [$table, $relationship, $join, $alias]);
  }

  /**
   * {@inheritdoc}
   */
  public function addRelationship($alias, JoinPluginBase $join, $base, $link_point = NULL) {
    return $this->invokeSql('addRelationship', [$alias, $join, $base, $link_point]);
  }

  /**
   * {@inheritdoc}
   */
  public function placeholder($base = 'views') {
    return $this->invokeSql('placeholder', [$base]);
  }

  /**
   * {@inheritdoc}
   */
  public function addTag($tag) {
    return $this->invokeSql('addTag', [$tag]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConnection() {
    return $this->invokeSql('getConnection', []);
  }

  /**
   * Forwards one legacy SQL-query call.
   */
  private function invokeSql(string $method, array $arguments): mixed {
    if (!method_exists($this->query, $method)) {
      throw new \LogicException(sprintf(
        'Views query plugin %s does not support SQL operation %s().',
        $this->query::class,
        $method,
      ));
    }

    @trigger_error(sprintf('Duck-typed support for SQL operation %s() on Views query plugins is deprecated in drupal:12.0.0 and is removed from drupal:14.0.0. Implement %s when the query supports SQL semantics. See https://www.drupal.org/node/2484565', $method, SqlQueryInterface::class), E_USER_DEPRECATED);
    return call_user_func_array([$this->query, $method], $arguments);
  }

}
