<?php

declare(strict_types=1);

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Valid HTML constraint.
 *
 * Determines if a string is valid HTML5.
 */
#[Constraint(
  id: 'Html',
  label: new TranslatableMarkup('Valid HTML', [], ['context' => 'Validation'])
)]
class HtmlConstraint extends SymfonyConstraint {

  /**
   * The html parser mode, valid values are 'fragment' or 'document'.
   *
   * @var "fragment"|"document"
   */
  public string $mode;

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return 'mode';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['mode'];
  }

}
