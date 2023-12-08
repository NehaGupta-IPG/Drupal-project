<?php

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

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
  public function testValidate(mixed $value, bool $is_valid, bool $throws_exception = FALSE): void {
    $typed_data = $this->typedData->create($this->definition, $value);
    if ($throws_exception) {
      $this->expectException(UnexpectedTypeException::class);
    }
    $violations = $typed_data->validate();
    $this->assertCount($is_valid ? 0 : 1, $violations, 'Validation failed for incorrect value.');
    if (!$is_valid) {
      $expected = sprintf('"%s" is not a valid stream wrapper URI.', $value);
      // Preprocess a bit the message in the violation, to strip tags, namely
      // the <em> added around the value, to avoid using translatable markup in
      // the test.
      $actual = strip_tags((string) $violations->get(0)->getMessage());
      $this->assertSame($expected, $actual);
    }
  }

  public function provideTestValidate(): array {
    $data = [];
    $data[] = [FALSE, FALSE, TRUE];
    $data[] = [10, FALSE, TRUE];
    $data[] = ['', FALSE];
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
