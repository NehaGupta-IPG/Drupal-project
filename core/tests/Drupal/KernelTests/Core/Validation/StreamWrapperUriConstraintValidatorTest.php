<?php

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Validation\Plugin\Validation\Constraint\StreamWrapperUriConstraintValidator
 * @group validation
 */
class StreamWrapperUriConstraintValidatorTest extends KernelTestBase {

  /**
   * The typed data manager to use.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedData;

  /**
   * The data definiton object to use.
   *
   * @var \Drupal\Core\TypedData\DataDefinition
   */
  protected $definition;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->typedData = $this->container->get(TypedDataManagerInterface::class);
    $this->definition = DataDefinition::create('string')
      ->addConstraint('StreamWrapperUri');
  }

  /**
   * @covers ::validate
   *
   * @dataProvider provideTestValidate
   */
  public function testValidate($value, $is_valid) {
    $typed_data = $this->typedData->create($this->definition, $value);
    $violations = $typed_data->validate();
    $this->assertEquals($is_valid ? 0 : 1, $violations->count(), 'Validation failed for incorrect value.');
    if (!$is_valid) {
      $expected = sprintf('"%s" is not a valid stream wrapper URI.', $value);
      $actual = $violations->get(0)->getMessage();
      $this->assertEquals($expected, $actual, 'Validation violation message was generated correctly for incorrect value.');
    }
  }

  public function provideTestValidate() {
    $data = [];
    $data[] = [FALSE, FALSE];
    $data[] = ['', FALSE];
    $data[] = [10, FALSE];
    $data[] = ['invalid-string', FALSE];
    $data[] = ['invalid-schema:', FALSE];
    $data[] = ['../relative/path', FALSE];
    $data[] = ['/absolute/path', FALSE];
    $data[] = ['https://www.example.com', FALSE];
    $data[] = ['invalidschema://path', FALSE];
    $data[] = ['public://media-icons/generic', TRUE];
    return $data;
  }

}
