<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\FunctionalJavascript;

use Drupal\Tests\media_library\FunctionalJavascript\MediaLibraryTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

// cspell:ignore vbo

/**
 * Tests the media library behaviors of the theme.
 *
 * Two code paths are covered. js/media-library.ui.js extends media_library/ui
 * and moves the selection counter to the front of the dialog button pane, and
 * migration/js/media_library.view.js, which default_admin.info.yml maps over
 * js/media_library.view.js in the media_library/view library, adds a "select
 * all" control to the media grid page and makes the bulk operations form
 * follow the selection.
 *
 * @see \Drupal\FunctionalJavascriptTests\Theme\AdminModalDisplayTest
 */
#[Group('default_admin')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
final class MediaLibraryTest extends MediaLibraryTestBase {

  /**
   * The path of the page display of the media_library view.
   *
   * @see core/modules/media_library/config/install/views.view.media_library.yml
   */
  private const GRID_PATH = 'admin/content/media-grid';

  /**
   * The number of media items listed by the media grid.
   */
  private const ITEM_COUNT = 3;

  /**
   * The selector of the view element the grid behavior is keyed on.
   *
   * @see \Drupal\media_library\Hook\MediaLibraryThemeHooks::preprocessViewsViewMediaLibrary()
   */
  private const VIEW = '.js-media-library-view[data-view-display-id="page"]';

  /**
   * The selector of the dialog button pane of the media library widget.
   *
   * @see \Drupal\media_library\MediaLibraryUiBuilder
   */
  private const BUTTON_PANE = '.media-library-widget-modal .ui-dialog-buttonpane';

  /**
   * The selector of the bulk operations form of the media grid.
   *
   * @see \Drupal\default_admin\Hook\FormHooks::formAlter()
   */
  private const BULK_FORM = '.media-library-views-form__bulk_form';

  /**
   * The selector of the element that is made sticky while items are selected.
   */
  private const STICKY_HEADER = '.media-library-view [data-drupal-selector*="edit-header"]';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMediaItems([
      'type_one' => ['Item one', 'Item two', 'Item three'],
    ]);

