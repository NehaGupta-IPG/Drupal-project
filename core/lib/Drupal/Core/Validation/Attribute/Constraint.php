<?php

namespace Drupal\Core\Validation\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Constraint attribute object.
 *
 * Plugin Namespace: Plugin\Validation\Constraint
 *
 * For a working example, see
 * \Drupal\Core\Validation\Plugin\Validation\Constraint\LengthConstraint
 *
 * @see \Drupal\Core\Validation\ConstraintManager
 * @see \Symfony\Component\Validator\Constraint
 * @see hook_validation_constraint_alter()
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Constraint extends Plugin {

  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly string|array|false $type = [],
    public readonly ?string $deriver = NULL,
  ) {}

}
