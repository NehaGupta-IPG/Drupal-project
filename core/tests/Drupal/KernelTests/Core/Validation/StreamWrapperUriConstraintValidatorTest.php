<?php

namespace Drupal\KernelTests\Core\Validation;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
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
  protected static $modules = ['file_test'];

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
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

  /**
   * @covers ::validate
   *
   * @dataProvider provideTestValidate
   */
  public function testValidate(mixed $value, string $error_type = 'none', ?array $constraint_options = NULL, ?string $invalid_schema = NULL): void {
    $definition = empty($constraint_options) ?
      $this->definition :
      DataDefinition::create('string')
        ->addConstraint('StreamWrapperUri', $constraint_options);
    $typed_data = $this->typedData->create($definition, $value);
    $violations_count = ($error_type == 'none') ? 0 : 1;
    if ($error_type == 'type') {
      $this->expectException(UnexpectedTypeException::class);
    }
    $expected_message = sprintf('"%s" is not a valid stream wrapper URI.', $value);
    if ($error_type == 'schema') {
      $expected_message = sprintf('"%s" stream wrapper is not allowed to be used.', $invalid_schema);
    }
    $violations = $typed_data->validate();
    $this->assertCount($violations_count, $violations);
    if ($violations_count > 0) {
      // Preprocess a bit the message in the violation, to strip tags, namely
      // the <em> added around the placeholder, to avoid using translatable
      // markup in the test.
      $actual = strip_tags((string) $violations->get(0)->getMessage());
      $this->assertSame($expected_message, $actual);
    }
  }

  /**
   * Data provider for testValidate().
   */
  public function provideTestValidate(): array {
    $data = [];
    // Not a string.
    $data[] = [FALSE, 'type'];
    $data[] = [10, 'type'];
    // String, but invalid uri.
    $data[] = ['', 'uri'];
    $data[] = ['invalid-string', 'uri'];
    $data[] = ['invalid-schema:', 'uri'];
    $data[] = ['../relative/path', 'uri'];
    $data[] = ['/absolute/path', 'uri'];
    $data[] = ['https://www.example.com', 'uri'];
    $data[] = ['invalid-schema://path', 'uri'];
    // Valid schema, with default constraint options.
    $data[] = ['assets://media-icons/generic'];
    $data[] = ['dummy-external-readonly://media-icons/generic'];
    $data[] = ['dummy-readonly://media-icons/generic'];
    $data[] = ['dummy-remote://media-icons/generic'];
    $data[] = ['dummy://media-icons/generic'];
    $data[] = ['private://media-icons/generic'];
    $data[] = ['public://media-icons/generic'];
    $data[] = ['temporary://media-icons/generic'];
    // Valid schema, but not requested set of stream wrappers.
    $data[] = ['dummy-external-readonly://media-icons/generic', 'schema', ['filter' => StreamWrapperInterface::WRITE], 'dummy-external-readonly'];
    $data[] = ['dummy-readonly://media-icons/generic', 'schema', ['filter' => StreamWrapperInterface::WRITE], 'dummy-readonly'];
    // Valid schema, with requested set of stream wrappers.
    $data[] = ['dummy-external-readonly://media-icons/generic', 'none', ['filter' => StreamWrapperInterface::READ_VISIBLE]];
    $data[] = ['dummy-readonly://media-icons/generic', 'none', ['filter' => StreamWrapperInterface::READ]];
    $data[] = ['public://media-icons/generic', 'none', ['filter' => StreamWrapperInterface::LOCAL_NORMAL]];
    return $data;
  }

}