    $user = $this->drupalCreateUser([
      'access content',
      'access media overview',
      'create basic_page content',
      'create media',
      'delete any media',
      'update any media',
      'view media',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests that the selection counter leads the dialog button pane.
   *
   * The library of the media library module appends the counter to the end of
   * the button pane, so the theme either moves an already present counter or
   * waits for it with a mutation observer.
   *
   * @see core/themes/default_admin/js/media-library.ui.js
   * @see core/modules/media_library/js/media_library.ui.js
   */
  public function testSelectionCountLeadsButtonPane(): void {
    $this->drupalGet('node/add/basic_page');
    $this->openMediaLibraryForField('field_twin_media');

    $this->assertElementExistsAfterWait('css', self::BUTTON_PANE . ' .js-media-library-selected-count');

    // The counter ends up in front of everything else in the pane, which is
    // the only observable difference the override makes.
    $this->assertJsCondition('document.querySelector("' . self::BUTTON_PANE . '").firstElementChild.classList.contains("js-media-library-selected-count")');

    // Leading the pane is only a position if something follows the counter.
    $this->assertGreaterThan(
      1,
      $this->getSession()->evaluateScript('document.querySelector("' . self::BUTTON_PANE . '").children.length'),
      'The dialog button pane holds the buttons as well as the selection counter.'
    );
  }

  /**
   * Tests the select all control of the media grid.
   *
   * Checking the injected control selects every item, announces the new
   * selection and makes the bulk operations form sticky. All of that is driven
   * by the one click handler, so it is asserted as one sequence.
   *
   * @see core/themes/default_admin/migration/js/media_library.view.js
   */
  public function testSelectAllDrivesSelectionAndStickyBulkForm(): void {
    $assert_session = $this->assertSession();

    $this->drupalGet(self::GRID_PATH);

    // The behavior is keyed on the page display of the media_library view and
    // bails out when that display lists nothing.
    $assert_session->elementExists('css', self::VIEW);
    $assert_session->elementsCount('css', '.js-media-library-item', self::ITEM_COUNT);

    // The label is prepended to the views form, so it leads the form.
    $select_all = $this->assertElementExistsAfterWait('css', 'form.media-library-views-form label.media-library-select-all');
    $this->assertSame('Select all media', $select_all->getText());
    $this->assertJsCondition('document.querySelector("form.media-library-views-form").firstElementChild.classList.contains("media-library-select-all")');
    $checkbox = $assert_session->elementExists('css', 'label.media-library-select-all input[type="checkbox"]');

    $checkbox->click();

    // The handler writes the checked property of every item checkbox, which a
    // CSS locator compiled to XPath cannot see.
    $this->assertJsCondition('document.querySelectorAll(".js-media-library-item .form-boolean:checked").length === ' . self::ITEM_COUNT);
    $this->assertAnnounceContains('All ' . self::ITEM_COUNT . ' items selected');

    // A selection makes the bulk operations form stick to the viewport.
    $this->assertNotNull($assert_session->waitForElement('css', self::BULK_FORM . '[data-drupal-sticky-vbo="true"]'));
    $this->assertNotNull($assert_session->waitForElement('css', self::STICKY_HEADER . '.is-sticky'));

    $checkbox->click();

    $this->assertJsCondition('document.querySelectorAll(".js-media-library-item .form-boolean:checked").length === 0');
    $this->assertAnnounceContains('Zero items selected');

    // Clearing the selection switches both flags back.
    $this->assertNotNull($assert_session->waitForElement('css', self::BULK_FORM . '[data-drupal-sticky-vbo="false"]'));
    $assert_session->elementNotExists('css', self::STICKY_HEADER . '.is-sticky');
  }

  /**
   * Tests the media grid of a display that builds no views form.
   *
   * Views only builds a form when a field of the display provides one, and on
   * the page display only the bulk form does, so dropping that field leaves
   * the select all control with nothing to be prepended to. The guard on that
   * lookup is what keeps the rest of the behavior alive. Only the control is
   * left out, while the item handling bound after it still runs, where the
   * unguarded null reference used to skip it.
   *
   * @see core/themes/default_admin/migration/js/media_library.view.js
   * @see \Drupal\views\ViewExecutable::hasFormElements()
   * @see \Drupal\FunctionalJavascriptTests\WebDriverTestBase::failOnJavaScriptErrors()
   */
  public function testGridWithoutBulkFormOnlySkipsSelectAll(): void {
    $assert_session = $this->assertSession();

    $this->config('views.view.media_library')
      ->clear('display.page.display_options.fields.media_bulk_form')
      ->save();

    $this->drupalGet(self::GRID_PATH);

    // The view still matches the selector the behavior is keyed on, and the
    // key is written before the callback runs, so the callback was entered.
    $this->assertElementExistsAfterWait('css', self::VIEW . '[data-once~="media-library-select-all"]');

    // Items are listed, so the callback did not take its empty grid branch.
    $assert_session->elementsCount('css', '.js-media-library-item', self::ITEM_COUNT);

    // Without a views form there is nothing to prepend the control to, and
    // the field that is gone is the one that put a checkbox on every item,
    // so the selection has no element left to track either.
    $assert_session->elementNotExists('css', '.media-library-views-form');
    $assert_session->elementNotExists('css', '.media-library-select-all');
    $assert_session->elementNotExists('css', '.js-media-library-item .form-boolean');

    // The item handling is bound after the control, so it is what the null
    // reference used to take down with it. Everything it writes lives in the
    // form that is missing here, so a click has no outcome left to observe
    // and the JavaScript errors the base class collects are the assertion.
    $triggers = $this->getSession()->getPage()->findAll('css', '.js-media-library-item .js-click-to-select-trigger');
    $this->assertCount(self::ITEM_COUNT, $triggers);
    foreach ($triggers as $trigger) {
      $trigger->click();
    }

    // The grid is untouched by the clicks, so nothing broke halfway through.
    $assert_session->elementsCount('css', '.js-media-library-item', self::ITEM_COUNT);
    $assert_session->elementNotExists('css', '.media-library-select-all');
  }

}
