<?php

namespace Drupal\Core\Environment;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Default implementation of environment manager.
 */
class EnvironmentManager implements EnvironmentManagerInterface {

  /**
   * Constructs a new default environment manager.
   *
   * @param Iterable<\Drupal\Core\Environment\SubstitutionInterface> $substitutions
   *   List of all available environment substitutions.
   */
  public function __construct(
    #[AutowireIterator(tag: 'environment_substitution')]
    protected iterable $substitutions,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function executeInEnvironments(array $params, callable $callback): mixed {
    if (count($params) === 0) {
      return $callback();
    }
    else {
      $param = array_shift($params);
      assert($param instanceof EnvironmentParameter);
      foreach ($this->substitutions as $substitution) {
        assert($substitution instanceof SubstitutionInterface);
        if ($substitution->applies($param)) {
          return $substitution->executeInEnvironment(
            $param,
            fn () => $this->executeInEnvironments($params, $callback),
          );
        }
      }

      if ($param->optional) {
        return $this->executeInEnvironments($params, $callback);
      }
      else {
        throw new \InvalidArgumentException(sprintf('Failed to find environment substitution implementation for param %s', $param->name));
      }
    }
  }

}
