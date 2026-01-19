<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Plugin;

use Drupal\Tests\views\Functional\ViewTestBase;
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
   * Tests Views form actions for an unrouted display without link display.
   */
  public function testFormActionFallsBackToCurrentRoute(): void {
    $this->drupalCreateContentType(['type' => 'article']);
    $node = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalPlaceBlock('views_block:test_bulk_form-block_1');

    $this->drupalGet('node/' . $node->id());
    $form = $this->assertSession()->elementExists('css', '.views-form form');
    $action = $form->getAttribute('action');

    $this->assertSame('/node/' . $node->id(), $action);
    $this->assertNotSame('/test_bulk_form', $action);
  }

}
