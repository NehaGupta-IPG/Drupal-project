<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Field\FieldType;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\Plugin\Field\FieldType\PasswordItem;
use Drupal\Core\Password\PasswordInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\Core\Field\Plugin\Field\FieldType\PasswordItem.
 */
#[CoversClass(PasswordItem::class)]
#[Group('Field')]
#[RunTestsInSeparateProcesses]
class PasswordItemTest extends FieldKernelTestBase {

  /**
   * A field storage to use in this test class.
   *
   * @var \Drupal\field\Entity\FieldStorageConfig
   */
  protected $fieldStorage;

  /**
   * The field used in this test class.
   *
   * @var \Drupal\field\Entity\FieldConfig
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => 'test_field',
      'entity_type' => 'entity_test',
      'type' => 'password',
    ]);
    $this->fieldStorage->save();

    $this->field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
      'required' => TRUE,
    ]);
    $this->field->save();
  }

  /**
   * Tests pre save pre hashed.
   */
  public function testPreSavePreHashed(): void {
    $entity = EntityTest::create([
      'name' => $this->randomString(),
    ]);
    $entity->test_field = 'this_is_not_a_real_hash';
    $entity->test_field->pre_hashed = TRUE;

    $entity->save();
    $this->assertSame('this_is_not_a_real_hash', $entity->test_field->value);
    $this->assertFalse($entity->test_field->pre_hashed);
  }

  /**
   * Tests pre save new null.
   */
  public function testPreSaveNewNull(): void {
    $entity = EntityTest::create([
      'name' => $this->randomString(),
    ]);
    $entity->test_field = NULL;

    $entity->save();
    $this->assertNull($entity->test_field->value);
  }

  /**
   * Tests pre save new empty string.
   */
  public function testPreSaveNewEmptyString(): void {
    $entity = EntityTest::create([
      'name' => $this->randomString(),
    ]);
    $entity->test_field = '';

    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Passwords cannot be empty.');
    $entity->save();
  }

  /**
   * Tests pre save new multiple spaces string.
   */
  public function testPreSaveNewMultipleSpacesString(): void {
    $entity = EntityTest::create([
      'name' => $this->randomString(),
    ]);
    $entity->test_field = '       ';

    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Passwords cannot be empty.');
    $entity->save();
  }

  /**
   * Tests pre save existing null.
   */
  public function testPreSaveExistingNull(): void {
    $entity = EntityTest::create();
    $entity->test_field = $this->randomString();
    $entity->save();

    $this->assertNotNull($entity->test_field->value);

    $entity->test_field = NULL;
    $entity->save();

    $this->assertNull($entity->test_field->value);
  }

  /**
   * Tests pre save existing empty string.
   */
  public function testPreSaveExistingEmptyString(): void {
    $entity = EntityTest::create();
    $entity->test_field = $this->randomString();
    $entity->save();

    $entity->test_field = '';
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Passwords cannot be empty.');
    $entity->save();
  }

  /**
   * Tests pre save existing multiple spaces string.
   */
  public function testPreSaveExistingMultipleSpacesString(): void {
    $entity = EntityTest::create();
    $entity->test_field = $this->randomString();
    $entity->save();

    $entity->test_field = '     ';
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Passwords cannot be empty.');
    $entity->save();
  }

  /**
   * Tests pre save exception new.
   */
  public function testPreSaveExceptionNew(): void {
    $entity = EntityTest::create();
    $entity->test_field = str_repeat('a', PasswordInterface::PASSWORD_MAX_LENGTH + 1);
    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Failed to hash the Test entity password.');
    $entity->save();
  }

  /**
   * Tests pre save exception existing.
   */
  public function testPreSaveExceptionExisting(): void {
    $entity = EntityTest::create();
    $entity->test_field = 'will_be_hashed';
    $entity->save();

    $this->assertNotEquals('will_be_hashed', $entity->test_field->value);

    $this->expectException(EntityStorageException::class);
    $this->expectExceptionMessage('Failed to hash the Test entity password.');
    $entity->test_field = str_repeat('a', PasswordInterface::PASSWORD_MAX_LENGTH + 1);
    $entity->save();
  }

}
