<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\default_admin\Hook\ThemeSuggestionHooks;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the theme suggestions the theme adds to render elements.
 *
 * The suggestion list is the observable result of these hooks: the theme ships
 * a template for the suggestions it adds, so a missing or misspelled
 * suggestion silently falls back to the default template.
 *
 * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class ThemeSuggestionHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The page suggestions consult Helper::isContentForm(), which lets the
    // active theme alter the list of content form routes, so the theme has to
    // be installed and active.
    // @see \Drupal\default_admin\Helper::isContentForm()
    $this->container->get('theme_installer')->install(['default_admin']);
    $this->container = \Drupal::getContainer();
    \Drupal::theme()->setActiveTheme(\Drupal::service(ThemeInitializationInterface::class)->initTheme('default_admin'));
  }

  /**
   * Tests the vertical tab suggestion for details elements.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsDetailsAlter()
   */
  public function testDetailsSuggestion(): void {
    $hooks = $this->createSuggestionHooks();

    $suggestions = ['details'];
    $hooks->themeSuggestionsDetailsAlter($suggestions, [
      'element' => ['#vertical_tab_item' => TRUE],
    ]);
    $this->assertSame(['details', 'details__vertical_tabs'], $suggestions);

    // A details element outside of a vertical tabs group keeps the default
    // template.
    $suggestions = ['details'];
    $hooks->themeSuggestionsDetailsAlter($suggestions, ['element' => []]);
    $this->assertSame(['details'], $suggestions);
  }

  /**
   * Tests the per form suggestion derived from the form ID.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsFormAlter()
   */
  public function testFormSuggestion(): void {
    $suggestions = ['form'];
    $this->createSuggestionHooks()->themeSuggestionsFormAlter($suggestions, [
      'element' => ['#id' => 'node-article-edit-form'],
    ]);

    // The HTML ID of a form holds dashes, while a theme suggestion has to use
    // underscores to map onto a template name.
    $this->assertSame(['form', 'form__node_article_edit_form'], $suggestions);
  }

  /**
   * Tests the per element type suggestion for form elements.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsFormElementAlter()
   */
  public function testFormElementSuggestion(): void {
    $hooks = $this->createSuggestionHooks();

    $suggestions = ['form_element'];
    $hooks->themeSuggestionsFormElementAlter($suggestions, [
      'element' => ['#type' => 'textfield'],
    ]);
    $this->assertSame(['form_element', 'form_element__textfield'], $suggestions);

    // An element without a type would produce the useless suggestion
    // "form_element__", so no suggestion is added at all.
    $suggestions = ['form_element'];
    $hooks->themeSuggestionsFormElementAlter($suggestions, ['element' => []]);
    $this->assertSame(['form_element'], $suggestions);
  }

  /**
   * Tests the maintenance page suggestion on and off the front page.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsMaintenancePageAlter()
   */
  public function testMaintenancePageSuggestion(): void {
    $front_page_matcher = $this->createStub(PathMatcherInterface::class);
    $front_page_matcher->method('isFrontPage')->willReturn(TRUE);

    $suggestions = ['maintenance_page'];
    $this->createSuggestionHooks($front_page_matcher)
      ->themeSuggestionsMaintenancePageAlter($suggestions);
    $this->assertSame([
      'maintenance_page',
      'maintenance_page__front',
    ], $suggestions);

    // Applications such as update.php run through the maintenance page as
    // well, and those keep the plain maintenance page template.
    $other_page_matcher = $this->createStub(PathMatcherInterface::class);
    $other_page_matcher->method('isFrontPage')->willReturn(FALSE);

    $suggestions = ['maintenance_page'];
    $this->createSuggestionHooks($other_page_matcher)
      ->themeSuggestionsMaintenancePageAlter($suggestions);
    $this->assertSame(['maintenance_page'], $suggestions);
  }

  /**
   * Tests the maintenance page suggestion when the front page check fails.
   *
   * The maintenance page is what a site shows when the database is gone, and
   * in that situation the front page check itself throws, which must not take
   * the maintenance page down with it.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsMaintenancePageAlter()
   */
  public function testMaintenancePageSuggestionWithFailingFrontPageCheck(): void {
    $path_matcher = $this->createStub(PathMatcherInterface::class);
    $path_matcher->method('isFrontPage')->willThrowException(new \Exception());

    $suggestions = ['maintenance_page'];
    $this->createSuggestionHooks($path_matcher)
      ->themeSuggestionsMaintenancePageAlter($suggestions);
    $this->assertSame([
      'maintenance_page',
      'maintenance_page__front',
    ], $suggestions);
  }

  /**
   * Tests the page suggestion derived from the path of the request.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsPageAlter()
   */
  public function testPageSuggestionFromPath(): void {
    $hooks = $this->createSuggestionHooks();

    $this->pushRequest('/admin/content/node');
    $suggestions = ['page'];
    $hooks->themeSuggestionsPageAlter($suggestions);
    $this->assertSame(['page', 'page__admin_content_node'], $suggestions);

    // Both the path separators and the dashes of a path become underscores,
    // so that the suggestion maps onto a template name.
    $this->pushRequest('/admin/config/media/image-styles');
    $suggestions = ['page'];
    $hooks->themeSuggestionsPageAlter($suggestions);
    $this->assertSame([
      'page',
      'page__admin_config_media_image_styles',
    ], $suggestions);
  }

  /**
   * Tests that the front page gets no page suggestion of its own.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsPageAlter()
   */
  public function testPageSuggestionOnFrontPage(): void {
    $this->pushRequest('/');

    $suggestions = ['page'];
    $this->createSuggestionHooks()->themeSuggestionsPageAlter($suggestions);

    // A suggestion built from the root path would be "page__", which is not a
    // template name, and the front page is not a content form either.
    $this->assertSame(['page'], $suggestions);
  }

  /**
   * Tests that a content form route also gets the node page suggestion.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsPageAlter()
   * @see \Drupal\default_admin\Helper::isContentForm()
   */
  public function testPageSuggestionOnContentForm(): void {
    $this->pushRequest('/node/1/edit', 'entity.node.edit_form');

    $suggestions = ['page'];
    $this->createSuggestionHooks()->themeSuggestionsPageAlter($suggestions);

    // The node page template carries the layout of the content forms, so it is
    // suggested for every content form route.
    $this->assertSame([
      'page',
      'page__node_1_edit',
      'page__node',
    ], $suggestions);
  }

  /**
   * Tests that the node page suggestion is not added twice.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsPageAlter()
   */
  public function testPageSuggestionKeepsSingleNodeSuggestion(): void {
    $this->pushRequest('/media/1/edit', 'entity.media.edit_form');

    // Another suggestion provider already asked for the node page template.
    $suggestions = ['page', 'page__node'];
    $this->createSuggestionHooks()->themeSuggestionsPageAlter($suggestions);

    $this->assertSame([
      'page',
      'page__node',
      'page__media_1_edit',
    ], $suggestions);
  }

  /**
   * Tests the simple table suggestion for multiple value field tables.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsTableAlter()
   */
  public function testTableSuggestion(): void {
    $hooks = $this->createSuggestionHooks();

    $suggestions = ['table'];
    $hooks->themeSuggestionsTableAlter($suggestions, [
      'attributes' => ['class' => ['field-multiple-table', 'js-form-wrapper']],
    ]);
    $this->assertSame(['table', 'table__simple'], $suggestions);

    // Every other table keeps the default table template.
    $suggestions = ['table'];
    $hooks->themeSuggestionsTableAlter($suggestions, [
      'attributes' => ['class' => ['sticky-enabled']],
    ]);
    $this->assertSame(['table'], $suggestions);

    // Tables are rendered without any class at all as well.
    $suggestions = ['table'];
    $hooks->themeSuggestionsTableAlter($suggestions, ['attributes' => []]);
    $this->assertSame(['table'], $suggestions);
  }

  /**
   * Tests the top bar suggestion.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsTopBarAlter()
   */
  public function testTopBarSuggestion(): void {
    $suggestions = ['top_bar'];
    $this->createSuggestionHooks()->themeSuggestionsTopBarAlter($suggestions);

    $this->assertSame(['top_bar', 'top_bar__default_admin'], $suggestions);
  }

  /**
   * Tests the status suggestion for views fields.
   *
   * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsViewsViewFieldAlter()
   */
  public function testViewsViewFieldSuggestion(): void {
    $hooks = $this->createSuggestionHooks();

    $status_field = $this->createStub(FieldPluginBase::class);
    $status_field->field = 'status';
    $suggestions = ['views_view_field'];
    $hooks->themeSuggestionsViewsViewFieldAlter($suggestions, [
      'field' => $status_field,
    ]);
    $this->assertSame([
      'views_view_field',
      'views_view_field__status',
    ], $suggestions);

    // The suggestion is bound to the name of the field, so fields holding
    // something else than a publication state are not marked up as one.
    $title_field = $this->createStub(FieldPluginBase::class);
    $title_field->field = 'title';
    $suggestions = ['views_view_field'];
    $hooks->themeSuggestionsViewsViewFieldAlter($suggestions, [
      'field' => $title_field,
    ]);
    $this->assertSame(['views_view_field'], $suggestions);
  }

  /**
   * Builds the hook implementations under test.
   *
   * @param \Drupal\Core\Path\PathMatcherInterface|null $path_matcher
   *   The path matcher to build the hook implementations with. Defaults to a
   *   stub, as only the maintenance page suggestion consults it.
   *
   * @return \Drupal\default_admin\Hook\ThemeSuggestionHooks
   *   The hook implementations.
   */
  protected function createSuggestionHooks(?PathMatcherInterface $path_matcher = NULL): ThemeSuggestionHooks {
    return new ThemeSuggestionHooks(
      $this->container->get('request_stack'),
      $path_matcher ?? $this->createStub(PathMatcherInterface::class),
    );
  }

  /**
   * Makes a request the current one.
   *
   * @param string $path
   *   The path of the request.
   * @param string|null $route_name
   *   The name of the route the request was matched against, or NULL for a
   *   request that has not been routed.
   */
  protected function pushRequest(string $path, ?string $route_name = NULL): void {
    $request_stack = $this->container->get('request_stack');
    $request = Request::create($path);
    // The tear down of a kernel test cleans up the session of whatever request
    // is current by then, so every pushed request has to carry one.
    $request->setSession($request_stack->getSession());

    if ($route_name !== NULL) {
      // The current route match only reports a route name when the request
      // carries the route object it was matched against as well.
      // @see \Drupal\Core\Routing\RouteMatch::createFromRequest()
      $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
      $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route($path));
    }

    $request_stack->push($request);
  }

}
