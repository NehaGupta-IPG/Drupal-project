<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Render\Element;

use Drupal\Core\Render\Element\IconAutocomplete;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the IconAutocomplete Form API element.
 *
 * @group Form
 * @group Icon
 */
#[Group('Form')]
#[Group('Icon')]
class IconAutocompleteTest extends KernelTestBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'icon_autocomplete_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['icon_basic'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Basic icon'),
    ];

    $form['icon_required'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Required icon'),
      '#required' => TRUE,
    ];

    $form['icon_with_default'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Icon with default'),
      '#default_value' => 'core:add',
    ];

    $form['icon_limited'] = [
      '#type' => 'icon_autocomplete',
      '#title' => $this->t('Limited icon'),
      '#allowed_icon_pack' => ['core', 'test'],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Not needed for this test.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Not needed for this test.
  }

  /**
   * Tests basic icon_autocomplete element rendering.
   */
  public function testIconAutocompleteBasicRender(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    // Check that the element is present.
    $this->assertArrayHasKey('icon_basic', $form);
    $this->assertEquals('icon_autocomplete', $form['icon_basic']['#type']);

    // Check autocomplete route is set.
    $this->assertEquals('system.icon_autocomplete', $form['icon_basic']['#autocomplete_route_name']);

    // Check default placeholder.
    $this->assertNotEmpty($form['icon_basic']['#placeholder']);

    // Check form classes are added.
    $this->assertContains('form-icon-autocomplete', $form['icon_basic']['#attributes']['class']);
    $this->assertContains('form-text', $form['icon_basic']['#attributes']['class']);
  }

  /**
   * Tests icon_autocomplete element with default value.
   */
  public function testIconAutocompleteWithDefaultValue(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $this->assertEquals('core:add', $form['icon_with_default']['#default_value']);
    $this->assertEquals('core:add', $form['icon_with_default']['#value']);
  }

  /**
   * Tests icon_autocomplete element with allowed icon packs.
   */
  public function testIconAutocompleteWithAllowedPacks(): void {
    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm($this, $form_state);

    $this->assertArrayHasKey('allowed_icon_pack', $form['icon_limited']['#autocomplete_query_parameters']);
    $this->assertEquals('core+test', $form['icon_limited']['#autocomplete_query_parameters']['allowed_icon_pack']);
  }

  /**
   * Tests icon_autocomplete element validation with valid value.
   */
  public function testIconAutocompleteValidationSuccess(): void {
    // Mock icon pack manager to return a valid icon.
    $icon_pack_manager = $this->getMockBuilder('Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $icon = $this->getMockBuilder('Drupal\Core\Theme\Icon\IconDefinitionInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $icon->method('getPackId')->willReturn('core');

    $icon_pack_manager->method('getIcon')
      ->with('core:add')
      ->willReturn($icon);

    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    $form_state = (new FormState())->setValues([
      'icon_basic' => 'core:add',
      'icon_required' => 'core:edit',
      'icon_with_default' => 'core:add',
      'icon_limited' => 'core:home',
    ]);

    \Drupal::formBuilder()->buildForm($this, $form_state);
    \Drupal::formBuilder()->submitForm($this, $form_state);

    // No errors should be present.
    $this->assertEmpty($form_state->getErrors());
  }

  /**
   * Tests icon_autocomplete element validation with empty required field.
   */
  public function testIconAutocompleteValidationRequiredEmpty(): void {
    $form_state = (new FormState())->setValues([
      'icon_basic' => '',
      'icon_required' => '',
      'icon_with_default' => 'core:add',
      'icon_limited' => '',
    ]);

    \Drupal::formBuilder()->buildForm($this, $form_state);
    \Drupal::formBuilder()->submitForm($this, $form_state);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertArrayHasKey('icon_required', $errors);
  }

  /**
   * Tests icon_autocomplete element validation with invalid format.
   */
  public function testIconAutocompleteValidationInvalidFormat(): void {
    $form_state = (new FormState())->setValues([
      'icon_basic' => 'invalid_format_no_separator',
      'icon_required' => 'core:add',
      'icon_with_default' => 'core:add',
      'icon_limited' => 'core:home',
    ]);

    \Drupal::formBuilder()->buildForm($this, $form_state);
    \Drupal::formBuilder()->submitForm($this, $form_state);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertArrayHasKey('icon_basic', $errors);
    $this->assertStringContainsString('pack_id:icon_id', (string) $errors['icon_basic']);
  }

  /**
   * Tests icon_autocomplete element validation with non-existent icon.
   */
  public function testIconAutocompleteValidationNonExistent(): void {
    // Mock icon pack manager to return NULL for non-existent icon.
    $icon_pack_manager = $this->getMockBuilder('Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $icon_pack_manager->method('getIcon')
      ->with('fake:nonexistent')
      ->willReturn(NULL);

    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    $form_state = (new FormState())->setValues([
      'icon_basic' => 'fake:nonexistent',
      'icon_required' => 'core:add',
      'icon_with_default' => 'core:add',
      'icon_limited' => 'core:home',
    ]);

    \Drupal::formBuilder()->buildForm($this, $form_state);
    \Drupal::formBuilder()->submitForm($this, $form_state);

    $errors = $form_state->getErrors();
    $this->assertNotEmpty($errors);
    $this->assertArrayHasKey('icon_basic', $errors);
    $this->assertStringContainsString('does not exist', (string) $errors['icon_basic']);
  }

  /**
   * Tests icon_autocomplete element validation with disallowed pack.
   */
  public function testIconAutocompleteValidationDisallowedPack(): void {
    // Mock icon pack manager.
    $icon_pack_manager = $this->getMockBuilder('Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $icon = $this->getMockBuilder('Drupal\Core\Theme\Icon\IconDefinitionInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $icon->method('getPackId')->willReturn('other');

    $icon_pack_manager->method('getIcon')
      ->with('other:icon')
      ->willReturn($icon);

    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    $form_state = (new FormState())->setValues([
      'icon_basic' => 'core:add',
      'icon_required' => 'core:add',
      'icon_with_default' => 'core:add',
      'icon_limited' => 'other:icon',
    ]);

    \Drupal::formBuilder()->submitForm($this, $form_state);

    $errors = $form_state->getErrors();
    /* @phpstan-ignore-next-line */
    $this->assertNotEmpty($errors);
    /* @phpstan-ignore-next-line */
    $this->assertArrayHasKey('icon_limited', $errors);
    /* @phpstan-ignore-next-line */
    $this->assertStringContainsString('not in the allowed list', (string) $errors['icon_limited']);
  }

  /**
   * Tests icon_autocomplete element with custom max_result.
   */
  public function testIconAutocompleteWithMaxResult(): void {
    $form = [
      'icon_custom' => [
        '#type' => 'icon_autocomplete',
        '#title' => $this->t('Custom max'),
        '#max_result' => 25,
      ],
    ];

    $form_state = new FormState();
    $form = \Drupal::service('form_builder')->doBuildForm('test_form', $form, $form_state);
    /* @phpstan-ignore-next-line */
    $this->assertEquals(25, $form['icon_custom']['#autocomplete_query_parameters']['max_result']);
  }

  /**
   * Tests valueCallback with various inputs.
   */
  public function testValueCallback(): void {
    $element = ['#type' => 'icon_autocomplete'];
    $form_state = new FormState();

    // Test with string input.
    $result = IconAutocomplete::valueCallback($element, 'core:add', $form_state);
    /* @phpstan-ignore-next-line */
    $this->assertEquals('core:add', $result);

    // Test with whitespace.
    $result = IconAutocomplete::valueCallback($element, '  core:add  ', $form_state);
    /* @phpstan-ignore-next-line */
    $this->assertEquals('core:add', $result);

    // Test with NULL.
    $result = IconAutocomplete::valueCallback($element, NULL, $form_state);
    /* @phpstan-ignore-next-line */
    $this->assertNull($result);

    // Test with FALSE.
    $result = IconAutocomplete::valueCallback($element, FALSE, $form_state);
    /* @phpstan-ignore-next-line */
    $this->assertNull($result);
  }

}
