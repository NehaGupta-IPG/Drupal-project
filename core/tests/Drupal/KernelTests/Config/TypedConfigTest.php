<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Config;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\Schema\Sequence;
use Drupal\Core\Config\Schema\SequenceDataDefinition;
use Drupal\Core\Config\Schema\TypedConfigInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Validation\ExecutionContextFactory;
use Drupal\Core\TypedData\Validation\RecursiveContextualValidator;
use Drupal\Core\TypedData\Validation\RecursiveValidator;
use Drupal\Core\Validation\ConstraintValidatorFactory;
use Drupal\Core\Validation\DrupalTranslator;
use Drupal\Core\Validation\Plugin\Validation\Constraint\PrimitiveTypeConstraintValidator;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;

// cspell:ignore nyans
/**
 * Tests config validation mechanism.
 */
#[Group('Config')]
#[RunTestsInSeparateProcesses]
class TypedConfigTest extends KernelTestBase {

  use SchemaCheckTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_test'];

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'config_test.validation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // When saving trusted data, it normally skips validation. So we need
    // to disable the config schema checker.
    // @todo Possibly remove in https://www.drupal.org/i/3347842
    if ($this->name() === 'testPrimitiveDataTypesConsistencyWhenTrustingData') {
      self::$configSchemaCheckerExclusions[] = 'config_test.types';
    }

    parent::setUp();

