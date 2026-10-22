<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Formats entity reference rejections using the available input context.
 */
final class EntityReferenceMessageFormatter {

  /**
   * Formats a reference rejection, optionally identifying the submitted value.
   *
   * @param string|\Stringable $entity_type_label
   *   The singular entity type label.
   * @param string|null $value
   *   The original autocomplete input, or NULL for a selected reference.
   * @param \Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint|null $constraint
   *   The message configuration, or NULL to use the default messages.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The reference rejection message.
   */
  public static function formatMessage(string|\Stringable $entity_type_label, ?string $value = NULL, ?ValidReferenceConstraint $constraint = NULL): TranslatableMarkup {
    $constraint ??= new ValidReferenceConstraint();
    $message = $value !== NULL ? $constraint->noReferenceableMatchMessage : $constraint->noReferenceableSelectionMessage;
    $parameters = ['@entity_type' => (string) $entity_type_label];
    if ($value !== NULL) {
      $parameters['%value'] = $value;
    }

    // Constraint message properties are extracted for translation by POTX.
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return new TranslatableMarkup($message, $parameters);
  }

  /**
   * Adapts Core reference messages without changing the original violations.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The field violations, in their existing order.
   * @param string|\Stringable $entity_type_label
   *   The singular entity type label.
   * @param string[] $values
   *   Original input values, keyed by their current field item delta. Messages
   *   without a corresponding input value retain their diagnostic wording.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The presentation violations with their metadata and order preserved.
   */
  public static function formatViolations(ConstraintViolationListInterface $violations, string|\Stringable $entity_type_label, array $values): ConstraintViolationListInterface {
    if (!$values) {
      return $violations;
    }

    $result = new ConstraintViolationList();
    $defaults = new ValidReferenceConstraint();
    foreach ($violations as $violation) {
      if (!$violation instanceof ConstraintViolation || !$violation->getConstraint() instanceof ValidReferenceConstraint) {
        $result->add($violation);
        continue;
      }

      $constraint = $violation->getConstraint();
      $message_property = match ($violation->getCode()) {
        ValidReferenceConstraint::NEW_ENTITY_NOT_REFERENCEABLE_ERROR => 'invalidAutocreateMessage',
        ValidReferenceConstraint::EXISTING_ENTITY_NOT_REFERENCEABLE_ERROR => 'message',
        ValidReferenceConstraint::ENTITY_DOES_NOT_EXIST_ERROR => 'nonExistingMessage',
        default => NULL,
      };
      $uses_default_message = $message_property !== NULL
        && $constraint->$message_property === $defaults->$message_property;
      $path = explode('.', $violation->getPropertyPath(), 2);
      if (!$uses_default_message || !array_key_exists($path[0], $values)) {
        $result->add($violation);
        continue;
      }

      $message = static::formatMessage($entity_type_label, $values[$path[0]], $constraint);
      $result->add(new ConstraintViolation(
        $message,
        $message->getUntranslatedString(),
        $message->getArguments() + $violation->getParameters(),
        $violation->getRoot(),
        $violation->getPropertyPath(),
        $violation->getInvalidValue(),
        $violation->getPlural(),
        $violation->getCode(),
        $constraint,
        $violation->getCause(),
      ));
    }

    return $result;
  }

}
