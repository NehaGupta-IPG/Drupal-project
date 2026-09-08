<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Functional;

use Drupal\Component\Utility\Html;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests blocks are being rendered in order by weight.
 */
#[Group('block')]
#[RunTestsInSeparateProcesses]
class BlockRenderOrderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $testBlocks = [
    'stark_powered' => [
      'weight' => -3,
      'id' => 'stark_powered',
      'label' => 'Test block A',
    ],
    'stark_by' => [
      'weight' => 3,
      'id' => 'stark_by',
      'label' => 'Test block C',
    ],
    'stark_drupal' => [
      'weight' => 3,
      'id' => 'stark_drupal',
      'label' => 'Test block B',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a test user.
    $end_user = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($end_user);
    // Enable test blocks and place them in the same region.
    $region = 'header';

    // Place the test blocks.
    foreach ($this->testBlocks as $test_block) {
      $this->drupalPlaceBlock('system_powered_by_block', [
        'label' => $test_block['label'],
        'region' => $region,
        'weight' => $test_block['weight'],
        'id' => $test_block['id'],
      ]);
    }
  }

  /**
   * Tests the render order of the blocks.
   */
  public function testBlockRenderOrder(): void {
    $this->drupalGet('');
    $test_content = $this->getSession()->getPage()->getContent();
    $position = [];
    $controller = $this->container->get('entity_type.manager')->getStorage('block');
    foreach ($controller->loadMultiple() as $return_block) {
      $id = $return_block->id();
      if ($return_block_weight = $return_block->getWeight()) {
        $this->assertSame((int) $this->testBlocks[$id]['weight'], $return_block_weight, 'Block weight is set as "' . $return_block_weight . '" for ' . $id . ' block.');
        $position[$id] = strpos($test_content, Html::getClass('block-' . $this->testBlocks[$id]['id']));
      }
    }
    // Verify that blocks with different weight are rendered in the correct
    // order.
    $this->assertLessThan($position['stark_by'], $position['stark_powered']);
    // Verify that blocks with identical weight are rendered in alphabetical
    // order.
    $this->assertLessThan($position['stark_by'], $position['stark_drupal']);
  }

  /**
   * Tests Block::sort function deprecation.
   */
  #[IgnoreDeprecations]
  public function testBlockSortDeprecation(): void {
    $this->drupalGet('');

    $controller = $this->container->get('entity_type.manager')->getStorage('block');
    $return_block = $controller->load('stark_powered');
    $this->expectUserDeprecationMessage('Drupal\block\Entity\Block::sort is deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Use Drupal\block\Entity\Block::sortEntities() instead. See https://www.drupal.org/project/drupal/issues/2265487');
    $return_block->sort($return_block, $return_block);
  }

}
