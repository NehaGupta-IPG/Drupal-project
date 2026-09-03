<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\Timestamp;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings form of the timestamp field formatter.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class TimestampFormatterSettingsTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'field', 'entity_test', 'user'];

  /**
   * The field name used in this test.
   */
  protected const FIELD_NAME = 'field_timestamp';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system', 'field']);
    $this->installEntitySchema('entity_test');

    FieldStorageConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'entity_test',
      'type' => 'timestamp',
    ])->save();
    FieldConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'timestamp_formatter_settings_test';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $formatter = \Drupal::service('plugin.manager.field.formatter')->createInstance('timestamp', [
      'field_definition' => FieldConfig::loadByName('entity_test', 'entity_test', static::FIELD_NAME),
      'settings' => [],
      'label' => 'hidden',
      'view_mode' => 'default',
      'third_party_settings' => [],
    ]);

    $form['settings'] = $formatter->settingsForm([], $form_state);
    $form['submit'] = ['#type' => 'submit', '#value' => 'Save'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Tests that submitting the form yields settings the config schema accepts.
   */
  public function testSubmittedSettings(): void {
    $form_state = new FormState();
    $form_state->setUserInput(['op' => 'Save']);
    $form_state->setProgrammed()->setSubmitted();
    \Drupal::formBuilder()->submitForm($this, $form_state);
    $settings = $form_state->getValues();

    // The 'description' element is display only and has no config schema.
    $this->assertArrayNotHasKey('description', $settings['time_diff']);

    // Saving the display validates the settings against the config schema.
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test')
      ->setComponent(static::FIELD_NAME, ['type' => 'timestamp', 'settings' => $settings]);
    $display->save();

    $saved_settings = $display->getComponent(static::FIELD_NAME)['settings'];
    $this->assertArrayNotHasKey('description', $saved_settings['time_diff']);
  }

}
