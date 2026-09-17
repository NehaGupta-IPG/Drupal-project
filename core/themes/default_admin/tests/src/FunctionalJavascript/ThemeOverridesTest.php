<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Drupal.theme() overrides that exist to fit the theme's own CSS.
 *
 * Every function covered here replaces a markup snippet that core builds in
 * JavaScript with one that carries the class names the theme styles. Each test
 * asserts the theme's marker on the element the overridden function builds,
 * and where core's marker is a different class name, the absence of that one
 * as well: without the pair of assertions a lost override would silently fall
 * back to core markup that the theme does not style.
 *
 * Each override is reached through the library it extends, so the routes below
 * are chosen for rendering the element that triggers the function, not for the
 * override itself.
 *
 * @see core/themes/default_admin/default_admin.info.yml
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
final class ThemeOverridesTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * The text format that carries a CKEditor 5 editor with the media plugin.
   */
  private const TEXT_FORMAT = 'media_embed_test';

  /**
   * The wrapper of the password confirm widget.
   *
   * The theme's preprocess layer prints the initial state classes on this
   * element, and core's behavior reads it back with closest().
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFormElementPasswordConfirm()
   * @see core/modules/user/js/user.js
   */
  private const PASSWORD_WIDGET = '.js-form-type-password-confirm.js-form-item-pass';

  /**
   * The form item of the main password input.
   *
   * @see core/modules/user/js/user.js
   */
  private const PASSWORD_ITEM = '.js-form-item-pass-pass1';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views', 'ckeditor5', 'media'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Two nodes that the test user may both edit and delete, so that the
    // operations column of /admin/content holds more than one link and core's
    // dropbutton builds a toggle for the secondary ones.
    // @see core/misc/dropbutton/dropbutton.js
    $this->createContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);
    $this->createNode(['title' => 'Page one']);
    $this->createNode(['title' => 'Page two']);

    $this->createMediaEmbedTextFormat();

    // A single user for every route below. All assertions of this class read
    // markup that JavaScript builds, none of them change content or
    // configuration, so the permissions only have to open the routes.
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'access content overview',
      'edit any page content',
      'delete any page content',
      'create page content',
      'administer content types',
      'administer site configuration',
      'administer users',
      'use text format ' . self::TEXT_FORMAT,
    ]));

    // The content overview is a wide table and the content form renders its
    // meta sidebar as an overlay below 1024px. A fixed wide window keeps every
    // element under test on screen and clickable.
    $this->getSession()->resizeWindow(1600, 1200);
  }

  /**
   * Tests the checkbox the table select behavior builds for a table header.
   *
   * @see core/themes/default_admin/js/checkbox.js
   */
  public function testCheckbox(): void {
    $this->drupalGet('admin/content');
    $assert_session = $this->assertSession();

    // The select all checkbox only exists once the table select behavior has
    // prepended it to the header cell, and it can only carry all three classes
    // when it was built by the override: core's function sets form-checkbox
    // alone.
    // @see core/misc/tableselect.js
    // @see core/misc/checkbox.js
    $select_all = $assert_session->waitForElement('css', 'th.select-all > input.form-checkbox.form-boolean.form-boolean--type-checkbox');
    $this->assertNotNull($select_all);

    // The override builds a checkbox, and the table select behavior labels the
    // very element it received from the override.
    $this->assertSame('checkbox', $select_all->getAttribute('type'));
    $this->assertSame('Select all rows in this table', $select_all->getAttribute('title'));
  }

  /**
   * Tests the toggle the dropbutton behavior builds for secondary actions.
   *
   * @see core/themes/default_admin/js/dropbutton.js
   */
  public function testDropbuttonToggle(): void {
    $this->drupalGet('admin/content');
    $assert_session = $this->assertSession();

    // The list item is core's, the button inside it is the override's: core
    // renders a class-less button wrapping a span.dropbutton-arrow, the
    // override renders the toggle class on the button and drops the arrow.
    // @see core/misc/dropbutton/dropbutton.js
    $toggle = $assert_session->waitForElement('css', 'li.dropbutton-toggle > button.dropbutton__toggle');
    $this->assertNotNull($toggle);
    $assert_session->elementNotExists('css', 'span.dropbutton-arrow');

    // The accessible name of the toggle is a direct child of the button, where
    // core nests it inside the arrow span. It is read from the markup rather
    // than as rendered text, because the span is visually hidden.
    $label = $assert_session->elementExists('css', 'li.dropbutton-toggle > button.dropbutton__toggle > span.visually-hidden');
    $this->assertSame('List additional actions', $label->getHtml());
  }

  /**
   * Tests that opening a dropbutton pins its menu to the viewport.
   *
   * @see core/themes/default_admin/migration/js/dropbutton.js
   */
  public function testDropbuttonPosition(): void {
    $this->drupalGet('admin/content');
    $assert_session = $this->assertSession();

    // The behavior binds its listener to the toggle of every dropbutton that
    // core marked as having secondary actions, so its own once() marker on
    // that wrapper is what proves the listener is bound.
    // @see core/misc/dropbutton/dropbutton.js
    $dropbutton = $assert_session->waitForElement('css', '.dropbutton-multiple[data-once~="adminDropbutton"]');
    $this->assertNotNull($dropbutton);
    $menu = $assert_session->elementExists('css', '.dropbutton__items', $dropbutton);

    // The template renders the menu without any inline style, so everything
    // asserted below is written by the behavior.
    $this->assertStringNotContainsString('position', (string) $menu->getAttribute('style'));

    $assert_session->elementExists('css', '.dropbutton__toggle', $dropbutton)->click();
    $this->assertNotNull($assert_session->waitForElement('css', '.dropbutton__items[style*="position: fixed"]'));

    // The menu is taken out of the flow and offset from the top of the
    // viewport, measured from the rectangle of the secondary action.
    $style = (string) $menu->getAttribute('style');
    $this->assertMatchesRegularExpression('/position:\s*fixed/', $style);
    $this->assertMatchesRegularExpression('/top:\s*-?[\d.]+px/', $style);

    // Horizontally the behavior pins one edge to a pixel offset and releases
    // the other, depending on the space next to the dropbutton. Which of the
    // two branches applies is a property of the viewport, so this asserts that
    // exactly one edge is pinned rather than which one.
    $left_pinned = (bool) preg_match('/left:\s*-?[\d.]+px/', $style);
    $right_pinned = (bool) preg_match('/right:\s*-?[\d.]+px/', $style);
    $this->assertNotSame($left_pinned, $right_pinned, "Exactly one horizontal edge is pinned: $style");
    $this->assertMatchesRegularExpression('/(left|right):\s*auto/', $style);
  }

  /**
   * Tests the tab markup the vertical tabs behavior builds from a details.
   *
   * @see core/themes/default_admin/js/vertical-tabs.js
   */
  public function testVerticalTab(): void {
    $this->drupalGet('admin/structure/types/manage/page');
    $assert_session = $this->assertSession();

    // Core links the title directly from the anchor, the override wraps title
    // and summary in a content span below a classed anchor. The nesting can
    // only exist after the behavior built the tab with the override.
    $this->assertNotNull($assert_session->waitForElement('css', 'ul.vertical-tabs__menu a.vertical-tabs__menu-link > span.vertical-tabs__menu-link-content'));

    // Every details the behavior turned into a pane got a tab of that shape.
    $this->assertJsCondition('document.querySelectorAll("a.vertical-tabs__menu-link > span.vertical-tabs__menu-link-content").length === document.querySelectorAll("details.vertical-tabs__pane").length');

    // The title is a span holding the text of the details summary, where core
    // renders a strong element, and the summary span is a sibling of it inside
    // the content span.
    $title = $assert_session->elementExists('css', '.vertical-tabs__menu-link-content > span.vertical-tabs__menu-item-title');
    $this->assertSame('Submission form settings', $title->getText());
    $assert_session->elementExists('css', '.vertical-tabs__menu-link-content > span.vertical-tabs__menu-link-summary');
    $assert_session->elementNotExists('css', 'strong.vertical-tabs__menu-item-title');
    $assert_session->elementNotExists('css', '.vertical-tabs__menu-item-summary');
  }

  /**
   * Tests the wrapper the details behavior appends to a summary.
   *
   * The file also carries Drupal.behaviors.adminDetails, a workaround that
   * focuses a clicked summary because Firefox only does so for keyboard
   * navigation. That half is not asserted here: the driver runs Chromium,
   * which focuses a summary on click by itself, so the assertion would hold
   * with the behavior removed.
   *
   * @see core/themes/default_admin/js/details.js
   */
  public function testDetailsSummarizedContentWrapper(): void {
    $this->drupalGet('admin/config/development/performance');
    $assert_session = $this->assertSession();

    // The summarized content behavior appends the wrapper to the summary of
    // every details of the page. Core names that element span.summary, the
    // override names it after the summary block of the theme's CSS.
    // @see core/misc/details-summarized-content.js
    $this->assertNotNull($assert_session->waitForElement('css', 'details#edit-caching > summary.details__summary > span.details__summary-summary'));
    $assert_session->elementNotExists('css', 'summary > span.summary');

    // Both details of the form received one, which is what makes the absence
    // above an override and not an unprocessed page.
    $this->assertJsCondition('document.querySelectorAll("details > summary > span.details__summary-summary").length === document.querySelectorAll("details").length');
  }

  /**
   * Tests the password widget state classes and the strength meter markup.
   *
   * @see core/themes/default_admin/js/user.theme.js
   */
  public function testPasswordConfirmWidget(): void {
    $this->drupalGet('admin/people/create');
    $assert_session = $this->assertSession();

    // The password behavior adds this class to the parent of the main input,
    // so it marks the point from which the widget is live.
    // @see core/modules/user/js/user.js
    $this->assertNotNull($assert_session->waitForElement('css', self::PASSWORD_ITEM . '.password-parent'));

    // The strength meter is built by the override: core names the two elements
    // password-strength__meter and password-strength__indicator.
    $assert_session->elementExists('css', self::PASSWORD_ITEM . ' .password-strength > .password-strength__track > .password-strength__bar');
    $assert_session->elementNotExists('css', '.password-strength__meter');
    $assert_session->elementNotExists('css', '.password-strength__indicator');

    // Core leaves the widget state classes empty, so these three are rendered
    // by the theme's preprocess layer and are only maintained afterwards
    // because the override filled the class map.
    $widget = $assert_session->elementExists('css', self::PASSWORD_WIDGET);
    $this->assertTrue($widget->hasClass('is-initial'));
    $this->assertTrue($widget->hasClass('is-password-empty'));
    $this->assertTrue($widget->hasClass('is-confirm-empty'));

    // Typing a password replaces the initial and empty state with the filled
    // one, and leaves the untouched confirm input marked as empty.
    $widget->fillField('Password', 'a');
    $this->assertNotNull($assert_session->waitForElement('css', self::PASSWORD_WIDGET . '.is-password-filled'));
    $this->assertFalse($widget->hasClass('is-initial'));
    $this->assertFalse($widget->hasClass('is-password-empty'));
    $this->assertTrue($widget->hasClass('is-confirm-empty'));

    // The strength text is written into the span of the override.
    $this->assertNotNull($assert_session->waitForElement('css', self::PASSWORD_ITEM . ' .password-strength__title > .password-strength__text:contains("Weak")'));
  }

  /**
   * Tests that dismissing a status message hides it.
   *
   * @see core/themes/default_admin/migration/js/messages.js
   */
  public function testMessageDismiss(): void {
    $this->drupalGet('admin/config/development/performance');
    $this->submitForm([], 'Save configuration');
    $assert_session = $this->assertSession();

    // The button is rendered by the theme's template, the behavior only binds
    // to it, so the once() marker is what proves the handler is attached.
    // @see core/themes/default_admin/templates/misc/status-messages.html.twig
    $dismiss = $assert_session->waitForElement('css', '.messages-list__item.messages--status > .js-message-button-hide[data-once~="messages-dismiss"]');
    $this->assertNotNull($dismiss);

    $message = $assert_session->elementExists('css', '.messages-list__item.messages--status');
    $this->assertStringNotContainsString('opacity', (string) $message->getAttribute('style'));
    $this->assertFalse($message->hasClass('visually-hidden'));

    // The handler hides the message the button belongs to, by an inline
    // opacity for the transition and the class for assistive technology.
    $dismiss->click();
    $this->assertNotNull($assert_session->waitForElement('css', '.messages-list__item.messages--status.visually-hidden'));
    $this->assertStringContainsString('opacity: 0', (string) $message->getAttribute('style'));
  }

  /**
   * Tests the error markup of a failed media embed preview.
   *
   * @see core/themes/default_admin/migration/js/media_embed_ckeditor.theme.js
   */
  public function testMediaEmbedPreviewError(): void {
    $this->drupalGet('node/add/page');
    $assert_session = $this->assertSession();

    // The override extends a library of the media module that only reaches a
    // page through the CKEditor 5 media plugin, which the text editor element
    // attaches for every text format available on the body field. The element
    // marks the format select, or a hidden input when only one format is
    // available, with the field it drives, so this is the server side proof
    // that those attachments were built.
    // @see core/modules/editor/src/Element.php
    // @see core/modules/ckeditor5/ckeditor5.ckeditor5.yml
    $assert_session->elementExists('css', '[data-editor-for]');
    $this->assertJsCondition('typeof Drupal.theme.mediaEmbedPreviewError === "function"');

    // The plugin renders the return value of this function into the editor
    // when a preview request fails. Core returns a bare div, the override adds
    // the classes of the theme's error component.
    $this->assertSame(
      '<div class="media-embed-error media-embed-error--preview-error">An error occurred while trying to preview the media. You should save your work and reload this page.</div>',
      $this->getSession()->evaluateScript("Drupal.theme('mediaEmbedPreviewError')"),
    );
  }

  /**
   * Creates a text format whose editor enables the CKEditor 5 media plugin.
   *
   * The plugin is enabled by the media embed filter alone, and its library is
   * the one the theme extends with the media embed override. The format sorts
   * before the fallback format, so it is the default format of a body field.
   *
   * @see core/modules/ckeditor5/ckeditor5.ckeditor5.yml
   */
  private function createMediaEmbedTextFormat(): void {
    FilterFormat::create([
      'format' => self::TEXT_FORMAT,
      'name' => 'Media embed test format',
      'weight' => 0,
      'filters' => [
        'media_embed' => ['status' => TRUE],
      ],
    ])->save();

    Editor::create([
      'editor' => 'ckeditor5',
      'format' => self::TEXT_FORMAT,
      'settings' => [
        'toolbar' => [
          'items' => ['bold', 'italic'],
        ],
        'plugins' => [
          'media_media' => ['allow_view_mode_override' => FALSE],
        ],
      ],
      'image_upload' => ['status' => FALSE],
    ])->save();
  }

}
