<?php

namespace Drupal\user\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraints\NoSuspiciousCharacters;

/**
 * Checks if a username contains suspicious Unicode characters.
 */
#[Constraint(
  id: 'UserNameNoSuspiciousCharacters',
  label: new TranslatableMarkup('User name no suspicious characters', [], ['context' => 'Validation'])
)]
class UserNameNoSuspiciousCharacters extends NoSuspiciousCharacters {

}
