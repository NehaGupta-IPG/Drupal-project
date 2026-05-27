<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Form;

use Drupal\Core\Form\FormPreprocess;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests default handling for the description display form property.
 */
#[CoversClass(FormPreprocess::class)]
#[Group('form')]
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
   * array without going through FormBuilder, meaning #description_display
   * is never explicitly set.
   */
  public function testDescriptionDisplayDefaultsToAfterWhenNotSet(): void {
    $variables = [
      'element' => [
        '#type' => 'textfield',
        '#title' => 'Test field',
        '#description' => 'Some description',
        '#id' => 'edit-test',
        '#children' => '',
        // Intentionally no #description_display set.
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
   * Ensures the fix does not accidentally override an intentionally set value.
   */
  #[DataProvider('descriptionDisplayValuesProvider')]
  public function testExplicitDescriptionDisplayIsRespected(string $display_value): void {
    $variables = [
      'element' => [
        '#type' => 'textfield',
        '#title' => 'Test field',
        '#description' => 'Some description',
        '#description_display' => $display_value,
        '#id' => 'edit-test',
        '#children' => '',
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
   * @return array<string, array<string>>
   *   An array of display value test cases.
   */
  public static function descriptionDisplayValuesProvider(): array {
    return [
      'after' => ['after'],
      'before' => ['before'],
      'invisible' => ['invisible'],
    ];
  }

  /**
   * Tests that description_display is not set when #description is empty.
   */
  public function testDescriptionDisplayNotSetWhenNoDescription(): void {
    $variables = [
      'element' => [
        '#type' => 'textfield',
        '#title' => 'Test field',
        '#id' => 'edit-test',
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
   */
  public function testFieldsetDescriptionDisplayDefaultsToAfter(): void {
    $variables = [
      'element' => [
        '#type' => 'fieldset',
        '#title' => 'Test fieldset',
        '#description' => 'Fieldset description',
        '#attributes' => ['id' => 'edit-fieldset'],
        '#children' => '',
        // Intentionally no #description_display set.
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
   * and that the fix does not break this behavior.
   */
  public function testFieldsetInvisibleDescriptionDisplayAddsClass(): void {
    $variables = [
      'element' => [
        '#type' => 'fieldset',
        '#title' => 'Test fieldset',
        '#description' => 'Fieldset description',
        '#description_display' => 'invisible',
        '#attributes' => ['id' => 'edit-fieldset'],
        '#children' => '',
      ],
    ];

    $this->formPreprocess->preprocessFieldset($variables);

    $this->assertSame('invisible', $variables['description_display']);

    // Cast Attribute object to string to check for the visually-hidden class.
    $attributes_string = (string) $variables['description']['attributes'];
    $this->assertStringContainsString(
      'visually-hidden',
      $attributes_string,
      'visually-hidden class must be present when description_display is invisible.'
    );
  }

}
