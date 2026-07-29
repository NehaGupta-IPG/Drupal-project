<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the UI for configuring entity displays.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class EntityDisplayFormBaseTest extends KernelTestBase {

  use UserCreationTrait {
    createUser as drupalCreateUser;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'field',
    'field_test',
    'field_ui',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['field', 'system', 'user']);

    \Drupal::service(ThemeInstallerInterface::class)->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    foreach (EntityTestHelper::getEntityTypes() as $entity_type) {
      $this->installEntitySchema($entity_type);

      // Auto-create fields for testing.
      FieldStorageConfig::create([
        'entity_type' => $entity_type,
        'field_name' => 'field_test_no_plugin',
        'type' => 'field_test',
        'cardinality' => 1,
      ])->save();
      FieldConfig::create([
        'entity_type' => $entity_type,
        'field_name' => 'field_test_no_plugin',
        'bundle' => $entity_type,
        'label' => 'Test field with no plugin',
        'translatable' => FALSE,
      ])->save();

      \Drupal::service('entity_display.repository')
        ->getFormDisplay($entity_type, $entity_type)
        ->setComponent('field_test_no_plugin', [
          'type' => 'test_field_widget',
        ])
        ->save();
    }

    $this->setCurrentUser($this->drupalCreateUser([
      'administer entity_test form display',
    ]));
  }

  /**
   * Ensures the entity is not affected when there are no applicable formatters.
   */
  public function testNoApplicableFormatters(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_form_display');
    $id = 'entity_test.entity_test.default';

    $entity_before = $storage->load($id);
    $this->drupalGet('entity_test/structure/entity_test/form-display');
    $entity_after = $storage->load($id);

    $this->assertSame($entity_before->toArray(), $entity_after->toArray());
  }

}
