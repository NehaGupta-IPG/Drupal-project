<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Parameter;

/**
 * Replaces resolved runtime parameter values with parameter references.
 *
 * Some parameters, such as 'app.root' and 'site.path', depend on the request
 * that builds the container rather than on the container definition. Compiling
 * the container resolves every reference to them, so their values end up in
 * the arguments of every service that uses them.
 *
 * This pass runs after the other passes and replaces each argument that equals
 * the value of a runtime parameter with a reference to that parameter. Dumped
 * containers then read the parameter at runtime instead of storing its value,
 * so the parameter value can be set when the container is loaded.
 *
 * Only exact matches are replaced. A string that contains a runtime parameter
 * value along with other text, such as a path inside the site directory, keeps
 * the value that was resolved when the container was compiled.
 *
 * @internal
 */
class RuntimeParametersPass extends AbstractRecursivePass {

  /**
   * The runtime parameter names, keyed by their resolved values.
   *
   * @var array<string, string>
   */
  protected array $parameterNames = [];

  /**
   * Constructs a RuntimeParametersPass.
   *
   * @param list<string> $parameters
   *   The names of the parameters to replace.
   */
  public function __construct(
    protected readonly array $parameters,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $this->parameterNames = [];
    foreach ($this->parameters as $name) {
      if (!$container->hasParameter($name)) {
        continue;
      }
      $value = $container->getParameter($name);
      // An empty string cannot be told apart from other empty arguments.
      if (is_string($value) && $value !== '') {
        $this->parameterNames[$value] = $name;
      }
    }

    if ($this->parameterNames) {
      parent::process($container);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processValue(mixed $value, bool $isRoot = FALSE): mixed {
    if (is_string($value) && isset($this->parameterNames[$value])) {
      return new Parameter($this->parameterNames[$value]);
    }
    return parent::processValue($value, $isRoot);
  }

}
