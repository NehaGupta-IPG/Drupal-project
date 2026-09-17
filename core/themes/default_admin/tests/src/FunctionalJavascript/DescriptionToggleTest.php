<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form description toggle of the theme.
 *
 * Both halves of the feature are covered here, because the markup the theme
 * renders is only useful together with the behavior that wires it up: the
 * preprocess layer hides the description and prints a help icon button next to
 * the label, and migration/js/description_toggle.js labels that button and
 * toggles the description on click.
 *
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFormElement()
 * @see \Drupal\default_admin\Settings::get()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class DescriptionToggleTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;

  /**
   * The name of the field that carries the description under test.
   */
  private const FIELD_NAME = 'field_toggle_test';

  /**
   * The description of that field.
   */
  private const DESCRIPTION_TEXT = 'The description that the help icon toggles.';

  /**
   * The form item wrapping the field widget.
   *
   * The class is built from the '#name' of the widget element, which is
   * 'field_toggle_test[0][value]'.
   *
   * @see \Drupal\Core\Form\FormPreprocess::preprocessFormElement()
   */
  private const FORM_ITEM = '.form-item--field-toggle-test-0-value';

  /**
   * The description of that form item.
   *
   * The identifier is derived from the '#id' of the widget element.
   *
   * @see \Drupal\Core\Form\FormPreprocess::preprocessFormElement()
   */
  private const DESCRIPTION = '#edit-field-toggle-test-0-value--description';

  /**
   * The toggle button of that form item.
   *
   * @see core/themes/default_admin/templates/form/form-element.html.twig
   */
  private const TOGGLE = self::FORM_ITEM . ' .help-icon > button.help-icon__description-toggle';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The body field is not created, so that the single field added below is
    // the only element of the main form column with a description.
    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ], FALSE);

    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Toggle test',
      'description' => self::DESCRIPTION_TEXT,
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'page')
      ->setComponent(self::FIELD_NAME, ['type' => 'string_textfield'])
      ->save();

    $this->drupalLogin($this->drupalCreateUser(['create page content']));

    // The content form renders a meta sidebar, which is an overlay below
    // 1024px and a plain column above it. A fixed wide window keeps the main
    // form column clickable regardless of the default window size.
    $this->getSession()->resizeWindow(1600, 1200);
  }

  /**
   * Tests the markup of an enabled toggle and the attributes the behavior adds.
   */
  public function testDescriptionToggleEnabled(): void {
    $this->enableDescriptionToggle();
    $this->drupalGet('node/add/page');
    $assert_session = $this->assertSession();

    // node.add is a content form route, so the preprocess layer turns the form
    // item into a toggle container and the template prints the button next to
    // the label.
    // @see \Drupal\default_admin\Helper::isContentForm()
    $assert_session->elementExists('css', self::FORM_ITEM . '.help-icon__description-container');
    $assert_session->elementExists('css', self::TOGGLE);

    // preprocessDescriptionToggle() forces the description display to
    // 'invisible', which is what adds visually-hidden in the template.
    // @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDescriptionToggle()
    $description = $assert_session->elementExists('css', self::DESCRIPTION);
    $this->assertTrue($description->hasClass('visually-hidden'));

    // The behavior gives the button a generated identifier and marks it as
    // collapsed.
    $toggle = $assert_session->waitForElement('css', self::TOGGLE . '[id^="help-icon-label--"][aria-expanded="false"]');
    $this->assertNotNull($toggle);
    $id = (string) $toggle->getAttribute('id');
    $this->assertMatchesRegularExpression('/^help-icon-label--\d+$/', $id);

    // Every description of the container is pointed at that button, so that
    // the hidden text has an accessible name once it is revealed.
    $this->assertNotNull($assert_session->waitForElement('css', self::DESCRIPTION . '[aria-labelledby="' . $id . '"]'));
  }

  /**
   * Tests that clicking the toggle reveals and hides the description again.
   */
  public function testDescriptionToggleClick(): void {
    $this->enableDescriptionToggle();
    $this->drupalGet('node/add/page');
    $assert_session = $this->assertSession();

    // Wait for the behavior before clicking, because the button is rendered by
    // the template and only receives its state from the behavior.
    $this->assertNotNull($assert_session->waitForElement('css', self::TOGGLE . '[aria-expanded="false"]'));
    $toggle = $assert_session->elementExists('css', self::TOGGLE);

    // The first click reveals the description and flips all three attributes
    // the click handler maintains.
    $toggle->click();
    $this->assertNotNull($assert_session->waitForElement('css', self::TOGGLE . '[aria-expanded="true"]'));
    $this->assertNotNull($assert_session->waitForElement('css', self::DESCRIPTION . '[aria-hidden="false"]'));
    $this->assertFalse($assert_session->elementExists('css', self::DESCRIPTION)->hasClass('visually-hidden'));

    // Clicking it again reverses all three.
    $toggle->click();
    $this->assertNotNull($assert_session->waitForElement('css', self::TOGGLE . '[aria-expanded="false"]'));
    $this->assertNotNull($assert_session->waitForElement('css', self::DESCRIPTION . '[aria-hidden="true"]'));
    $this->assertTrue($assert_session->elementExists('css', self::DESCRIPTION)->hasClass('visually-hidden'));
  }

  /**
   * Tests that the very same form has no toggle while the setting is off.
   */
  public function testDescriptionToggleDisabled(): void {
    // False is the installed default of the setting.
    // @see core/themes/default_admin/config/install/default_admin.settings.yml
    $this->assertFalse($this->config('default_admin.settings')->get('show_description_toggle'));

    $this->drupalGet('node/add/page');
    $assert_session = $this->assertSession();

    // The library is attached by the template, next to the button, so without
    // the setting neither of them reaches the page. Waiting for the loaded
    // document also settles the page before the absences below are asserted.
    $this->assertJsCondition('document.readyState === "complete" && typeof Drupal !== "undefined" && typeof Drupal.behaviors.formDescriptionToggle === "undefined"');
    $assert_session->elementNotExists('css', '.help-icon__description-toggle');
    $assert_session->elementNotExists('css', '.help-icon__description-container');

    // The description is still rendered, and it is neither hidden nor labelled
    // by anything. Its text is readable, which an element carrying
    // visually-hidden would not be.
    $description = $assert_session->elementExists('css', self::DESCRIPTION);
    $this->assertFalse($description->hasClass('visually-hidden'));
    $this->assertFalse($description->hasAttribute('aria-labelledby'));
    $this->assertFalse($description->hasAttribute('aria-hidden'));
    $assert_session->elementTextContains('css', self::DESCRIPTION, self::DESCRIPTION_TEXT);
  }

  /**
   * Turns the description toggle on for the active theme.
   *
   * The theme settings are read through the theme settings provider, which
   * falls back to the default theme when no administration theme is
   * configured.
   *
   * @see \Drupal\default_admin\Settings::get()
   */
  private function enableDescriptionToggle(): void {
    $this->config('default_admin.settings')
      ->set('show_description_toggle', TRUE)
      ->save();
  }

}
