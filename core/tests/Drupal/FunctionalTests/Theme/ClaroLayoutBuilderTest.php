<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Theme;

use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the layout builder with the Claro theme.
 */
#[Group('claro')]
#[RunTestsInSeparateProcesses]
class ClaroLayoutBuilderTest extends BrowserTestBase {
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'layout_builder',
    'layout_builder_views_test',
    'layout_test',
    'field_ui',
    'block',
    'block_test',
    'node',
    'layout_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'header']);

    // Create two nodes.
    $this->createContentType([
      'type' => 'bundle_with_section_field',
      'name' => 'Bundle with section field',
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
      'body' => [
        [
          'value' => 'The first node body',
        ],
      ],
    ]);
    $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The second node title',
      'body' => [
        [
          'value' => 'The second node body',
        ],
      ],
    ]);
  }

  /**
   * Tests the layout builder has expected contextual links with Claro.
   *
   * @see claro.theme
   */
  public function testContextualLinks(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer node display',
      'administer node fields',
      'access contextual links',
    ]));

    $field_ui_prefix = 'admin/structure/types/manage/bundle_with_section_field';

    // From the manage display page, go to manage the layout.
    $this->drupalGet("$field_ui_prefix/display/default");
    $assert_session->linkNotExists('Manage layout');
    $assert_session->fieldDisabled('layout[allow_custom]');

    $this->submitForm(['layout[enabled]' => TRUE], 'Save');
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');

    // Add a new block.
    $assert_session->linkExists('Add block');
    $this->clickLink('Add block');
    $assert_session->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is the label');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');

    // Test that the block has the contextual class applied and the container
    // for contextual links.
    $assert_session->elementExists('css', 'div.block-system-powered-by-block.contextual-region div[data-contextual-id]');

    // Ensure other blocks do not have contextual links.
    $assert_session->elementExists('css', 'div.block-page-title-block');
    $assert_session->elementNotExists('css', 'div.block-page-title-block.contextual-region div[data-contextual-id]');
  }

  /**
   * Tests handling of missing or renamed layouts in Layout Builder.
   */
  public function testMissingLayoutRecovery(): void {
    $assert_session = $this->assertSession();

    // Create a user with necessary permissions.
    $this->drupalLogin($this->drupalCreateUser([
      'administer content types',
      'configure any layout',
      'administer node display',
      'administer node fields',
      'access content',
    ]));

    // Create a content type with Layout Builder enabled.
    $this->drupalGet('admin/structure/types/add');
    $this->submitForm([
      'name' => 'Test Layout Content',
      'type' => 'test_layout_content',
    ], 'Save and manage fields');

    $this->drupalGet('admin/structure/types/manage/test_layout_content/display/default');
    $this->submitForm(['layout[enabled]' => TRUE], 'Save');

    // Enable Layout Builder on the content type.
    $this->drupalGet('admin/structure/types/manage/test_layout_content/display');
    $assert_session->linkExists('Manage layout');
    $this->clickLink('Manage layout');

    // Add a block.
    $assert_session->linkExists('Add block');
    $this->clickLink('Add block');
    $assert_session->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $this->getSession()->getPage()->fillField('settings[label]', 'Test Layout Block');
    $this->getSession()->getPage()->pressButton('Add block');

    // Simulate a missing layout scenario by renaming/removing the layout.
    $this->simulateMissingLayout('layout_test_plugin');

    // Attempt to load the Layout Builder form again.
    $this->drupalGet('admin/structure/types/manage/test_layout_content/display/default');

    // Check if Layout Builder still functions and provides a recovery option.
    $assert_session->pageTextContains('The "layout_test_plugin" plugin does not exist.');
    $assert_session->pageTextContains('Select a new layout or reconfigure your settings.');
  }

  /**
   * Simulates a missing layout plugin.
   *
   * By overriding the layout manager's definitions.
   *
   * @param string $layout_id
   *   The ID of the layout to remove.
   */
  protected function simulateMissingLayout(string $layout_id): void {
    // Get the layout plugin manager service.
    $layout_plugin_manager = \Drupal::service('plugin.manager.core.layout');

    // Get current layout definitions.
    $definitions = $layout_plugin_manager->getDefinitions();

    // Remove the specified layout.
    if (isset($definitions[$layout_id])) {
      unset($definitions[$layout_id]);
    }

    // Override the service container with modified definitions.
    $mock_plugin_manager = $this->prophesize(LayoutPluginManager::class);
    $mock_plugin_manager->getDefinitions()->willReturn($definitions);

    // Replace the service in the container.
    \Drupal::getContainer()->set('plugin.manager.core.layout', $mock_plugin_manager->reveal());
  }

}
