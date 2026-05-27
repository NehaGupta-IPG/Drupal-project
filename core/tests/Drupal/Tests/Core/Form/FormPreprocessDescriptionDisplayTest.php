<?php

namespace Drupal\Tests\Core\Form;

use Drupal\Core\Form\FormPreprocess;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Form\FormPreprocess
 * @group Form
 */
class FormPreprocessDescriptionDisplayTest extends UnitTestCase {

  /**
   * The FormPreprocess instance under test.
   *
   * @var \Drupal\Core\Form\FormPreprocess
   */
  protected FormPreprocess $formPreprocess;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->formPreprocess = new FormPreprocess();
  }

  /**
   * Tests that #description_display defaults to 'after' when not set.
   *
   * This covers the case where a form element is added directly to a render
   * array WITHOUT going through FormBuilder, meaning #description_display
   * is never explicitly set.
   *
   * @covers ::preprocessFormElement
   */
  public function testDescriptionDisplayDefaultsToAfterWhenNotSet(): void {
    $variables = [
      'element' => [
        '#type'        => 'textfield',
        '#title'       => 'Test field',
        '#description' => 'Some description',
        '#id'          => 'edit-test',
        '#children'    => '',
              // Intentionally NO #description_display set.
              // This simulates adding an element directly to a render array,
              // bypassing FormBuilder::doBuildForm().
      ],
    ];

    // Should not throw any PHP warning/notice.
    $this->formPreprocess->preprocessFormElement($variables);

    // Default must be 'after'.
    $this->assertSame(
          'after',
          $variables['description_display'],
          '#description_display should default to "after" when not explicitly set.'
      );

    // Description content must still be populated.
    $this->assertNotNull(
          $variables['description'],
          'Description should not be NULL when #description is set.'
      );
    $this->assertSame(
          'Some description',
          $variables['description']['content'],
          'Description content should match the #description value.'
      );
  }

  /**
   * Tests that an explicit #description_display value is respected.
   *
   * Ensures the fix does not override an intentionally set value.
   *
   * @covers ::preprocessFormElement
   * @dataProvider descriptionDisplayValuesProvider
   */
  public function testExplicitDescriptionDisplayIsRespected(string $display_value): void {
    $variables = [
      'element' => [
        '#type'                => 'textfield',
        '#title'               => 'Test field',
        '#description'         => 'Some description',
        '#description_display' => $display_value,
        '#id'                  => 'edit-test',
        '#children'            => '',
      ],
    ];

    $this->formPreprocess->preprocessFormElement($variables);

    $this->assertSame(
          $display_value,
          $variables['description_display'],
          "Explicit #description_display value '$display_value' should be preserved."
      );
  }

  /**
   * Data provider for explicit #description_display values.
   *
   * @return array
   */
  public static function descriptionDisplayValuesProvider(): array {
    return [
      'after'     => ['after'],
      'before'    => ['before'],
      'invisible' => ['invisible'],
    ];
  }

  /**
   * Tests that description_display is not set when #description is empty.
   *
   * @covers ::preprocessFormElement
   */
  public function testDescriptionDisplayNotSetWhenNoDescription(): void {
    $variables = [
      'element' => [
        '#type'     => 'textfield',
        '#title'    => 'Test field',
        '#id'       => 'edit-test',
        '#children' => '',
              // No #description at all.
      ],
    ];

    $this->formPreprocess->preprocessFormElement($variables);

    $this->assertNull(
          $variables['description'],
          'Description variable should be NULL when no #description is provided.'
      );
    $this->assertArrayNotHasKey(
          'description_display',
          $variables,
          'description_display should not be set when there is no #description.'
      );
  }

  /**
   * Tests fieldset #description_display defaults to 'after' when not set.
   *
   * @covers ::preprocessFieldset
   */
  public function testFieldsetDescriptionDisplayDefaultsToAfter(): void {
    $variables = [
      'element' => [
        '#type'        => 'fieldset',
        '#title'       => 'Test fieldset',
        '#description' => 'Fieldset description',
        '#attributes'  => ['id' => 'edit-fieldset'],
        '#children'    => '',
              // Intentionally NO #description_display set.
      ],
    ];

    $this->formPreprocess->preprocessFieldset($variables);

    $this->assertSame(
          'after',
          $variables['description_display'],
          'Fieldset #description_display should default to "after" when not set.'
      );
  }

  /**
   * Tests fieldset with 'invisible' #description_display adds visually-hidden.
   *
   * Ensures the visually-hidden class is applied when explicitly set,
   * and that the fix does not break this behaviour.
   *
   * @covers ::preprocessFieldset
   */
  public function testFieldsetInvisibleDescriptionDisplayAddsClass(): void {
    $variables = [
      'element' => [
        '#type'                => 'fieldset',
        '#title'               => 'Test fieldset',
        '#description'         => 'Fieldset description',
        '#description_display' => 'invisible',
        '#attributes'          => ['id' => 'edit-fieldset'],
        '#children'            => '',
      ],
    ];

    $this->formPreprocess->preprocessFieldset($variables);

    $this->assertSame('invisible', $variables['description_display']);

    // Drupal's Attribute object renders classes as a string like
    // 'class="visually-hidden"'. Convert to string and check it contains
    // the expected class.
    $attributes_string = (string) $variables['description']['attributes'];
    $this->assertStringContainsString(
          'visually-hidden',
          $attributes_string,
          'visually-hidden class must be present when description_display is invisible.'
      );
  }

}
