<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Core\Database\Database;
use Drupal\block\Entity\Block;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests "Hide block if the view output is empty" with a view area.
 *
 * The block display asks whether its output is empty before the areas are
 * rendered, so a view area in "No results behavior" has to know whether the
 * view it embeds is empty before it renders it.
 *
 * @see \Drupal\views\Plugin\views\area\View::isEmpty()
 * @see \Drupal\views\Plugin\views\display\Block::execute()
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class AreaViewBlockHideEmptyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'views']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    // One account exists, so a view filtered on its name has a result and a
    // view filtered on any other name has none.
    User::create(['uid' => 1, 'name' => 'editor', 'status' => 1])->save();

    // The views to embed: one with no results, one with a result.
    $this->createUserView('child_empty', 'missing');
    $this->createUserView('child_results', 'editor');
  }

  /**
   * Tests that an empty block with no "No results behavior" is hidden.
   */
  public function testNoEmptyAreaIsHidden(): void {
    $this->createUserView('parent', 'nobody', NULL, TRUE);
    $this->assertBlockHidden('parent');
  }

  /**
   * Tests that a block is hidden when the view it embeds is empty.
   */
  public function testEmptyEmbeddedViewIsHidden(): void {
    $this->createUserView('parent', 'nobody', 'child_empty:default', TRUE);

    // Once rendered, the display knows its output is empty.
    $view = Views::getView('parent');
    $view->setDisplay('block_1');
    $build = $view->preview();
    $this->container->get('renderer')->renderInIsolation($build);
    $this->assertTrue($view->display_handler->outputIsEmpty(), 'Once rendered, the display reports its output as empty.');

    // Block::execute() asks before the areas are rendered, which happens in a
    // #pre_render callback, and must get the same answer.
    $this->assertBlockHidden('parent');
  }

  /**
   * Tests that a block is hidden when the view it embeds is inaccessible.
   */
  public function testInaccessibleEmbeddedViewIsHidden(): void {
    $this->createUserView('child_denied', 'editor', NULL, FALSE, 'administer views');
    $this->createUserView('parent', 'nobody', 'child_denied:default', TRUE);
    $this->assertBlockHidden('parent');
  }

  /**
   * Tests that checking for emptiness does not run the embedded view twice.
   */
  public function testEmbeddedViewRunsOnce(): void {
    // The parent block is hidden when the embedded view is empty, so the
    // area is never rendered; only an embedded view with results reaches both
    // isEmpty() and render().
    foreach (['child_empty' => 'missing', 'child_results' => 'editor'] as $child => $name) {
      $this->createUserView("parent_$child", 'nobody', "$child:default", TRUE);
      Database::startLog($child);
      $this->renderBlock("parent_$child");
      // Views runs a string filter as LIKE, so the names are free of the
      // characters it escapes, and the logged argument matches the name.
      $queries = array_filter(
        Database::getLog($child),
        fn (array $entry): bool => in_array($name, $entry['args'], TRUE),
      );
      $this->assertCount(1, $queries, "The embedded view $child is queried once.");
    }
  }

  /**
   * Tests that a block is shown when the view it embeds has results.
   */
  public function testEmbeddedViewWithResultsIsShown(): void {
    $this->createUserView('parent', 'nobody', 'child_results:default', TRUE);
    $html = $this->renderBlock('parent');
    $this->assertStringContainsString('editor', $html);
  }

  /**
   * Asserts the block of a view renders to nothing.
   */
  protected function assertBlockHidden(string $view_id): void {
    $html = $this->renderBlock($view_id);
    $this->assertSame('', preg_replace('/\s+/', ' ', trim($html)), 'The block is hidden.');
  }

  /**
   * Places the block_1 display of a view and renders the block entity.
   */
  protected function renderBlock(string $view_id): string {
    $block = Block::create([
      'id' => $view_id . '_block',
      'theme' => 'stark',
      'region' => 'content',
      'plugin' => "views_block:$view_id-block_1",
      'settings' => ['label' => 'Parent block title', 'label_display' => 'visible'],
    ]);
    $block->save();
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('block')
      ->view($block);
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Creates a view listing user names, filtered on one name.
   *
   * @param string $id
   *   The view ID.
   * @param string $name
   *   The only user name the view lists.
   * @param string|null $embed
   *   A "view:display" to embed in the No results behavior, if any.
   * @param bool $hide_empty
   *   Whether the block display hides itself when empty.
   * @param string|null $permission
   *   A permission the view requires, if any.
   */
  protected function createUserView(string $id, string $name, ?string $embed = NULL, bool $hide_empty = FALSE, ?string $permission = NULL): void {
    $default = [
      'access' => $permission ? ['type' => 'perm', 'options' => ['perm' => $permission]] : ['type' => 'none'],
      'cache' => ['type' => 'none'],
      'query' => ['type' => 'views_query'],
      'style' => ['type' => 'default'],
      'row' => ['type' => 'fields'],
      'fields' => [
        'name' => [
          'id' => 'name',
          'table' => 'users_field_data',
          'field' => 'name',
          'entity_type' => 'user',
          'entity_field' => 'name',
          'plugin_id' => 'field',
          'type' => 'string',
          'settings' => ['link_to_entity' => FALSE],
          'label' => '',
        ],
      ],
      'filters' => [
        'name' => [
          'id' => 'name',
          'table' => 'users_field_data',
          'field' => 'name',
          'entity_type' => 'user',
          'entity_field' => 'name',
          'plugin_id' => 'string',
          'operator' => '=',
          'value' => $name,
        ],
      ],
      'empty' => [],
    ];
    if ($embed) {
      $default['empty']['view'] = [
        'id' => 'view',
        'table' => 'views',
        'field' => 'view',
        'plugin_id' => 'view',
        'view_to_insert' => $embed,
        'inherit_arguments' => FALSE,
      ];
    }

    View::create([
      'id' => $id,
      'label' => $id,
      'base_table' => 'users_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => $default,
        ],
        'block_1' => [
          'id' => 'block_1',
          'display_plugin' => 'block',
          'display_title' => 'Block',
          'position' => 1,
          'display_options' => ['block_hide_empty' => $hide_empty],
        ],
      ],
    ])->save();
  }

}
