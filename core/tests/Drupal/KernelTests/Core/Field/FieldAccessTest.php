<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Field;

use Drupal\Core\Access\AccessResult;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Field level access hooks.
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class FieldAccessTest extends KernelTestBase {

  /**
   * Modules to load code from.
   *
   * @var array
   */
  protected static $modules = [
    'entity_test',
    'field',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install field configuration.
    $this->installConfig(['field']);

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('entity_test_mul_langcode_key');
    $this->installEntitySchema('entity_test_mul_changed');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('entity_test_mulrev');
    $this->installEntitySchema('entity_test_mulrev_changed');

    // The users table is needed for creating dummy user accounts.
    $this->installEntitySchema('user');
    // Register entity_test text field.
    $this->container->get('module_handler')->loadInclude('entity_test', 'install');
    entity_test_install();
  }

  /**
   * Tests hook_entity_field_access() and hook_entity_field_access_alter().
   *
   * @see entity_test_entity_field_access()
   * @see entity_test_entity_field_access_alter()
   */
  public function testFieldAccess(): void {
    $values = [
      'name' => $this->randomMachineName(),
      'user_id' => 1,
      'field_test_text' => [
        'value' => 'no access value',
        'format' => 'full_html',
      ],
    ];
    $entity = EntityTest::create($values);

    // Create a dummy user account for testing access with.
    $values = ['name' => 'test'];
    $account = User::create($values);

    $this->assertFalse($entity->field_test_text->access('view', $account), 'Access to the field was denied.');
    $expected = AccessResult::forbidden()->addCacheableDependency($entity);
    $this->assertEquals($expected, $entity->field_test_text->access('view', $account, TRUE), 'Access to the field was denied.');

    $entity->field_test_text = 'access alter value';
    $this->assertFalse($entity->field_test_text->access('view', $account), 'Access to the field was denied.');
    $this->assertEquals($expected, $entity->field_test_text->access('view', $account, TRUE), 'Access to the field was denied.');

    $entity->field_test_text = 'standard value';
    $this->assertTrue($entity->field_test_text->access('view', $account), 'Access to the field was granted.');
    $this->assertEquals(AccessResult::allowed(), $entity->field_test_text->access('view', $account, TRUE), 'Access to the field was granted.');
  }

  /**
   * Tests that multiple hook_entity_field_access() implementations in the same module are respected.
   *
   * @see \Drupal\entity_test\Hook\EntityTestHooks::entityFieldAccess()
   * @see \Drupal\entity_test\Hook\EntityTestHooks::entityFieldAccessSecond()
   */
  public function testFieldAccessMultipleImplementationsSameModule(): void {
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test_text_2',
      'type' => 'text',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test_text_2',
      'bundle' => 'entity_test',
      'label' => 'Test text-field 2',
      'translatable' => FALSE,
    ])->save();

    \Drupal::state()->set('entity_test_field_access_multiple_hooks', TRUE);

    $values = [
      'name' => $this->randomMachineName(),
      'user_id' => 1,
      'field_test_text' => [
        'value' => 'multiple hooks value',
        'format' => 'full_html',
      ],
      'field_test_text_2' => [
        'value' => 'multiple hooks value',
        'format' => 'full_html',
      ],
    ];
    $entity = EntityTest::create($values);

    // Create a dummy user account for testing access with.
    $account = User::create($values);

    $this->assertFalse($entity->field_test_text->access('view', $account), 'Access to field_test_text was denied by the first hook implementation.');
    $this->assertFalse($entity->field_test_text_2->access('view', $account), 'Access to field_test_text_2 was denied by the second hook implementation.');
  }

}
