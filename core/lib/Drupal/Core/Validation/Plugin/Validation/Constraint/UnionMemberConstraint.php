<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks that a union value matches one of the members of its union.
 *
 * This constraint is a default constraint of the 'union' data type, so it does
 * not have to be added to a union definition by hand.
 *
 * @see \Drupal\Core\TypedData\Plugin\DataType\Union
 */
#[Constraint(
  id: 'UnionMember',
  label: new TranslatableMarkup('Union member', [], ['context' => 'Validation']),
  type: ['union'],
)]
class UnionMemberConstraint extends SymfonyConstraint {

  /**
   * Constructs a UnionMemberConstraint object.
   *
   * @param bool $strict
   *   (optional) Whether a value that matches more than one member is a
   *   violation. Defaults to FALSE, which allows overlapping members and lets
   *   declaration order decide. Strictness never changes which member a value
   *   resolves to; it only reports the overlap.
   * @param string $message
   *   (optional) The violation message for a value matching no member.
   * @param string $ambiguousMessage
   *   (optional) The violation message for a value matching more than one
   *   member while $strict is TRUE.
   * @param array|null $groups
   *   (optional) The validation groups.
   * @param mixed $payload
   *   (optional) The domain-specific data attached to the constraint.
   */
  public function __construct(
    public bool $strict = FALSE,
    public string $message = 'The value does not match any allowed type.',
    public string $ambiguousMessage = 'The value matches more than one allowed type: @types.',
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
  }

}
