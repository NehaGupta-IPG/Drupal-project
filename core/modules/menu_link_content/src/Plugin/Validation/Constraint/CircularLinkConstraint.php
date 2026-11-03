<?php

namespace Drupal\menu_link_content\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\CompositeConstraintBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Validation constraint for preventing circular links.
 */
#[Constraint(
  id: 'CircularLink',
  label: new TranslatableMarkup('Circular Link.', [], ['context' => 'Validation'])
)]
class CircularLinkConstraint extends CompositeConstraintBase {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'A menu item may not be linked to itself.';

  /**
   * {@inheritdoc}
   */
  public function coversFields() {
    return ['url'];
  }

}
