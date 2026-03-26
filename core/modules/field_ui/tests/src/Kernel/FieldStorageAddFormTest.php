<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\field_ui\Form\FieldStorageAddForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests field storage add form validation messaging.
 *
 * @group field_ui
 */
class FieldStorageAddFormTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_ui',
    'node',
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
    $this->installEntitySchema('node');
    $this->installConfig(['field', 'field_ui', 'node', 'system']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    $this->setCurrentUser($this->createUser([
      'administer content types',
      'administer node fields',
    ]));
  }

  /**
   * Tests message formatting when the label is provided.
   */
  public function testInvalidMachineNameMessageFormattingWithLabel(): void {
    $errors = $this->validateForm('Labelled field', 'invalid.name');

    $this->assertArrayHasKey('drupal-modal', $errors);
    $this->assertStringContainsString('There was a problem creating field Labelled field:', (string) $errors['drupal-modal']);
    $this->assertStringNotContainsString('There was a problem creating field Labelled field :', (string) $errors['drupal-modal']);
    $this->assertStringContainsString('field_invalid.name', (string) $errors['drupal-modal']);
    $this->assertStringContainsString('invalid characters', (string) $errors['drupal-modal']);
  }

  /**
   * Tests fallback message formatting when the label is empty.
   */
  public function testInvalidMachineNameMessageFormattingWithoutLabel(): void {
    $errors = $this->validateForm('', 'invalid.name');

    $this->assertArrayHasKey('label', $errors);
    $this->assertSame('Label field is required.', (string) $errors['label']);
    $this->assertArrayHasKey('drupal-modal', $errors);
    $this->assertStringContainsString('There was a problem creating field:', (string) $errors['drupal-modal']);
    $this->assertStringNotContainsString('There was a problem creating field :', (string) $errors['drupal-modal']);
    $this->assertStringContainsString('field_invalid.name', (string) $errors['drupal-modal']);
    $this->assertStringContainsString('invalid characters', (string) $errors['drupal-modal']);
  }

  /**
   * Builds and validates the add form for a given field label and machine name.
   *
   * @param string $label
   *   The field label to submit.
   * @param string $field_name
   *   The machine-readable field name to submit.
   *
   * @return array
   *   The collected form validation errors.
   */
  protected function validateForm(string $label, string $field_name): array {
    $form_state = new FormState();
    $form_state->setBuildInfo([
      'args' => ['node', 'article', 'string', 'false'],
    ]);

    $form_object = FieldStorageAddForm::create($this->container);
    $form = $this->container->get('form_builder')->buildForm($form_object, $form_state);

    $form_state->setValues([
      'label' => $label,
      'field_name' => $field_name,
      'field_options_wrapper' => 'string',
      'translatable' => FALSE,
    ]);

    $form_object->validateForm($form, $form_state);
    return $form_state->getErrors();
  }

}
