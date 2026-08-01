<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Plugin;

use Drupal\Core\Url;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Views forms functionality.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class ViewsFormTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['action_bulk_test', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the Views form wrapper.
   */
  public function testFormWrapper(): void {
    $this->drupalGet('test_bulk_form');
    // Ensure we have the form tag on the page.
    $xpath = $this->cssSelect('.views-form form');
    $this->assertCount(1, $xpath, 'There is one views form on the page.');
    // Ensure we don't have nested form elements.
    $result = (bool) preg_match('#<form[^>]*?>(?!/form).*<form#s', $this->getSession()->getPage()->getContent());
    $this->assertFalse($result, 'The views form element is not nested.');

    // Test the form isn't shown when the display doesn't use fields.
    $this->drupalGet('display-without-fields');
    // Ensure there's no form.
    $xpath = $this->cssSelect('.views-form form');
    $this->assertCount(0, $xpath);
  }

  /**
   * Tests Views forms without a link display use the current route.
   */
  public function testFormActionFallsBackToCurrentRoute(): void {
    $this->drupalCreateContentType(['type' => 'article']);
    $node = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalPlaceBlock('views_block:test_bulk_form-block_1', ['region' => 'content']);

    $this->drupalGet('node/' . $node->id());
    $form = $this->assertSession()->elementExists('css', '.views-form form');
    $action = $form->getAttribute('action');

    $this->assertSame(Url::fromRoute('entity.node.canonical', ['node' => $node->id()])->toString(), $action);
  }

  /**
   * Tests Views form actions honor a custom link display URL.
   */
  public function testFormActionUsesCustomLinkDisplayPath(): void {
    $view = View::load('test_bulk_form');
    $displays = $view->get('display');
    $displays['block_1']['display_options']['link_display'] = 'custom_url';
    $displays['block_1']['display_options']['link_url'] = '/custom-bulk-form';
    $displays['block_1']['display_options']['defaults']['link_display'] = FALSE;
    $displays['block_1']['display_options']['defaults']['link_url'] = FALSE;
    $view->set('display', $displays);
    $view->save();

    $this->drupalCreateContentType(['type' => 'article']);
    $node = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalPlaceBlock('views_block:test_bulk_form-block_1', ['region' => 'content']);

    $this->drupalGet('node/' . $node->id());
    $form = $this->assertSession()->elementExists('css', '.views-form form');
    $action = $form->getAttribute('action');

    $this->assertSame('/custom-bulk-form', $action);
  }

}
