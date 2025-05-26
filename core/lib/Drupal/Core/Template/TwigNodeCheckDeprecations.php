<?php

namespace Drupal\Core\Template;

use Twig\Compiler;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;

/**
 * A node that checks deprecated variable usage.
 *
 * @see \Drupal\Core\Template\TwigNodeVisitorCheckDeprecations
 * @see \Drupal\Core\Template\TwigExtension::checkDeprecations()
 */
class TwigNodeCheckDeprecations extends Node {

  /**
   * {@inheritdoc}
   */
  public function __construct(protected array $usedNames) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public function compile(Compiler $compiler) {
    $usedNamesNode = new ArrayExpression([], $this->getTemplateLine());
    foreach ($this->usedNames as $name) {
      $usedNamesNode->addElement(new ConstantExpression($name, $this->getTemplateLine()));
    }

    $compiler->write("\$this->env->getExtension('\Drupal\Core\Template\TwigExtension')\n");
    $compiler->indent();
    $compiler->write("->checkDeprecations(\$context, ");
    $compiler->subcompile($usedNamesNode);
    $compiler->raw(");");
    $compiler->outdent();
  }

}
