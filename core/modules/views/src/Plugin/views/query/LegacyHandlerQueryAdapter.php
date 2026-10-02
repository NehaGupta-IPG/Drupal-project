<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\views\query;

/**
 * Adapts legacy duck-typed query plugins to HandlerQueryInterface.
 *
 * @internal
 */
class LegacyHandlerQueryAdapter implements HandlerQueryInterface {

  public function __construct(
    protected readonly QueryPluginBase $query,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addField($table, $field, $alias = '', array $params = []) {
    return $this->invoke('addField', [$table, $field, $alias, $params]);
  }

  /**
   * {@inheritdoc}
   */
  public function addWhere($group, $field, $value = NULL, $operator = NULL) {
    return $this->invoke('addWhere', [$group, $field, $value, $operator]);
  }

  /**
   * {@inheritdoc}
   */
  public function addOrderBy($table, $field = NULL, $order = 'ASC', $alias = '', array $params = []) {
    return $this->invoke('addOrderBy', [$table, $field, $order, $alias, $params]);
  }

  /**
   * Forwards one legacy handler-query call.
   */
  protected function invoke(string $method, array $arguments): mixed {
    if (!method_exists($this->query, $method)) {
      throw new \LogicException(sprintf(
        'Views query plugin %s does not support %s().',
        $this->query::class,
        $method,
      ));
    }

    @trigger_error(sprintf('Duck-typed support for %s() on Views query plugins is deprecated in drupal:12.0.0 and is removed from drupal:14.0.0. Implement %s instead. See https://www.drupal.org/node/2484565', $method, HandlerQueryInterface::class), E_USER_DEPRECATED);
    return call_user_func_array([$this->query, $method], $arguments);
  }

}
