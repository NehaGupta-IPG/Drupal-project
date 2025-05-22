<?php

namespace Drupal\Core\Template;

use Twig\Compiler;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\TwigFunction;

/**
 * Extend FunctionExpression to add extra arguments.
 *
 * @see \Drupal\Core\Template\TwigNodeVisitor
 */
class TwigFunctionExpression extends FunctionExpression {

  /**
   * @var array
   *   Contains additional data that could be useful inside twig.engine.
   */
  protected $extraArguments;

  /**
   * TwigFunctionExpression constructor.
   *
   * @param \Twig\TwigFunction $function
   *   The Twig function.
   * @param \Twig\Node\Node $arguments
   *   The function arguments.
   * @param int $lineno
   *   The line number.
   * @param array $extra_arguments
   *   (Optional) Extra arguments to pass to the function.
   */
  public function __construct(TwigFunction $function, Node $arguments, int $lineno, array $extra_arguments = []) {
    parent::__construct($function->getName(), $arguments, $lineno);
    $this->extraArguments = $extra_arguments;
  }

  /**
   * Add extra arguments before compiling the function.
   *
   * Override ::compileCallable() in order to run after ::compile() has set
   * the attributes, but before the callable is compiled.
   *
   * @param \Twig\Compiler $compiler
   */
  protected function compileCallable(Compiler $compiler): void {
    // Merge in the extra arguments.
    if ($this->extraArguments) {
      $this->setAttribute('arguments', $this->getAttribute('arguments') + $this->extraArguments);
    }
    parent::compileCallable($compiler);
  }

}
