<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Core\Form\FormState;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the link area handler.
 *
 * @see \Drupal\views\Plugin\views\area\Link
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class AreaLinkTest extends ViewsKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_view'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installEntitySchema('user');
    $this->container->get('router.builder')->rebuild();
    $this->setUpCurrentUser([], ['access user profiles']);
  }

  /**
   * Tests rendering of the link area.
   */
  public function testRender(): void {
    $view = $this->getViewWithLink([
      'path' => '/user/password',
      'title' => 'Log in & go',
      'class' => 'button button--primary',
    ]);
    $this->assertSame('<a href="/user/password" class="button button--primary">Log in &amp; go</a>', $this->renderLink($view));

    // Without results, the link is hidden unless "empty" is enabled.
    $this->assertSame([], $this->getLink($view)->render(TRUE));
    $this->getLink($view)->options['empty'] = TRUE;
    $this->assertStringContainsString('href="/user/password"', $this->renderLink($view, TRUE));

    // The front page and external URLs.
    $this->getLink($view)->options['path'] = '<front>';
    $this->getLink($view)->options['class'] = '';
    $this->assertSame('<a href="/">Log in &amp; go</a>', $this->renderLink($view));
    $this->getLink($view)->options['path'] = 'https://example.com/path';
    $this->assertSame('<a href="https://example.com/path">Log in &amp; go</a>', $this->renderLink($view));

    // Invalid paths and empty text render nothing.
    $this->getLink($view)->options['path'] = 'not-a-path';
    $this->assertSame('', $this->renderLink($view));
    $this->getLink($view)->options['path'] = '/user/password';
    $this->getLink($view)->options['title'] = '';
    $this->assertSame('', $this->renderLink($view));
  }

  /**
   * Tests that links to inaccessible routes are not rendered.
   */
  public function testAccess(): void {
    $view = $this->getViewWithLink([
      'path' => 'route:user.admin_permissions',
      'title' => 'Permissions',
    ]);
    $build = $this->getLink($view)->render();
    $this->assertArrayNotHasKey('#type', $build);
    $this->assertContains('user.permissions', $build['#cache']['contexts']);

    $this->setUpCurrentUser([], ['administer permissions']);
    $view = $this->getViewWithLink([
      'path' => 'route:user.admin_permissions',
      'title' => 'Permissions',
    ]);
    $this->assertSame('<a href="/admin/people/permissions">Permissions</a>', $this->renderLink($view));
  }

  /**
   * Tests token replacement in the link area.
   */
  public function testTokens(): void {
    $view = $this->getViewWithLink([
      'path' => '/user/password?name={{ name }}',
      'title' => 'Link for {{ name }}',
      'class' => 'link-{{ name }}',
      'tokenize' => TRUE,
    ]);
    // Field tokens are taken from the rendered fields of the first row.
    $view->render();
    $this->assertSame('<a href="/user/password?name=John" class="link-john">Link for John</a>', $this->renderLink($view));
  }

  /**
   * Tests validation of the options form.
   */
  public function testValidation(): void {
    $view = $this->getViewWithLink(['path' => '', 'title' => 'Link']);
    $handler = $this->getLink($view);
    $paths = [
      '/user/password' => TRUE,
      '<front>' => TRUE,
      'https://example.com' => TRUE,
      'route:user.login' => TRUE,
      '/user/{{ name }}' => TRUE,
      'not-a-path' => FALSE,
      'route:does.not.exist' => FALSE,
    ];
    foreach ($paths as $path => $valid) {
      $form = ['path' => ['#parents' => ['options', 'path']]];
      $form_state = (new FormState())->setValue(['options', 'path'], $path);
      $handler->validateOptionsForm($form, $form_state);
      $this->assertSame(!$valid, $form_state->hasAnyErrors(), $path);
    }
  }

  /**
   * Builds and executes the test view with a link area in the header.
   *
   * @param array $options
   *   The options of the link area handler.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executed view.
   */
  protected function getViewWithLink(array $options): ViewExecutable {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('header', [
      'link' => [
        'id' => 'link',
        'table' => 'views',
        'field' => 'link',
        'plugin_id' => 'link',
      ] + $options,
    ]);
    $this->executeView($view);
    return $view;
  }

  /**
   * Gets the link area handler of the view.
   */
  protected function getLink(ViewExecutable $view): object {
    return $view->display_handler->handlers['header']['link'];
  }

  /**
   * Renders the link area handler of the view.
   */
  protected function renderLink(ViewExecutable $view, bool $empty = FALSE): string {
    $build = $this->getLink($view)->render($empty);
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
