<?php

namespace Drupal\Tests\system\Functional\Render;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests if blocks can be rendered separately in a region template.
 */
#[Group('Render')]
class RegionContentRenderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'test_theme_region_content';

  /**
   * Tests the rendering of blocks in a region template.
   */
  public function testRegionContentRendering(): void {
    // Define test blocks.
    $test_blocks = [
      'first' => [
        'label' => 'I am first',
        'weight' => '0',
      ],
      'second' => [
        'label' => 'I am second',
        'weight' => '1',
      ],
      'special' => [
        'label' => 'I am special',
        'weight' => '2',
      ],
    ];

    // Place the test blocks in the header.
    foreach ($test_blocks as $id => $test_block) {
      $this->drupalPlaceBlock('system_powered_by_block', [
        'region' => 'header',
        'id' => $id,
        'label' => $test_block['label'],
        'weight' => $test_block['weight'],
      ]);
    }

    // Test if the blocks are placed correctly in the region template. See
    // core/modules/system/tests/themes/test_theme_region_content/templates/region--header.html.twig
    $this->drupalGet('');
    $assert = $this->assertSession();
    $assert->elementExists('css', '#wrapper-special #block-special');
    $assert->elementExists('css', '#wrapper-the-rest #block-first');
    $assert->elementExists('css', '#wrapper-the-rest #block-second');
    $assert->elementNotExists('css', '#wrapper-the-rest #block-special');
    $assert->elementNotExists('css', '#wrapper-special #block-first');
    $assert->elementNotExists('css', '#wrapper-special #block-second');
  }

}
