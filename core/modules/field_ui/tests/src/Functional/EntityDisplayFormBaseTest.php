<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_test\EntityTestHelper;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_ui\Form\EntityFormDisplayEditForm;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the UI for configuring entity displays.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class EntityDisplayFormBaseTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui', 'entity_test', 'field_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    foreach (EntityTestHelper::getEntityTypes() as $entity_type) {
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

      FieldStorageConfig::create([
        'entity_type' => $entity_type,
        'field_name' => 'field_test_partial',
        'type' => 'field_test',
        'cardinality' => 1,
      ])->save();
      FieldConfig::create([
        'entity_type' => $entity_type,
        'field_name' => 'field_test_partial',
        'bundle' => $entity_type,
        'label' => 'Test partial field',
        'translatable' => FALSE,
      ])->save();

      \Drupal::service('entity_display.repository')
        ->getFormDisplay($entity_type, $entity_type)
        ->setComponent('field_test_no_plugin', [
          'type' => 'test_field_widget',
        ])
        ->setComponent('field_test_partial', [
          'type' => 'test_field_widget',
          'weight' => 20,
        ])
        ->save();
    }

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test form display',
    ]));
  }

  /**
   * Tests copying partial form values to an entity display.
   */
  public function testCopyPartialFormValuesToEntity(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_form_display');
    $display = $storage->load('entity_test.entity_test.default');

    $display->setComponent('extra_test', [
      'weight' => 30,
      'region' => 'content',
    ]);

    $before_partial = $display->getComponent('field_test_partial');
    $before_extra = $display->getComponent('extra_test');

    $form = [
      '#fields' => [
        'field_test_no_plugin',
        'field_test_partial',
      ],
      '#extra' => [
        'extra_test',
      ],
    ];

    $form_state = new FormState();
    $form_state->setValues([
      'fields' => [
        'field_test_no_plugin' => [
          'type' => 'test_field_widget',
          'weight' => 10,
          'region' => 'content',
        ],
      ],
    ]);

    $form_object = TestEntityFormDisplayEditForm::create($this->container);
    $form_object->setDisplayEntity($display);
    $form_object->copyValuesToEntity($display, $form, $form_state);

    $this->assertSame(10, $display->getComponent('field_test_no_plugin')['weight']);
    $this->assertSame($before_partial, $display->getComponent('field_test_partial'));
    $this->assertSame($before_extra, $display->getComponent('extra_test'));
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

/**
 * Test implementation of EntityFormDisplayEditForm.
 */
class TestEntityFormDisplayEditForm extends EntityFormDisplayEditForm {

  /**
   * Sets the entity display being edited.
   */
  public function setDisplayEntity(EntityInterface $entity): void {
    $this->entity = $entity;
  }

  /**
   * Exposes copyFormValuesToEntity() for testing.
   */
  public function copyValuesToEntity(
    EntityInterface $entity,
    array $form,
    FormStateInterface $form_state,
  ): void {
    $this->copyFormValuesToEntity($entity, $form, $form_state);
  }

}
