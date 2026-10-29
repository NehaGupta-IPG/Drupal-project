<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\node\Entity\Node;
use Drupal\Tests\layout_builder\Traits\EnableLayoutBuilderTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Layout Builder can recover from a missing layout plugin.
 */
#[Group('layout_builder')]
#[RunTestsInSeparateProcesses]
class LayoutBuilderMissingLayoutTest extends LayoutBuilderTestBase {

  use EnableLayoutBuilderTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder_layout_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test layout builder with a missing layout.
   */
  public function testLayoutWithMissingLayout(): void {
    // Add a layout from layout_builder_test to the default layout.
    $display = LayoutBuilderEntityViewDisplay::load('node.bundle_with_section_field.default');
    $this->enableLayoutBuilder($display);
    $display->appendSection(new Section('layout_builder_test_layout'));
    $display->save();

    $user = $this->drupalCreateUser([
      'access content',
      'administer blocks',
      'configure any layout',
      'administer node display',
      'configure all bundle_with_section_field node layout overrides',
    ]);

    $this->drupalGet('/node/1');
    // Verify the test layout has been rendered.
    $testSelector = '.testing-layout';
    $this->assertSession()->elementExists('css', $testSelector);

    // Simulate the layout being removed.
    \Drupal::state()->set('layout_builder_layout_test.remove_layout', TRUE);
    \Drupal::service(LayoutPluginManagerInterface::class)->clearCachedDefinitions();

    // Save the node to flush its cache.
    Node::load(1)->save();
    // Verify the node & default layout don't break.
    $this->drupalGet('/node/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', $testSelector);
    $this->drupalLogin($user);
    $this->drupalGet('/node/1/layout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', \sprintf('%s-preview', $testSelector));
    $this->drupalGet('/admin/structure/types/manage/bundle_with_section_field/display/default/layout');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', \sprintf('%s-preview', $testSelector));
  }

}
