<?php

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use Symfony\Component\DependencyInjection\Container as SymfonyContainer;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Drupal's dependency injection container builder.
 *
 * @todo Submit upstream patches to Symfony to not require these overrides.
 *
 * @ingroup container
 */
class ContainerBuilder extends SymfonyContainerBuilder implements ContainerInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(?ParameterBagInterface $parameterBag = NULL) {
    parent::__construct($parameterBag);
    $this->setResourceTracking(FALSE);
  }

  /**
   * Overrides Symfony\Component\DependencyInjection\ContainerBuilder::set().
   *
   * Drupal's container builder can be used at runtime after compilation, so we
   * override Symfony's ContainerBuilder's restriction on setting services in a
   * frozen builder.
   *
   * @todo Restrict this to synthetic services only. Ideally, the upstream
   *   ContainerBuilder class should be fixed to allow setting synthetic
   *   services in a frozen builder.
   */
  public function set(string $id, ?object $service): void {
    SymfonyContainer::set($id, $service);
  }

  /**
   * {@inheritdoc}
   */
  public function register($id, $class = NULL): Definition {
    $definition = new Definition($class);
    // As of Symfony 5.2 all services are private by default, but in Drupal
    // services are still public by default.
    $definition->setPublic(TRUE);
    return $this->setDefinition($id, $definition);
  }

  /**
   * {@inheritdoc}
   */
  public function setAlias($alias, $id): Alias {
    $alias = parent::setAlias($alias, $id);
    // As of Symfony 3.4 all aliases are private by default.
    $alias->setPublic(TRUE);
    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value): void {
    if (strtolower($name) !== $name && strpos($name, 'env(') !== 0) {
      throw new \InvalidArgumentException("Parameter names must be lowercase: $name");
    }

    parent::setParameter($name, $value);
  }

  /**
   * Replaces the parameter bag, e.g. to swap in resolved env values.
   *
   * This is needed because compile() replaces the parameter bag with a
   * FrozenParameterBag, preventing further modifications.
   */
  public function drupalResolveEnvParameters(): void {
    // When not dumping (e.g. KernelTestBase), resolve env placeholders
    // eagerly in the ContainerBuilder's parameter bag since it won't go
    // through our custom Container which handles runtime resolution.
    //
    // First, convert internal env placeholders back to %env(FOO)%
    // format in all parameter values, then replace the parameter bag
    // so that Symfony's EnvVarProcessor (used by the resolve: prefix)
    // sees %env(FOO)% patterns instead of internal placeholder strings.
    $params = $this->parameterBag->all();
    $withEnvFormat = $this->resolveEnvPlaceholders($params);
    if ($withEnvFormat !== $params) {
      $this->parameterBag = (new ParameterBag($withEnvFormat));
      // Now resolve the %env(FOO)% strings to actual env var values.
      // resolveEnvPlaceholders(value, TRUE) calls getEnv() which uses
      // EnvVarProcessor to handle prefixes (int:, bool:, resolve:, etc).
      foreach ($withEnvFormat as $key => $value) {
        if (is_string($value) && str_contains($value, '%env(')) {
          $withEnvFormat[$key] = $this->resolveEnvPlaceholders($params[$key], TRUE);
        }
      }
      // @todo We need to freeze the parameter bag again.
      $this->parameterBag = (new ParameterBag($withEnvFormat));

      // Also resolve env placeholders in service definition arguments,
      // since ResolveParameterPlaceHoldersPass already replaced %param%
      // references with internal env placeholder strings during compile.
      foreach ($this->getDefinitions() as $definition) {
        $args = $definition->getArguments();
        $changed = FALSE;
        foreach ($args as $key => $arg) {
          if (is_string($arg) && stripos($arg, 'env_') !== FALSE) {
            $resolved = $this->resolveEnvPlaceholders($arg, TRUE);
            if ($resolved !== $arg) {
              $args[$key] = $resolved;
              $changed = TRUE;
            }
          }
        }
        if ($changed) {
          $definition->setArguments($args);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    assert(FALSE, 'The container was serialized.');
    return array_keys(get_object_vars($this));
  }

}
