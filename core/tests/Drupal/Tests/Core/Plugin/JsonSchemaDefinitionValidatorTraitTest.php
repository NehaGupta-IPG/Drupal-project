<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Plugin;

use Drupal\Core\Plugin\JsonSchemaDefinitionValidatorTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for JsonSchemaDefinitionValidatorTrait.
 */
#[CoversClass(JsonSchemaDefinitionValidatorTrait::class)]
#[Group('Plugin')]
class JsonSchemaDefinitionValidatorTraitTest extends TestCase {

  /**
   * Tests that definitions are not validated without a validator.
   */
  public function testValidateDefinitionWithoutValidator(): void {
    $validator = new JsonSchemaDefinitionValidatorTestClass();
    $this->assertTrue($validator->validate([
      'provider' => 'my_module',
      'id' => 'my_plugin',
    ]));
  }

  /**
   * Tests validation of a valid definition.
   */
  public function testValidateDefinitionValid(): void {
    $validator = new JsonSchemaDefinitionValidatorTestClass();
    $validator->setValidator();
    $this->assertTrue($validator->validate([
      'provider' => 'my_module',
      'id' => 'my_plugin',
      'label' => 'My plugin',
    ]));
  }

  /**
   * Tests validation of an invalid definition.
   */
  public function testValidateDefinitionInvalid(): void {
    $validator = new JsonSchemaDefinitionValidatorTestClass();
    $validator->setValidator();

    $this->expectException(JsonSchemaDefinitionValidatorTestException::class);
    $this->expectExceptionMessage('my_module:my_plugin Error in definition `my_plugin`:[label] The property label is required');
    $validator->validate([
      'provider' => 'my_module',
      'id' => 'my_plugin',
    ]);
  }

}

/**
 * A class using JsonSchemaDefinitionValidatorTrait for testing purposes.
 */
class JsonSchemaDefinitionValidatorTestClass {

  use JsonSchemaDefinitionValidatorTrait;

  /**
   * Exposes the protected validation method.
   *
   * @param array $definition
   *   The definition to check.
   *
   * @return bool
   *   The validation result.
   */
  public function validate(array $definition): bool {
    return $this->validateDefinition($definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefinitionSchemaPath(): string {
    return __DIR__ . '/Fixtures/json_schema_definition.schema.json';
  }

  /**
   * {@inheritdoc}
   */
  protected function createInvalidDefinitionException(string $message): \Exception {
    return new JsonSchemaDefinitionValidatorTestException($message);
  }

}

/**
 * An exception thrown for invalid definitions in tests.
 */
class JsonSchemaDefinitionValidatorTestException extends \Exception {

}
