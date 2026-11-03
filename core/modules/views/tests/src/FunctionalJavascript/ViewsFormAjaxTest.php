<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Views form actions during AJAX rebuilds.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class ViewsFormAjaxTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'action_bulk_test', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests an unrouted Views form retains the original path after AJAX.
   */
  public function testFormActionUsesOriginalPathAfterAjax(): void {
    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalLogin($this->drupalCreateUser([
      'administer nodes',
      'edit any page content',
      'delete any page content',
    ]));

    for ($i = 0; $i < 11; $i++) {
      $this->drupalCreateNode([
        'type' => 'page',
        'title' => 'Node ' . $i,
      ]);
    }
    $node = $this->drupalCreateNode(['type' => 'page']);

    $view = View::load('test_bulk_form');
    $displays = $view->get('display');
    $displays['block_1']['display_options']['use_ajax'] = TRUE;
    $displays['block_1']['display_options']['defaults']['use_ajax'] = FALSE;
    $view->set('display', $displays);
    $view->save();

    $this->drupalPlaceBlock('views_block:test_bulk_form-block_1', ['region' => 'content']);
    $this->drupalGet($node->toUrl());

    $this->clickLink('Go to page 2');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $form = $this->assertSession()->elementExists('css', '.views-form form');
    $action_path = parse_url($form->getAttribute('action'), PHP_URL_PATH);
    $this->assertSame($node->toUrl()->toString(), $action_path);
  }

}
