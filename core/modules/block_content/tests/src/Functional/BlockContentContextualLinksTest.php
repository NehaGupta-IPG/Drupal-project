<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional;

/**
 * Tests contextual links for Block Content when Block configs are rendered.
 *
 * @group block_content
 */
class BlockContentContextualLinksTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'contextual',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests contextual links.
   */
  public function testBlockContentContextualLinks(): void {
    $block_content = $this->createBlockContent();

    $block = $this->placeBlock('block_content:' . $block_content->uuid());

    $user = $this->drupalCreateUser([
      'access contextual links',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('<front>');
    $this->assertSession()->elementAttributeContains('css', 'div[data-contextual-id]', 'data-contextual-id', 'block:block=' . $block->id() . ':langcode=en|block_content:block_content=' . $block_content->id() . ':');
  }

}
