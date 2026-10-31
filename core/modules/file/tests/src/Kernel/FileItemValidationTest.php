<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that files referenced in file and image fields are always validated.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileItemValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'entity_test',
    'field',
    'user',
  ];

  /**
   * A user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');

    $this->user = User::create([
      'name' => 'username',
      'status' => 1,
    ]);
    $this->user->save();
    $this->container->get('current_user')->setAccount($this->user);
  }

  /**
   * Tests file validation constraint.
   *
   * @legacy-covers \Drupal\file\Plugin\Validation\Constraint\FileValidationConstraint
   * @legacy-covers \Drupal\file\Plugin\Validation\Constraint\FileValidationConstraintValidator
   */
  #[DataProvider('getFileTypes')]
  public function testFileValidationConstraint(string $file_type): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test_file',
      'entity_type' => 'entity_test',
      'type' => $file_type,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_test_file',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'max_filesize' => '2k',
        'file_extensions' => 'jpg',
      ],
    ]);
    $field->save();

    $image_uri = uniqid('public://') . '.png';
    $resolution = '1024x768';
    $image_uri = $this->getRandomGenerator()->image($image_uri, $resolution, $resolution);
    // The file needs to be bigger than 2 KB for the test to pass.
    $this->assertGreaterThan(2048, filesize($image_uri));

    // Test for max filesize.
    $file = File::create([
      'uri' => $image_uri,
      'uid' => $this->user->id(),
    ]);
    $file->setPermanent();
    $file->save();

    $entity_test = EntityTest::create([
      'uid' => $this->user->id(),
      'field_test_file' => [
        'target_id' => $file->id(),
      ],
    ]);

    // Enforce the file to be new as file size is checked only for new files.
    $entity_test->field_test_file->entity->enforceIsNew();
    $result = $entity_test->validate();
    $this->assertCount(3, $result);

    $this->assertEquals('field_test_file.0', $result->get(0)->getPropertyPath());
    $this->assertEquals('You do not have access to the referenced entity (<em class="placeholder">file</em>: <em class="placeholder">1</em>).', (string) $result->get(0)->getMessage());
    $this->assertEquals('field_test_file.0', $result->get(1)->getPropertyPath());
    $this->assertStringEndsWith('exceeding the maximum file size of <em class="placeholder">2 KB</em>.', (string) $result->get(1)->getMessage());
    $this->assertEquals('field_test_file.0', $result->get(2)->getPropertyPath());
    $this->assertEquals('Only files with the following extensions are allowed: <em class="placeholder">jpg</em>.', (string) $result->get(2)->getMessage());

    // File size is not checked for already existing files.
    $entity_test->field_test_file->entity->enforceIsNew(FALSE);
    $result = $entity_test->validate();
    $this->assertCount(1, $result);
    $this->assertEquals('field_test_file.0', $result->get(0)->getPropertyPath());
    $this->assertEquals('Only files with the following extensions are allowed: <em class="placeholder">jpg|png</em>.', (string) $result->get(0)->getMessage());

    // Refer to a file that does not exist.
    $entity_test = EntityTest::create([
      'uid' => $this->user->id(),
      'field_test_file' => [
        'target_id' => 2,
      ],
    ]);
    $result = $entity_test->validate();
    $this->assertCount(1, $result);
    $this->assertEquals('field_test_file.0.target_id', $result->get(0)->getPropertyPath());
    $this->assertEquals('The referenced entity (<em class="placeholder">file</em>: <em class="placeholder">2</em>) does not exist.', (string) $result->get(0)->getMessage());
  }

  /**
   * Provides a list of file types to test.
   */
  public static function getFileTypes(): array {
    return [['file'], ['image']];
  }

}