    $this->installConfig('config_test');
  }

  /**
   * Verifies that the Typed Data API is implemented correctly.
   */
  public function testTypedDataAPI(): void {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = \Drupal::service('config.typed');

    // Test non-existent data.
    try {
      $typed_config_manager->get('config_test.non_existent');
      $this->fail('Expected error when trying to get non-existent typed config.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertEquals('Missing required data for typed configuration: config_test.non_existent', $e->getMessage());
    }

    /** @var \Drupal\Core\Config\Schema\TypedConfigInterface $typed_config */
    $typed_config = $typed_config_manager->get('config_test.validation');

    // Test a primitive.
    $string_data = $typed_config->get('llama');
    $this->assertInstanceOf(StringInterface::class, $string_data);
    $this->assertEquals('llama', $string_data->getValue());

    // Test complex data.
    $mapping = $typed_config->get('cat');
    /** @var \Drupal\Core\TypedData\ComplexDataInterface $mapping */
    $this->assertInstanceOf(ComplexDataInterface::class, $mapping);
    $this->assertInstanceOf(StringInterface::class, $mapping->get('type'));
    $this->assertEquals('kitten', $mapping->get('type')->getValue());
    $this->assertInstanceOf(IntegerInterface::class, $mapping->get('count'));
    $this->assertEquals(2, $mapping->get('count')->getValue());
    // Verify the item metadata is available.
    $this->assertInstanceOf(ComplexDataDefinitionInterface::class, $mapping->getDataDefinition());
    $this->assertArrayHasKey('type', $mapping->getProperties());
    $this->assertArrayHasKey('count', $mapping->getProperties());

    // Test accessing sequences.
    $sequence = $typed_config->get('giraffe');
    /** @var \Drupal\Core\TypedData\ListInterface $sequence */
    $this->assertInstanceOf(SequenceDataDefinition::class, $sequence->getDataDefinition());
    $this->assertSame(Sequence::class, $sequence->getDataDefinition()->getClass());
    $this->assertSame('sequence', $sequence->getDataDefinition()->getDataType());
    $this->assertInstanceOf(ComplexDataInterface::class, $sequence);
    $this->assertInstanceOf(StringInterface::class, $sequence->get('hum1'));
    $this->assertEquals('hum1', $sequence->get('hum1')->getValue());
    $this->assertEquals('hum2', $sequence->get('hum2')->getValue());
    $this->assertCount(2, $sequence->getIterator());
    // Verify the item metadata is available.
    $this->assertInstanceOf(SequenceDataDefinition::class, $sequence->getDataDefinition());

    // Test accessing typed config objects for simple config and config
    // entities.
    $typed_config_manager = \Drupal::service('config.typed');
    $typed_config = $typed_config_manager->createFromNameAndData('config_test.validation', \Drupal::configFactory()->get('config_test.validation')->get());
    $this->assertInstanceOf(TypedConfigInterface::class, $typed_config);
    $this->assertEquals([
      '_core',
      'llama',
      'cat',
      'giraffe',
      'uuid',
      'string__not_blank',
      'host',
      'block_id',
      'block_revision_id',
      'block_serialized',
      'composite',
      'string_concat_value_1',
      'string_concat_value_2',
      'string_concat_values',
    ], array_keys($typed_config->getElements()));
    $this->assertSame('config_test.validation', $typed_config->getName());
    $this->assertSame('config_test.validation', $typed_config->getPropertyPath());
    $this->assertSame('config_test.validation.llama', $typed_config->get('llama')->getPropertyPath());

    $config_test_entity = \Drupal::entityTypeManager()->getStorage('config_test')->create([
      'id' => 'test',
      'label' => 'Test',
      'weight' => 11,
      'style' => 'test_style',
    ]);

    $typed_config = $typed_config_manager->createFromNameAndData($config_test_entity->getConfigDependencyName(), $config_test_entity->toArray());
    $this->assertInstanceOf(TypedConfigInterface::class, $typed_config);
    $this->assertEquals(
      [
        'uuid',
        'langcode',
        'status',
        'dependencies',
        'id',
        'label',
        'weight',
        'style',
        'size',
        'size_value',
        'protected_property',
      ],
      array_keys($typed_config->getElements()));
  }

  /**
   * Tests the behavior of `NotBlank` on required data.
   *
   * @see \Drupal\Core\TypedData\DataDefinition::getConstraints()
   * @see \Drupal\Core\TypedData\DataDefinitionInterface::isRequired()
   * @see \Drupal\Core\Validation\Plugin\Validation\Constraint\NotNullConstraint
   * @see \Symfony\Component\Validator\Constraints\NotBlank::$allowNull
   */
  #[TestWith(["", FALSE, "This value should not be blank."])]
  #[TestWith(["", TRUE, "This value should not be blank."])]
  #[TestWith([NULL, FALSE, "This value should not be blank."])]
  #[TestWith([NULL, TRUE, "This value should not be null."])]
  public function testNotBlankInteractionWithNotNull(?string $value, bool $is_required, string $expected_message): void {
    \Drupal::configFactory()->getEditable('config_test.validation')
      ->set('string__not_blank', $value)
      ->save();

    $typed_config = \Drupal::service('config.typed')->get('config_test.validation');
    $typed_config->get('string__not_blank')->getDataDefinition()->setRequired($is_required);
    $result = $typed_config->validate();

    // Expect 1 validation error message: the one from `NotBlank` or `NotNull`.
    $this->assertCount(1, $result);
    $this->assertSame('string__not_blank', $result->get(0)->getPropertyPath());
    $this->assertEquals($expected_message, $result->get(0)->getMessage());
  }

  /**
   * Provides test cases for testing the `type: boolean` primitive type.
   *
   * @return \Generator
   *   Each returned value is an array with at least 4 values, potentially 6, to
   *   pass into ::assertPrimitiveDataTypesConsistency():
   *   1. the config key whose value to set
   *   2. the value to set
   *   3. the value we expect to be validated
   *   4. the value we expect to be saved
   *   5. the expected validation errors from TypedDataInterface::validate().
   *   6. whether we expect a complaint from the ConfigSchemaChecker when saving
   *      as trusted data (and hence bypassing the magic-cast-during-save logic).
   *
   * @todo Possibly remove 6 in https://www.drupal.org/i/3347842
   */
  public static function providerBooleanDataType(): \Generator {
    // @see core/modules/config/tests/config_test/config/install/config_test.types.yml
    $key = 'boolean';

    // No magic: values 2–4 are identical; no validation errors (value 5).
    yield "$key: NO MAGIC: `true`" => [$key, TRUE, TRUE, TRUE, []];
    yield "$key: NO MAGIC: `false`" => [$key, FALSE, FALSE, FALSE, []];
    // Every key can be marked optional: the validator should not complain.
    yield "$key: NO MAGIC: `null`" => [$key, NULL, NULL, NULL, []];

    // Magic: values 2–4 are not identical.
    // First: intentional magic — no validation errors triggered.
    yield "$key: MAGIC: `0` → `false`" => [
      $key, 0, 0, FALSE, [],
      'variable type is integer but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    yield "$key: MAGIC: `1` → `true`" => [
      $key, 1, 1, TRUE, [],
      'variable type is integer but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    yield "$key: MAGIC: `'1'` → `true`" => [
      $key, '1', '1', TRUE, [],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    yield "$key: MAGIC: `'0'` → `false`" => [
      $key, '0', '0', FALSE, [],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    // Second: accidental magic — validation errors are triggered.
    yield "$key: MAGIC: `'test'` → `true`" => [
      $key, 'test', 'test', TRUE, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    yield "$key: MAGIC: `'yes'` → `true`" => [
      $key, 'no', 'no', TRUE, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    yield "$key: MAGIC: `'no'` → `true` ⚠️" => [
      $key, 'no', 'no', TRUE, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    yield "$key: MAGIC: `'false'` → `true` ⚠️" => [
      $key, 'false', 'false', TRUE, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
    yield "$key: MAGIC: `''` → `false`" => [
      $key, '', '', FALSE, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData',
    ];
  }

  /**
   * Provides test cases for testing the `type: integer` primitive type.
   *
   * @return \Generator
   *   Each returned value is an array with at least 4 values, potentially 6, to
   *   pass into ::assertPrimitiveDataTypesConsistency():
   *   1. the config key whose value to set
   *   2. the value to set
   *   3. the value we expect to be validated
   *   4. the value we expect to be saved
   *   5. the expected validation errors
   *   6. whether we expect a complaint from the ConfigSchemaChecker when saving
   *      as trusted data (and hence bypassing the magic-cast-during-save logic).
   *
   * @todo Possibly remove 6 in https://www.drupal.org/i/3347842
   */
  public static function providerIntegerDataType(): \Generator {
    // @see core/modules/config/tests/config_test/config/install/config_test.types.yml
    $key = 'int';

    // No magic: arguments 2–4 are identical; no validation errors (item 5).
    yield "$key: NO MAGIC: `0`" => [$key, 0, 0, 0, []];
    yield "$key: NO MAGIC: `1`" => [$key, 1, 1, 1, []];
    yield "$key: NO MAGIC: `-1`" => [$key, -1, -1, -1, []];
    // Testing min and max value for integers.
    yield "$key: NO MAGIC: `-2147483648`" => [$key, -2147483648, -2147483648, -2147483648, []];
    // Every key can be marked optional: the validator should not complain.
    yield "$key: NO MAGIC: `null`" => [$key, NULL, NULL, NULL, []];

    // Magic: arguments 2–4 are not identical.
    // First: intentional magic — no validation errors triggered.
    yield "$key: MAGIC: `'55'` → `55`" => [
      $key, '55', '55', 55, [],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\IntegerData',
    ];
    yield "$key: MAGIC: `true` → `1`" => [
      $key, TRUE, TRUE, 1, [],
      'variable type is boolean but applied schema class is Drupal\Core\TypedData\Plugin\DataType\IntegerData',
    ];
    // Second: accidental magic: validation errors are triggered.
    yield "$key: MAGIC: `''` → `null`" => [
      $key, '', '', NULL, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\IntegerData',
    ];
    yield "$key: MAGIC: `'55%'` → `55`" => [
      $key, '55%', '55%', 55, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\IntegerData',
    ];
    yield "$key: MAGIC: `false` → `0`" => [
      $key, FALSE, FALSE, 0, [$key => 'This value should be of the correct primitive type.'],
      'variable type is boolean but applied schema class is Drupal\Core\TypedData\Plugin\DataType\IntegerData',
    ];
  }

  /**
   * Provides test cases for testing the `type: float` primitive type.
   *
   * @return \Generator
   *   Each returned value is an array with at least 4 values, potentially 6, to
   *   pass into ::assertPrimitiveDataTypesConsistency():
   *   1. the config key whose value to set
   *   2. the value to set
   *   3. the value we expect to be validated
   *   4. the value we expect to be saved
   *   5. the expected validation errors
   *   6. whether we expect a complaint from the ConfigSchemaChecker when saving
   *      as trusted data (and hence bypassing the magic-cast-during-save logic).
   *
   * @todo Possibly remove 6 in https://www.drupal.org/i/3347842
   */
  public static function providerFloatDataType(): \Generator {
    // @see core/modules/config/tests/config_test/config/install/config_test.types.yml
    $key = 'float';

    // No magic: arguments 2–4 are identical; no validation errors (item 5).
    yield "$key: NO MAGIC: `0.0`" => [$key, 0.0, 0.0, 0.0, []];
    yield "$key: NO MAGIC: `1.0`" => [$key, 1.0, 1.0, 1.0, []];
    yield "$key: NO MAGIC: `-1.0`" => [$key, -1.0, -1.0, -1.0, []];
    yield "$key: NO MAGIC: `3.14159`" => [$key, 3.14159, 3.14159, 3.14159, []];
    yield "$key: NO MAGIC: `-3.14159`" => [$key, 3.14159, 3.14159, 3.14159, []];
    // Every key can be marked optional: the validator should not complain.
    yield "$key: NO MAGIC: `null`" => [$key, NULL, NULL, NULL, []];

    // Reasonable magical conversions: integers can be represented as floats;
    // arguments 2 and 3 are identical, array item 4 is a float.
    yield "$key: REASONABLE MAGIC: `0`" => [$key, 0, 0, 0.0, []];
    yield "$key: REASONABLE MAGIC: `1`" => [$key, 1, 1, 1.0, []];
    yield "$key: REASONABLE MAGIC: `-1`" => [$key, -1, -1, -1.0, []];
    // Testing min and max value for integers.
    yield "$key: REASONABLE MAGIC: `-2147483648`" => [$key, -2147483648, -2147483648, -2147483648.0, []];

    // Magic: arguments 2–4 are not identical.
    // First: intentional magic — no validation errors triggered.
    yield "$key: MAGIC: `'3.14159'` → `3.14159`" => [
      $key, '3.14159', '3.14159', 3.14159, [],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\FloatData',
    ];
    yield "$key: MAGIC: `true` → `1.0`" => [
      $key, TRUE, TRUE, 1.0, [],
      'variable type is boolean but applied schema class is Drupal\Core\TypedData\Plugin\DataType\FloatData',
    ];
    // Second: accidental magic: validation errors are triggered.
    yield "$key: MAGIC: `''` → `null`" => [
      $key, '', '', NULL, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\FloatData',
    ];
    yield "$key: MAGIC: `'55%'` → `55.0`" => [
      $key, '55%', '55%', 55.0, [$key => 'This value should be of the correct primitive type.'],
      'variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\FloatData',
    ];
    yield "$key: MAGIC: `false` → `0`" => [
      $key, FALSE, FALSE, 0.0, [$key => 'This value should be of the correct primitive type.'],
      'variable type is boolean but applied schema class is Drupal\Core\TypedData\Plugin\DataType\FloatData',
    ];
  }

  /**
   * Provides test cases for testing the `type: string` primitive type.
   *
   * @return \Generator
   *   Each returned value is an array with at least 4 values, potentially 6, to
   *   pass into ::assertPrimitiveDataTypesConsistency():
   *   1. the config key whose value to set
   *   2. the value to set
   *   3. the value we expect to be validated
   *   4. the value we expect to be saved
   *   5. the expected validation errors
   *   6. whether we expect a complaint from the ConfigSchemaChecker when saving
   *      as trusted data (and hence bypassing the magic-cast-during-save logic).
   *
   * @todo Possibly remove 6 in https://www.drupal.org/i/3347842
   */
  public static function providerStringDataType(): \Generator {
    // @see core/modules/config/tests/config_test/config/install/config_test.types.yml
    $key = 'string';

    // No magic: arguments 2–4 are identical.
    yield "$key: NO MAGIC: `''`" => [$key, '', '', '', []];
    yield "$key: NO MAGIC: `'55%'`" => [$key, '55%', '55%', '55%', []];
    // Every key can be marked optional: the validator should not complain.
    yield "$key: NO MAGIC: `null`" => [$key, NULL, NULL, NULL, []];

    // Magic: arguments 2–4 are not identical.
    // First: intentional magic — no validation errors triggered.
    // Booleans.
    yield "$key: MAGIC: `true` → `'1'`" => [
      $key, TRUE, TRUE, '1', [],
      'variable type is boolean but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    yield "$key: MAGIC: `false` → `''`" => [
      $key, FALSE, FALSE, '', [],
      'variable type is boolean but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    // Integers.
    yield "$key: 🪄: `0` → `'0'`" => [
      $key, 0, 0, '0', [],
      'variable type is integer but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    yield "$key: 🪄: `1` → `'1'`" => [
      $key, 1, 1, '1', [],
      'variable type is integer but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    yield "$key: 🪄: `-1` → `'-1'`" => [
      $key, -1, -1, '-1', [],
      'variable type is integer but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    // Testing min and max value for integers.
    yield "$key: 🪄: `-2147483648` -> '-2147483648'" => [
      $key, -2147483648, -2147483648, '-2147483648', [],
      'variable type is integer but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    // Floats.
    yield "$key:🪄: `0.0` → `'0'`" => [
      $key, 0.0, 0.0, '0', [],
      'variable type is double but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    yield "$key:🪄: `1.0` → `'1'`" => [
      $key, 1.0, 1.0, '1', [],
      'variable type is double but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    yield "$key:🪄: `-1.0` → `'-1'`" => [
      $key, -1.0, -1.0, '-1', [],
      'variable type is double but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    yield "$key:🪄: `3.14159` -> '3.14159'" => [
      $key, 3.14159, 3.14159, '3.14159', [],
      'variable type is double but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    yield "$key:🪄: `-3.14159` -> '-3.14159'" => [
      $key, -3.14159, -3.14159, '-3.14159', [],
      'variable type is double but applied schema class is Drupal\Core\TypedData\Plugin\DataType\StringData',
    ];
    // Second: accidental magic: validation errors are triggered.
    // None.
  }

  /**
   * Test primitive config data types consistency.
   */
  #[DataProvider('providerBooleanDataType')]
  #[DataProvider('providerIntegerDataType')]
  #[DataProvider('providerFloatDataType')]
  #[DataProvider('providerStringDataType')]
  public function testPrimitiveDataTypesConsistency(...$arguments): void {
    // Config schema checker errors cannot happen because the magic-cast-during-
    // save logic always casts primitives to comply with the type, even if the
    // original value does not have a sensible casting. Unset the 6th argument
    // before passing it to the assertion helper.
    // @see \Drupal\Core\Config\StorableConfigBase::castValue()
    $expects_config_schema_checker_error = FALSE;
    if (count($arguments) === 6) {
      // Assert explicitly when there is a mismatch between the validation
      // constraint logic and the magical casting logic.
      $expects_validation_error = !empty($arguments[4]);
      $expects_config_schema_checker_error = ($arguments[5] !== NULL);
      if ($expects_validation_error && $expects_config_schema_checker_error) {
        // Assert the test data correctly represents the inconsistency:
        // validation fails for this value, but magical casting during save
        // transforms it to a different (valid) value.
        $this->assertNotSame(
          $arguments[1],
          $arguments[3],
          sprintf(
            'The `type: %s` config schema type uses magical casting for the %s value that is inconsistent with the validation logic in %s.',
            $arguments[0],
            gettype($arguments[1]),
            PrimitiveTypeConstraintValidator::class,
          ),
        );
      }
      unset($arguments[5]);
    }
    $this->assertPrimitiveDataTypesConsistency(FALSE, ...$arguments);

    // For inconsistent magic cases, also verify at runtime that castValue()
    // actually transformed the value: read the persisted value from config
    // and assert it differs from what was originally set.
    if (isset($expects_validation_error) && $expects_validation_error && $expects_config_schema_checker_error) {
      $this->assertNotSame(
        $arguments[1],
        $this->config('config_test.types')->get($arguments[0]),
        sprintf(
          'Magical casting must have actually transformed the %s value for `type: %s`.',
          gettype($arguments[1]),
          $arguments[0],
        ),
      );
    }
  }

  /**
  * Test primitive config data types consistency with trusted data.
  *
  * @todo Possibly remove in https://www.drupal.org/i/3347842
  */
  #[DataProvider('providerBooleanDataType')]
  #[DataProvider('providerIntegerDataType')]
  #[DataProvider('providerFloatDataType')]
  #[DataProvider('providerStringDataType')]
  public function testPrimitiveDataTypesConsistencyWhenTrustingData(...$arguments): void {
    // When trusting data, the provided value is saved without magic. Set the
    // 5th argument ($expected_saved_value) to the 2nd argument ($value_to_set).
    $arguments[3] = $arguments[1];
    $this->assertPrimitiveDataTypesConsistency(TRUE, ...$arguments);
  }

  /**
   * Asserts consistency of primitive config data while validating and saving.
   *
   * @param bool $trust_data
   *   Whether to tell Config::save() to trust the config data or not.
   * @param string $key
   *   The key to set in the `config_test.types` config object.
   * @param mixed $value_to_set
   *   The value to set for the key.
   * @param mixed $expected_validated_value
   *   The value expected in PrimitiveTypeConstraintValidator.
   * @param mixed $expected_saved_value
   *   The value expected in the saved config object.
   * @param string[] $expected_validation_errors
   *   The expected validation errors (empty array if none are expected).
   * @param string|null $expected_config_schema_checker_error
   *   (optional) The expected config schema checker error, if any.
   */
  public function assertPrimitiveDataTypesConsistency(bool $trust_data, string $key, mixed $value_to_set, mixed $expected_validated_value, mixed $expected_saved_value, array $expected_validation_errors, ?string $expected_config_schema_checker_error = NULL): void {
    $name = 'config_test.types';
    $config = $this->config($name);

    // 1. Verify the original config object is valid.
    $typed_config_manager = $this->container->get('config.typed');
    $typed_config = $typed_config_manager->get($name);
    $this->assertSame([], static::violationListToArray(($typed_config->validate())));

    // 2. Set the given value: $value_to_set.
    $typed_config->set($key, $value_to_set);
    $this->assertSame($value_to_set, $typed_config->get($key)->getValue());

    // TRICKY: to be able to observe the value passed into
    // PrimitiveTypeConstraintValidator, the default recursive validator and the
    // default recursive contextual validator both have to be overridden.
    $introspectable_recursive_validator = new class(
      new ExecutionContextFactory(new DrupalTranslator()),
      new ConstraintValidatorFactory($this->container->get('class_resolver')),
      $typed_config_manager
    ) extends RecursiveValidator {

      /**
       * The property path to inspect.
       *
       * @var string|null
       */
      public ?string $propertyPathToInspect = NULL;

      /**
       * The value of the property path during validation.
       *
       * @var mixed
       */
      public mixed $propertyPathValueDuringValidation;

      /**
       * {@inheritdoc}
       */
      public function startContext($root = NULL): ContextualValidatorInterface {
        $recursive_contextual_validator = new class(
          $this->contextFactory->createContext($this, $root),
          $this,
          $this->constraintValidatorFactory,
          $this->typedDataManager
        ) extends RecursiveContextualValidator {

          /**
           * The recursive validator.
           *
           * @var \Drupal\Core\TypedData\Validation\RecursiveValidator|null
           */
          public ?RecursiveValidator $containingRecursiveValidator;

          /**
           * {@inheritdoc}
           */
          protected function validateNode(TypedDataInterface $data, $constraints = NULL, $is_root_call = FALSE) {
            if ($data->getPropertyPath() === $this->containingRecursiveValidator->propertyPathToInspect) {
              // @see
              $this->containingRecursiveValidator->propertyPathValueDuringValidation = $this->typedDataManager->getCanonicalRepresentation($data);
            }
            return parent::validateNode($data, $constraints, $is_root_call);
          }

        };
        $recursive_contextual_validator->containingRecursiveValidator = $this;

        return $recursive_contextual_validator;
      }

    };
    $introspectable_recursive_validator->propertyPathToInspect = "$name.$key";

    // 3. Validate, and assert the expected value is validated, and the expected
    //    validation errors are present.
    $typed_config_manager->setValidator($introspectable_recursive_validator);
    $violations = $typed_config->validate();
    $this->assertSame($introspectable_recursive_validator->propertyPathValueDuringValidation, $expected_validated_value);
    $this->assertSame($expected_validation_errors, static::violationListToArray($violations));

    // Transfer the data in $typed_config to the corresponding $config to allow
    // it to be saved.
    $developer_provided_data = $typed_config->toArray();
    $config->setData($developer_provided_data);
    $this->assertSame($value_to_set, $config->get($key));
    $config->save($trust_data);

    // 4. Verify the expected value is saved.
    $this->assertSame($expected_saved_value, $config->get($key));

    // 5 Verify the ConfigSchemaChecker is able to verify post-save.
    $this->assertSame($expected_config_schema_checker_error === NULL
      ? TRUE
      : ["config_test.types:$key" => $expected_config_schema_checker_error],
      $this->checkConfigSchema($typed_config_manager, $name, $config->get())
    );
  }

  /**
   * Asserts a set of validation errors is raised when the entity is validated.
   *
   * @param array<string, string|string[]> $expected_messages
   *   The expected validation error messages. Keys are property paths, values
   *   are the expected messages: a string if a single message is expected, an
   *   array of strings if multiple are expected.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violation_list
   *   The actual constraint violation list to compare against.
   */
  protected function assertValidationErrorsOLD(array $expected_messages, ConstraintViolationListInterface $violation_list): void {
    $actual_messages = [];
    foreach ($violation_list as $violation) {
      $property_path = $violation->getPropertyPath();

      if (!isset($actual_messages[$property_path])) {
        $actual_messages[$property_path] = (string) $violation->getMessage();
      }
      else {
        // Transform value from string to array.
        if (is_string($actual_messages[$property_path])) {
          $actual_messages[$property_path] = (array) $actual_messages[$violation->getPropertyPath()];
        }
        // And append.
        $actual_messages[$property_path][] = (string) $violation->getMessage();
      }
    }
    ksort($expected_messages);
    ksort($actual_messages);
    $this->assertSame($expected_messages, $actual_messages);
  }

  /**
   * Convert a violation list to an array.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violation_list
   *   The violation list to convert.
   *
   * @return array
   *   The converted violation list.
   */
  protected static function violationListToArray(ConstraintViolationListInterface $violation_list): array {
    $actual_messages = [];
    foreach ($violation_list as $violation) {
      $property_path = $violation->getPropertyPath();

      if (!isset($actual_messages[$property_path])) {
        $actual_messages[$property_path] = (string) $violation->getMessage();
      }
      else {
        // Transform value from string to array.
        if (is_string($actual_messages[$property_path])) {
          $actual_messages[$property_path] = (array) $actual_messages[$violation->getPropertyPath()];
        }
        // And append.
        $actual_messages[$property_path][] = (string) $violation->getMessage();
      }
    }
    ksort($actual_messages);
    return $actual_messages;
  }

  /**
   * Tests config validation via the Typed Data API.
   */
  public function testSimpleConfigValidation(): void {
    $config = \Drupal::configFactory()->getEditable('config_test.validation');
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = \Drupal::service('config.typed');
    /** @var \Drupal\Core\Config\Schema\TypedConfigInterface $typed_config */
    $typed_config = $typed_config_manager->get('config_test.validation');

    $result = $typed_config->validate();
    $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
    $this->assertEmpty($result);

    // Test constraints on primitive types.
    $config->set('llama', 'elephant');
    $config->save();

    $typed_config = $typed_config_manager->get('config_test.validation');
    $result = $typed_config->validate();
    // Its not a valid llama anymore.
    $this->assertCount(1, $result);
    $this->assertEquals('no valid llama', $result->get(0)->getMessage());

    // Test constraints on mapping.
    $config->set('llama', 'llama');
    $config->set('cat.type', 'nyans');
    $config->save();

    $typed_config = $typed_config_manager->get('config_test.validation');
    $result = $typed_config->validate();
    $this->assertEmpty($result);

    // Test constrains on nested mapping.
    $config->set('cat.type', 'tiger');
    $config->save();

    $typed_config = $typed_config_manager->get('config_test.validation');
    $result = $typed_config->validate();
    $this->assertCount(1, $result);
    $this->assertEquals('no valid cat', $result->get(0)->getMessage());

    // Test constrains on sequences elements.
    $config->set('cat.type', 'nyans');
    $config->set('giraffe', ['muh', 'hum2']);
    $config->save();
    $typed_config = $typed_config_manager->get('config_test.validation');
    $result = $typed_config->validate();
    $this->assertCount(1, $result);
    $this->assertEquals('Giraffes just hum', $result->get(0)->getMessage());

    // Test constrains on the sequence itself.
    $config->set('giraffe', ['hum', 'hum2', 'invalid-key' => 'hum']);
    $config->save();

    $typed_config = $typed_config_manager->get('config_test.validation');
    $result = $typed_config->validate();
    $this->assertCount(1, $result);
    $this->assertEquals('giraffe', $result->get(0)->getPropertyPath());
    $this->assertEquals('Invalid giraffe key.', $result->get(0)->getMessage());

    // Validates mapping.
    $typed_config = $typed_config_manager->get('config_test.validation');
    $value = $typed_config->getValue();
    unset($value['giraffe']);
    $value['elephant'] = 'foo';
    $value['zebra'] = 'foo';
    $typed_config->setValue($value);
    $result = $typed_config->validate();
    $this->assertCount(3, $result);
    // 2 constraint violations triggered by the default validation constraint
    // for `type: mapping`
    // @see \Drupal\Core\Validation\Plugin\Validation\Constraint\ValidKeysConstraint
    $this->assertSame('elephant', $result->get(0)->getPropertyPath());
    $this->assertEquals("'elephant' is not a supported key.", $result->get(0)->getMessage());
    $this->assertSame('zebra', $result->get(1)->getPropertyPath());
    $this->assertEquals("'zebra' is not a supported key.", $result->get(1)->getMessage());
    // 1 additional constraint violation triggered by the custom
    // constraint for the `config_test.validation` type, which indirectly
    // extends `type: mapping` (via `type: config_object`).
    // @see \Drupal\config_test\ConfigValidation::validateMapping()
    $this->assertEquals('', $result->get(2)->getPropertyPath());
    $this->assertEquals('Unexpected keys: elephant, zebra', $result->get(2)->getMessage());
  }

}
