<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\Validation\Plugin\Validation\Constraint\StringEqualsConcatenatedValuesConstraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\StringEqualsConcatenatedValuesConstraintValidator;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the StringEqualsConcatenatedValues validator.
 */
#[Group('Validation')]
#[CoversClass(StringEqualsConcatenatedValuesConstraint::class)]
#[CoversClass(StringEqualsConcatenatedValuesConstraintValidator::class)]
#[RunTestsInSeparateProcesses]
class StringEqualsConcatenatedValuesConstraintValidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('config_test');
  }

  /**
   * Tests basic validation of concatenated config values.
   *
   * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\StringEqualsConcatenatedValuesConstraint
   */
  public function testStringEqualsConcatenatedValuesConstraint(): void {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = \Drupal::service('config.typed');
    /** @var \Drupal\Core\Config\Schema\TypedConfigInterface $typed_config */
    $typed_config = $typed_config_manager->get('config_test.validation');

    // Test valid names.
    $typed_config->get('string_concat_values')->setValue('localhost.llama');
    $this->assertCount(0, $typed_config->validate());

    // Test invalid names.
    $typed_config->get('string_concat_values')->setValue('drupal.kitten');

    $constraintViolationList = $typed_config->validate();
    $this->assertCount(1, $constraintViolationList);

    $this->assertSame("Expected 'localhost.llama', not 'drupal.kitten'. Format: '&lt;%parent.string_concat_value_1&gt;.&lt;%parent.string_concat_value_2&gt;'.", (string) $constraintViolationList->get(0)->getMessage());
  }

  /**
   * Tests that validation fails if the schema references missing properties.
   *
   * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\StringEqualsConcatenatedValuesConstraint
   */
  public function testInvalidReferenceToProperties(): void {
    $this->expectExceptionMessageIs('Schema errors for config_test.validation with the following errors: 0 [string_concat_values_invalid] This validation constraint is configured to inspect the properties <em class="placeholder">%parent.invalid, %parent.reference</em>, but some do not exist: <em class="placeholder">%parent.invalid, %parent.reference</em>.');

    /** @var \Drupal\Core\Config\Config $editable_config */
    $editable_config = \Drupal::configFactory()->getEditable('config_test.validation');
    $editable_config->set('string_concat_values_invalid', 'test');
    $editable_config->save();
  }

  /**
   * Tests that validation fails if reserved characters are not replaced.
   *
   * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\StringEqualsConcatenatedValuesConstraint
   */
  public function testReservedCharacters(): void {
    /** @var \Drupal\Core\Config\Config $editable_config */
    $editable_config = \Drupal::configFactory()->getEditable('config_test.validation');
    $editable_config->set('string_concat_value_1', 'test|||value');
    $editable_config->set('string_concat_values', 'test>>>value.llama');
    $editable_config->save();

    $this->expectExceptionMessageIs("Schema errors for config_test.validation with the following errors: 0 [string_concat_values] Expected 'test&gt;&gt;&gt;value.llama', not 'test|||value.llama'. Format: '&lt;%parent.string_concat_value_1&gt;.&lt;%parent.string_concat_value_2&gt;'.");

    $editable_config->set('string_concat_values', 'test|||value.llama');
    $editable_config->save();
  }

  /**
   * Tests concatenation of basic schema value types.
   *
   * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\StringEqualsConcatenatedValuesConstraint
   */
  #[DataProvider('valueTypesProvider')]
  public function testValueTypes(string|int|bool $first_value, string|int|bool $second_value, string $result, string $invalid_result): void {
    $editable_config = \Drupal::configFactory()->getEditable('config_test.validation');
    $editable_config->set('string_concat_value_1', $first_value);
    $editable_config->set('string_concat_value_2', $second_value);
    $editable_config->set('string_concat_values', $result);
    $editable_config->save();

    $this->expectExceptionMessageIs("Schema errors for config_test.validation with the following errors: 0 [string_concat_values] Expected '$result', not '$invalid_result'. Format: '&lt;%parent.string_concat_value_1&gt;.&lt;%parent.string_concat_value_2&gt;'.");
    $editable_config->set('string_concat_values', $invalid_result);
    $editable_config->save();
  }

  /**
   * Data provider for testValueTypes().
   */
  public static function valueTypesProvider(): array {
    return [
      ['part1', 'part2', 'part1.part2', 'part1-part2'],
      [123, 456, '123.456', '123-456'],
      [TRUE, FALSE, '1.', '1-0'],
    ];
  }

}
