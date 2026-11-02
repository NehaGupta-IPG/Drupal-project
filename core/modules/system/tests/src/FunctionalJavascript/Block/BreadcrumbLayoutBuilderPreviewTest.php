<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript\Block;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\layout_builder\FunctionalJavascript\LayoutBuilderSortTrait;
use Drupal\Tests\layout_builder\Traits\EnableLayoutBuilderTrait;

/**
 * Tests breadcrumbs in layout builder.
 *
 * @group system
 * @group layout_builder
 */
class BreadcrumbLayoutBuilderPreviewTest extends WebDriverTestBase {

  use EnableLayoutBuilderTrait;
  use LayoutBuilderSortTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'node',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Add a new bundle.
    $this->createContentType(['type' => 'bundle_with_section_field']);

    // Enable layout overrides.
    $display = LayoutBuilderEntityViewDisplay::load('node.bundle_with_section_field.default');
    $this->enableLayoutBuilder($display);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'access contextual links',
    ]));
  }

  /**
   * Tests breadcrumbs preview in Layout Builder.
   */
  public function testBreadcrumbsInLayoutBuilder(): void {
    $page = $this->getSession()->getPage();
    $node = $this->createNode([
      'type' => 'bundle_with_section_field',
      'title' => 'The first node title',
    ]);
    $section = new Section('layout_twocol_section');
    $component = new SectionComponent(\Drupal::service('uuid')->generate(), 'first', [
      'id' => 'system_breadcrumb_block',
    ]);
    $section->appendComponent($component);
    $node->get('layout_builder__layout')->appendSection($section);
    $node->save();
    $selector = '[data-layout-content-preview-placeholder-label*=Breadcrumbs]';
    $this->drupalGet('node/' . $node->id() . '/layout');
    $this->assertSession()->elementExists('css', '.layout__region--first ' . $selector);
    $this->assertSession()->elementNotExists('css', '.layout__region--second ' . $selector);
    $this->sortableTo($selector, '.layout__region--first', '.layout__region--second');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementNotExists('css', '.layout__region--first ' . $selector);
    $this->assertSession()->elementExists('css', '.layout__region--second ' . $selector);
    $page->pressButton('Save layout');
    $this->assertSession()->linkExists('Home');
    $this->assertSession()->pageTextNotContains('"Breadcrumbs" block');
  }

}
