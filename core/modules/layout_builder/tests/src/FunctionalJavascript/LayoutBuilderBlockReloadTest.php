<?php

namespace Drupal\Tests\layout_builder\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\system\Traits\OffCanvasTestTrait;

/**
 * Tests the Layout Builder element block reloading.
 *
 * @group layout_builder
 */
class LayoutBuilderBlockReloadTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;
  use OffCanvasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'field_ui',
    'layout_builder',
    'layout_test',
    'node',
    'off_canvas_test',
    'layout_builder_block_random_uuid',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * The node to customize with Layout Builder.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
    BlockContent::create([
      'info' => 'My custom block',
      'type' => 'basic',
      'body' => [
        [
          'value' => 'This is the block content',
          'format' => filter_default_format(),
        ],
      ],
    ])->save();

    $this->createContentType(['type' => 'bundle_with_section_field']);
    $this->node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The node title',
      'body' => [
        [
          'value' => 'The node body',
        ],
      ],
    ]);

    $this->drupalLogin($this->drupalCreateUser([
      'access contextual links',
      'configure any layout',
      'administer node display',
    ], 'foobar'));

    $this->enableLayoutsForBundle('admin/structure/types/manage/bundle_with_section_field/display', TRUE);
  }

  public function testBlockOperations() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Go to node/1 layout URL.
    $this->drupalGet('node/1/layout');

    // Body block reference.
    $body_block = $page->find('css', '.block-field-blocknodebundle-with-section-fieldbody');
    // UUID of the body block for comparison later.
    $body_block_random_uuid = $body_block->getAttribute('data-layout-builder-block-random-uuid');

    // Add a new block and check that the random UUID has not changed for the
    // first block.
    $this->openAddBlockForm('Powered by Drupal');
    $page->pressButton('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('Powered by Drupal');

    // Powered by block reference.
    $powered_block = $page->find('css', '.block-system-powered-by-block');
    // UUID of the powered by block for comparison later.
    $powered_block_random_uuid = $powered_block->getAttribute('data-layout-builder-block-random-uuid');

    // Check that the body block random UUID has not changed.
    $check_body_block_random_uuid = $body_block->getAttribute('data-layout-builder-block-random-uuid');
    $this->assertEquals($body_block_random_uuid, $check_body_block_random_uuid);

    // Update the body block.
    $this->clickContextualLink('.block-field-blocknodebundle-with-section-fieldbody', 'Configure');
    $this->assertOffCanvasFormAfterWait('layout_builder_update_block');
    $page->fillField('settings[label]', 'Overridden body field label');
    $page->pressButton('Update');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas');

    // Check that the body block random UUID has changed.
    $check_body_block_random_uuid = $body_block->getAttribute('data-layout-builder-block-random-uuid');
    $this->assertNotEquals($body_block_random_uuid, $check_body_block_random_uuid);

    // Check that the powered by block random UUID has not changed.
    $check_powered_block_random_uuid = $powered_block->getAttribute('data-layout-builder-block-random-uuid');
    $this->assertEquals($powered_block_random_uuid, $check_powered_block_random_uuid);

    // Remove block.
    $this->clickContextualLink('.block-field-blocknodebundle-with-section-fieldbody', 'Remove block');
    $this->assertOffCanvasFormAfterWait('layout_builder_remove_block');
    $assert_session->pageTextContains('Are you sure you want to remove the Overridden body field label block?');
    $assert_session->pageTextContains('This action cannot be undone.');
    $page->pressButton('Remove');
    $assert_session->assertWaitOnAjaxRequest();

    // Check that the powered by block random UUID has not changed.
    $check_powered_block_random_uuid = $powered_block->getAttribute('data-layout-builder-block-random-uuid');
    $this->assertEquals($powered_block_random_uuid, $check_powered_block_random_uuid);
  }

  /**
   * Waits for the specified form and returns it when available and visible.
   *
   * @param string $expected_form_id
   *   The expected form ID.
   */
  private function assertOffCanvasFormAfterWait(string $expected_form_id): void {

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->waitForOffCanvasArea();
    $off_canvas = $this->assertSession()->elementExists('css', '#drupal-off-canvas');
    $this->assertNotNull($off_canvas);
    $form_id_element = $off_canvas->find('hidden_field_selector', ['hidden_field', 'form_id']);
    // Ensure the form ID has the correct value and that the form is visible.
    $this->assertNotEmpty($form_id_element);
    $this->assertSame($expected_form_id, $form_id_element->getValue());
    $this->assertTrue($form_id_element->getParent()->isVisible());
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove this in https://www.drupal.org/project/drupal/issues/2918718.
   */
  protected function clickContextualLink($selector, $link_locator, $force_visible = TRUE) {
    /** @var \Drupal\FunctionalJavascriptTests\JSWebAssert $assert_session */
    $assert_session = $this->assertSession();
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();
    $page->waitFor(10, function () use ($page, $selector) {
      return $page->find('css', "$selector .contextual-links");
    });
    if (count($page->findAll('css', "$selector .contextual-links")) > 1) {
      throw new \Exception('More than one contextual links found by selector');
    }

    if ($force_visible && $page->find('css', "$selector .contextual .trigger.visually-hidden")) {
      $this->toggleContextualTriggerVisibility($selector);
    }

    $link = $assert_session->elementExists('css', $selector)->findLink($link_locator);
    $this->assertNotEmpty($link);

    if (!$link->isVisible()) {
      $button = $assert_session->waitForElementVisible('css', "$selector .contextual button");
      $this->assertNotEmpty($button);
      $button->press();
      $link = $page->waitFor(10, function () use ($link) {
        return $link->isVisible() ? $link : FALSE;
      });
    }

    $link->click();

    if ($force_visible) {
      $this->toggleContextualTriggerVisibility($selector);
    }
  }

  /**
   * Enable layouts.
   *
   * @param string $path
   *   The path for the manage display page.
   * @param bool $allow_custom
   *   Whether to allow custom layouts.
   */
  private function enableLayoutsForBundle($path, $allow_custom = FALSE) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->drupalGet($path);
    $page->checkField('layout[enabled]');
    if ($allow_custom) {
      $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[name="layout[allow_custom]"]'));
      $page->checkField('layout[allow_custom]');
    }
    $page->pressButton('Save');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#edit-manage-layout'));
    $assert_session->linkExists('Manage layout');
  }

  /**
   * Opens the add block form in the off-canvas dialog.
   *
   * @param string $block_title
   *   The block title which will be the link text.
   */
  private function openAddBlockForm($block_title) {
    $assert_session = $this->assertSession();
    $assert_session->linkExists('Add block');
    $this->clickLink('Add block');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('named', ['link', $block_title]));
    $this->clickLink($block_title);
    $this->assertOffCanvasFormAfterWait('layout_builder_add_block');
  }

}
