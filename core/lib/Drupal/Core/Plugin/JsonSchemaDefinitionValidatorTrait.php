<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Validates plugin definitions against a JSON schema.
 *
 * Validation only happens when the justinrainbow/json-schema library is
 * available and a validator has been set with ::setValidator().
 */
trait JsonSchemaDefinitionValidatorTrait {

  /**
   * The schema validator.
   *
   * This property will only be set if the validator library is available.
   *
   * @var \JsonSchema\Validator|null
   */
  protected ?Validator $validator = NULL;

  /**
   * Sets the validator service if available.
   *
   * @param \JsonSchema\Validator|null $validator
   *   The JSON Validator class.
   */
  public function setValidator(?Validator $validator = NULL): void {
    if (NULL !== $validator) {
      $this->validator = $validator;
      return;
    }
    if (class_exists(Validator::class)) {
      $this->validator = new Validator();
    }
  }

  /**
   * Gets the absolute path of the JSON schema to validate definitions with.
   *
   * @return string
   *   The absolute path of the JSON schema file.
   */
  abstract protected function getDefinitionSchemaPath(): string;

  /**
   * Creates the exception thrown when a definition is not valid.
   *
   * @param string $message
   *   The exception message.
   *
   * @return \Exception
   *   The exception to throw.
   */
  abstract protected function createInvalidDefinitionException(string $message): \Exception;

  /**
   * Validates a definition against the JSON schema specification.
   *
   * @param array $definition
   *   The definition to check. It must contain the "provider" and "id" keys.
   *
   * @return bool
   *   TRUE if the definition is valid or if the validator is not available.
   *
   * @throws \Exception
   *   Thrown when the definition is not valid. The exception class is set by
   *   ::createInvalidDefinitionException().
   */
  protected function validateDefinition(array $definition): bool {
    // If the validator isn't set, then the validation library is not installed.
    if (!$this->validator) {
      return TRUE;
    }

    $schema = (object) ['$ref' => 'file://' . $this->getDefinitionSchemaPath()];

    $definition_object = Validator::arrayToObjectRecursive($definition);

    $this->validator->validate($definition_object, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

    if ($this->validator->isValid()) {
      return TRUE;
    }

    $message_parts = array_map(
      static fn (array $error): string => sprintf("[%s] %s", $error['property'], $error['message']),
      $this->validator->getErrors()
    );
    $message = implode(", ", $message_parts);

    throw $this->createInvalidDefinitionException(
      sprintf(
        '%s:%s Error in definition `%s`:%s',
        $definition['provider'],
        $definition['id'],
        $definition_object->id,
        $message
      )
    );
  }

}
