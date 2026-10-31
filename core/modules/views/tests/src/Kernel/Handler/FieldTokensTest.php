<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use Drupal\node\Entity\Node;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use Drupal\views\Tests\ViewTestData;
use Drupal\user\UserInterface;

/**
 * Tests "Link to the Content" functionality & views integration for tokens.
 *
 * @group views
 */
class FieldTokensTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'user',
    'node',
    'views_test_data',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_field_tokens'];

  /**
   * The admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    // First set up the needed entity types before installing the views.
    parent::setUp(FALSE);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    ViewTestData::createTestViews(static::class, ['views_test_data']);

    // Bypass any field access.
    $this->adminUser = User::create(['name' => $this->randomString()]);
    $this->adminUser->save();
    $this->container->get('current_user')->setAccount($this->adminUser);

    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

    // Create a text field.
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'type' => 'string',
      'entity_type' => 'node',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'text field',
    ])->save();

    Views::viewsData()->clear();
  }

  /**
   * Tests the output of Views string and title fields when modified.
   *
   * This tests the output of both string and title fields when the "Link to
   * content" checkbox is checked and the field is rewritten to ensure that
   * the entire rewritten field is inside a single a tag.
   */
  public function testViewsTokens(): void {
    $test_text = $this->getRandomGenerator()->word(2);
    $test_title = $this->getRandomGenerator()->word(2);
    $node = Node::create([
      'type' => 'page',
      'title' => $test_title,
      'field_text' => $test_text,
      'user_id' => $this->adminUser->id(),
    ]);
    $node->save();

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $executable = Views::getView('test_field_tokens');
    $executable->initHandlers();

    $output = $executable->preview();
    $output = $renderer->renderRoot($output);
    $this->setRawContent($output);
    $test_html = $this->xpath('//a/div[contains(@class, "test-field-text")]');
    $this->assertCount(1, $test_html);

    // Formatted Title.
    $this->assertStringContainsString("Text formatted: $test_text", (string) $test_html[0]);
    // Raw Title.
    $this->assertStringContainsString("Text raw: $test_text", (string) $test_html[0]);
    // Options is an array and should return empty after token replace.
    $this->assertStringContainsString("Text raw options: .", (string) $test_html[0]);

    $test_html = $this->xpath('//a/div[contains(@class, "test-title")]');
    $this->assertCount(1, $test_html);

    // Formatted Title.
    $this->assertStringContainsString("Title formatted: $test_title", (string) $test_html[0]);
    // Raw Title.
    $this->assertStringContainsString("Title raw: $test_title", (string) $test_html[0]);
    // Options is an array and should return empty after token replace.
    $this->assertStringContainsString("Title raw options: .", (string) $test_html[0]);
  }

}
