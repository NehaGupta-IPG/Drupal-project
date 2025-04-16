<?php

declare(strict_types=1);

namespace Drupal\field\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;
use Drupal\Core\Validation\Attribute\Constraint;

/**
 * Checks if a plugin exists and optionally implements a particular interface.
 */
#[Constraint(
  id: 'NoEntitiesExistYetWithHigherCardinality',
  label: new TranslatableMarkup('No entities exist with higher cardinality', [], ['context' => 'Validation'])
)]
class NoEntitiesExistYetWithHigherCardinality extends SymfonyConstraint {

  /**
   * The error message if a plugin does not implement the expected interface.
   *
   * @var string
   */
  public string $message = "The field '@field_name' of entity type '@entity_type' has more entries (@max_delta) than the cardinality (@cardinality) allows.";
  /**
   * The entity type to check.
   *
   * @var string
   */
  public string $entityType;

  /**
   * The field name to check.
   *
   * @var string
   */
  public string $fieldName;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['entityType', 'fieldName'];
  }

}
