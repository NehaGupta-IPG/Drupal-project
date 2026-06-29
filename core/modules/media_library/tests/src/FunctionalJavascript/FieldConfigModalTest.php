<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests setting a media field default value from the field edit modal.
 *
 * @see \Drupal\media_library\MediaLibraryState::getDialogSelector()
 */
#[Group('media_library')]
#[RunTestsInSeparateProcesses]
class FieldConfigModalTest extends MediaLibraryTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createMediaItems([
      'type_one' => ['Horse', 'Bear'],
    ]);

    $user = $this->drupalCreateUser([
      'access content',
      'access administration pages',
      'administer node fields',
      'create basic_page content',
      'edit any basic_page content',
      'view media',
      'create media',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests inserting a default value into a media field from the edit modal.
   */
  public function testMediaDefaultValueInFieldEditModal(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Open the field settings in the modal field-edit flow.
    $this->drupalGet('admin/structure/types/manage/basic_page/fields');
    $this->assertNotEmpty($edit_link = $page->find('css', '[href$="node.basic_page.field_unlimited_media"]'));
    $edit_link->click();

    // The field-edit form renders inside '#drupal-modal'. The default value
    // widget is hidden until the "Set default value" checkbox is checked.
    $this->assertElementExistsAfterWait('css', '#drupal-modal [name="set_default_value"]')->check();
    $open_button = $assert_session->waitForElementVisible('css', '#drupal-modal .js-media-library-open-button');
    $this->assertNotEmpty($open_button);
    $open_button->press();

    // The media library opens in its own dedicated modal element, stacked on
    // top of the field-edit modal. Before this fix it reused '#drupal-modal'
    // and wiped the field-edit form.
    $this->assertElementExistsAfterWait('css', '#media-library-modal #media-library-wrapper');
    $this->waitForText('Add or select media');
    $assert_session->elementExists('css', '#drupal-modal');

    // Wait for the grid of media items to finish rendering before selecting.
    $this->assertMediaLibraryGrid();

    // Select the first media item by clicking it (the raw checkbox is visually
    // hidden) and remember its ID to assert on after insertion. Waiting for the
    // item to be visible also proves the stacked dialog rendered at full size.
    $first_item = $assert_session->waitForElementVisible('css', '.media-library-widget-modal .js-media-library-view .js-click-to-select');
    $this->assertNotEmpty($first_item);
    $media_id = $first_item->find('css', '.js-click-to-select-checkbox input')->getAttribute('value');
    $this->assertNotEmpty($media_id);
    $first_item->find('css', '.js-click-to-select-trigger')->click();

    // Insert the selection. The button lives in the media library modal, not
    // the field-edit modal, so scope to it.
    $assert_session->elementExists('css', '.media-library-widget-modal .ui-dialog-buttonpane')
      ->pressButton('Insert selected');

    // The media library modal closes, the field-edit modal survives, and the
    // selected media is inserted into the default value widget without error.
    // Before this fix, opening the library overwrote the field-edit modal, so
    // the insertion failed and an error appeared behind the modal.
    $assert_session->waitForElementRemoved('css', '#media-library-modal');
    $assert_session->elementExists('css', '#drupal-modal');
    $this->assertNotEmpty($assert_session->waitForElement('css', "#drupal-modal .js-media-library-item input[type='hidden'][value='$media_id']"));
    $assert_session->elementNotExists('css', '.messages--error');
  }

}
