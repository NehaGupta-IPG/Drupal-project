<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for title field formatter.
 *
 * @group field
 */
class TitleFormatterTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    // Define field storage.
    FieldStorageConfig::create([
      'field_name' => 'field_subtitle',
      'entity_type' => 'node',
      'type' => 'string',
      'settings' => [
        'max_length' => 255,
      ],
      'translatable' => FALSE,
    ])->save();
    // Define field instance for a content type.
    FieldConfig::create([
      'field_name' => 'field_subtitle',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Custom Text Field',
      'required' => FALSE,
      'settings' => [
        'max_length' => 255,
      ],
    ])->save();
    // Configure display.
    $display = EntityViewDisplay::load('node.page.default');
    $display->setComponent('field_subtitle', [
      'type' => 'title',
      'settings' => [
        'link_to_entity' => 1,
        'tag' => 'h2',
      ],
    ])->save();
  }

  /**
   * Test title formatter output.
   */
  public function testTitleFormatter(): void {
    $user = $this->drupalCreateUser();
    $node = Node::create([
      'title' => 'Test node',
      'type' => 'page',
      'field_subtitle' => 'Test subtitle node',
      'uid' => $user->id(),
    ]);
    $node->save();
    $assert = $this->assertSession();
    $subtitle = $node->get('field_subtitle')->getValue();

    // Verify title formatter output.
    $this->drupalGet('node/' . $node->id());
    $assert->elementTextContains('css', 'div > h2 a[href="' . $node->toUrl()->toString() . '"]', $subtitle[0]['value']);
  }

}
