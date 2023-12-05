<?php

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * @coversDefaultClass \Drupal\Core\Validation\Plugin\Validation\Constraint\StreamWrapperUriConstraintValidator
 * @group validation
 */
class StreamWrapperUriConstraintValidatorTest extends KernelTestBase {

  use StringTranslationTrait;

  /**
   * The typed data manager to use.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  private $typedData;

  /**
   * The data definition object to use.
   *
   * @var \Drupal\Core\TypedData\DataDefinition
   */
  private $definition;

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
  public function testValidate(mixed $value, bool $is_valid): void {
    $typed_data = $this->typedData->create($this->definition, $value);
    $violations = $typed_data->validate();
    $this->assertCount($is_valid ? 0 : 1, $violations, 'Validation failed for incorrect value.');
    if (!$is_valid) {
      $expected = (string) $this->t('"%value" is not a valid stream wrapper URI.', ['%value' => $value]);
      $this->assertSame($expected, (string) $violations->get(0)->getMessage(), 'Validation violation message was generated correctly for incorrect value.');
    }
  }

  public function provideTestValidate(): array {
    $data = [];
    $data[] = [FALSE, FALSE];
    $data[] = ['', FALSE];
    $data[] = [10, FALSE];
    $data[] = ['invalid-string', FALSE];
    $data[] = ['invalid-schema:', FALSE];
    $data[] = ['../relative/path', FALSE];
    $data[] = ['/absolute/path', FALSE];
    $data[] = ['https://www.example.com', FALSE];
    $data[] = ['invalid-schema://path', FALSE];
    $data[] = ['public://media-icons/generic', TRUE];
    return $data;
  }

}
