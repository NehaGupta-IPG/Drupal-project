<?php

namespace Drupal\Tests\media_library\FunctionalJavascript;

/**
 * Tests Media Library's select all javascript.
 *
 * @group media_library
 */
class MediaLibrarySelectAllTest extends MediaLibraryTestBase {

  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a few example media items for use in selection.
    $this->createMediaItems([
      'type_one' => array_map(fn () => $this->randomMachineName(), range(0, 24)),
    ]);

    $account = $this->drupalCreateUser(['access media overview']);
    $this->drupalLogin($account);
  }

  /**
   * Tests that the 'Select all media' checkbox works correctly.
   */
  public function testSelectAll(): void {
    $page = $this->getSession()->getPage();

    // Assert that there are 24 items on the first page.
    $this->drupalGet('admin/content/media-grid');
    $this->waitForElementsCount('css', '.js-media-library-item', 24);

    // Assert that the 'Select all media' checkbox exists on the first page.
    $this->assertSession()->fieldExists('Select all media');

    $page->checkField('Select all media');
    $items = $page->findAll('css', '.js-media-library-item');
    /** @var \Behat\Mink\Element\NodeElement $item */
    foreach ($items as $item) {
      $this->assertTrue($item->find('css', 'input[type="checkbox"]')->isChecked());
    }

    // Assert that we are on the second page and that there is only 1 item.
    $this->assertSession()->linkExists('Next page');
    $page->clickLink('Next page');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->waitForElementsCount('css', '.js-media-library-item', 1);

    // Assert that the 'Select all media' checkbox exists on the second page.
    $this->assertSession()->fieldExists('Select all media');
  }

}
