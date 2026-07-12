<?php

declare(strict_types=1);

namespace Drupal\Core\Environment;

/**
 * Trait which implements the SubstitutionInterface::apply() method.
 */
trait SubstitutionApplyTrait {

  /**
   * {@inheritdoc}
   */
  public function applies(EnvironmentParameter $param): bool {
    return ($this instanceof $param->name);
  }

}
