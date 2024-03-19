<?php

declare(strict_types=1);

namespace Drupal\views\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if a bundle exists on a certain content entity type.
 */
#[Constraint(
  id: 'AutoCreateEntityBundleExists',
  label: new TranslatableMarkup('Entity bundle exists', [], ['context' => 'Validation']),
  type: 'entity'
)]
class AutoCreateEntityBundleExistsConstraint extends SymfonyConstraint {

  /**
   * The error message if validation fails.
   */
  public string $message = "The '@bundle' bundle does not exist on the '@entity_type_id' entity type.";

  /**
   * The host entity type ID.
   *
   * This can contain variable values (e.g., `%parent`) that will be replaced.
   *
   * @see \Drupal\Core\Config\Schema\TypeResolver::replaceVariable()
   */
  public string $entityTypeId;

  /**
   * The field name which has auto creation enabled.
   *
   * This can contain variable values (e.g., `%parent`) that will be replaced.
   *
   * @see \Drupal\Core\Config\Schema\TypeResolver::replaceVariable()
   */
  public string $fieldName;

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return 'entityTypeId';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['entityTypeId', 'fieldName'];
  }

}
