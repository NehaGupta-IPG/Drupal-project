<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\FunctionalString;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the creation of string fields.
 */
#[Group('text')]
#[RunTestsInSeparateProcesses]
class StringFieldTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'file'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user without any special permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->webUser = $this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'access content',
    ]);
    $this->drupalLogin($this->webUser);
  }

  // Test fields.

  /**
   * Tests widgets.
   */
  public function testTextfieldWidgets(): void {
    $this->_testTextfieldWidgets('string', 'string_textfield');
    $this->_testTextfieldWidgets('string', 'string_textarea');
    $this->_testTextfieldWidgets('string_long', 'string_textarea');
  }

  /**
   * Helper function for testTextfieldWidgets().
   */
  public function _testTextfieldWidgets($field_type, string $widget_type): void {
    // Create a field.
    $field_name = $this->randomMachineName();
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => $field_type,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => $this->randomMachineName() . '_label',
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($field_name, [
        'type' => $widget_type,
        'settings' => [
          'placeholder' => 'A placeholder on ' . $widget_type,
        ],
      ])
      ->save();
    $display_repository->getViewDisplay('entity_test', 'entity_test', 'full')
      ->setComponent($field_name)
      ->save();

    // Display creation form.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldValueEquals("{$field_name}[0][value]", '');
    $this->assertSession()->fieldNotExists("{$field_name}[0][format]");
    $this->assertSession()->responseContains('placeholder="A placeholder on ' . $widget_type . '"');
    // Verify string fields' maxlength setting is an attribute.
    if ($field_type == 'string') {
      $element = $this->assertSession()->elementExists('css', "[name='{$field_name}[0][value]']");
      $this->assertEquals(255, $element->getAttribute('maxlength'));
    }

    // Submit with some value.
    $value = $this->randomMachineName();
    $edit = [
      "{$field_name}[0][value]" => $value,
    ];
    $this->submitForm($edit, 'Save');
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertSession()->pageTextContains('entity_test ' . $id . ' has been created.');

    // Display the entity.
    $entity = EntityTest::load($id);
    $display = $display_repository->getViewDisplay($entity->getEntityTypeId(), $entity->bundle(), 'full');
    $content = $display->build($entity);
    $rendered_entity = \Drupal::service('renderer')->renderRoot($content);
    $this->assertStringContainsString($value, (string) $rendered_entity);
  }

  /**
   * Tests string field validation.
   */
  public function testStringFieldValidation(): void {
    // Create a field with settings to validate.
    $max_length = 3;
    $field_name = $this->randomMachineName();
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'entity_test',
      'type' => 'string',
      'settings' => [
        'max_length' => $max_length,
      ],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
    ])->save();

    // Test validation with valid and invalid values.
    $entity = EntityTest::create();
    for ($i = 0; $i <= $max_length + 2; $i++) {
      $entity->{$field_name}->value = str_repeat('x', $i);
      $violations = $entity->{$field_name}->validate();
      if ($i <= $max_length) {
        $this->assertCount(0, $violations, "Length $i does not cause validation error when max_length is $max_length");
      }
      else {
        $this->assertCount(1, $violations, "Length $i causes validation error when max_length is $max_length");
      }
    }
  }

}
