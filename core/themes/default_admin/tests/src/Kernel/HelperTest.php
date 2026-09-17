<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\default_admin\Helper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the helper methods of the theme.
 *
 * The helper caches most of its answers in static properties and function
 * statics, so every scenario that depends on a different site state needs a
 * test method of its own.
 *
 * @see \Drupal\default_admin\Helper
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class HelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'default_admin_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // ::isActive() reads the administration theme and the default theme from
    // system.theme, so that configuration has to be in place.
    $this->installConfig(['system']);

    // default_admin is the theme under test, Stark stands in for an unrelated
    // theme and test_installer_theme is the core test theme that has
    // default_admin as its base theme.
    // @see core/modules/system/tests/themes/test_installer_theme/test_installer_theme.info.yml
    $this->container->get('theme_installer')->install([
      'default_admin',
      'stark',
      'test_installer_theme',
    ]);
    // Installing a theme rebuilds the container.
    $this->container = \Drupal::getContainer();

    // ::isContentForm() lets the active theme alter the list of content form
    // routes, and default_admin is the theme that is active when it runs.
    \Drupal::theme()->setActiveTheme(\Drupal::service(ThemeInitializationInterface::class)->initTheme('default_admin'));
  }

  /**
   * Tests that the theme is active when it is the administration theme.
   */
  public function testIsActiveAsAdminTheme(): void {
    $this->config('system.theme')
      ->set('admin', 'default_admin')
      ->set('default', 'stark')
      ->save();

    $this->assertTrue(Helper::isActive());

    // The answer is computed once and then kept for the rest of the request,
    // so that the theme does not look the theme configuration up again for
    // every element it preprocesses.
    $this->config('system.theme')
      ->set('admin', 'stark')
      ->save();
    $this->assertTrue(Helper::isActive());
  }

  /**
   * Tests that the theme is active when it is the default theme.
   *
   * With no administration theme configured, administrative pages are rendered
   * with the default theme.
   */
  public function testIsActiveAsDefaultTheme(): void {
    $this->config('system.theme')
      ->set('admin', '')
      ->set('default', 'default_admin')
      ->save();

    $this->assertTrue(Helper::isActive());
  }

  /**
   * Tests that the theme is active when a sub-theme of it is used.
   */
  public function testIsActiveAsBaseThemeOfAdminTheme(): void {
    $this->config('system.theme')
      ->set('admin', 'test_installer_theme')
      ->set('default', 'stark')
      ->save();

    $this->assertTrue(Helper::isActive());
  }

  /**
   * Tests that the theme is not active for unrelated themes.
   */
  public function testIsNotActiveForUnrelatedThemes(): void {
    $this->config('system.theme')
      ->set('admin', 'stark')
      ->set('default', 'stark')
      ->save();

    $this->assertFalse(Helper::isActive());
  }

  /**
   * Tests that the media library forms are not treated as content forms.
   *
   * The list of form IDs to ignore is evaluated before the form object, so
   * these forms stay out of the content form layout even when the form object
   * is a content entity form.
   */
  public function testIsContentFormIgnoresMediaLibraryForms(): void {
    $form_state = new FormState();
    $form_state->setFormObject($this->createStub(ContentEntityFormInterface::class));

    $this->assertFalse(Helper::isContentForm($form_state, 'media_library_add_form_upload'));
  }

  /**
   * Tests that modules can add form IDs to the list of forms to ignore.
   *
   * @see \Drupal\default_admin_test\Hook\DefaultAdminTestHooks::contentFormIgnoreFormIds()
   */
  public function testIsContentFormIgnoresFormIdsFromModules(): void {
    $form_state = new FormState();
    $form_state->setFormObject($this->createStub(ContentEntityFormInterface::class));

    $this->assertFalse(Helper::isContentForm($form_state, 'default_admin_test_ignored_form'));
  }

  /**
   * Tests that forms based on the node form are content forms.
   */
  public function testIsContentFormDetectsNodeForms(): void {
    $form_state = new FormState();
    $form_state->addBuildInfo('base_form_id', 'node_form');

    $this->assertTrue(Helper::isContentForm($form_state, 'node_page_edit_form'));

    // Once a content form has been detected, every later call reports it, so
    // that the layout stays consistent while the page is being built.
    $this->assertTrue(Helper::isContentForm());
  }

  /**
   * Tests that content entity forms are content forms.
   */
  public function testIsContentFormDetectsContentEntityForms(): void {
    $form_state = new FormState();
    $form_state->setFormObject($this->createStub(ContentEntityFormInterface::class));

    $this->assertTrue(Helper::isContentForm($form_state, 'block_content_basic_form'));
  }

  /**
   * Tests a form state that is not built from a form object.
   *
   * Such a form state has no form object in its build information, and the
   * decision is left to the route the form is built on.
   */
  public function testIsContentFormWithoutFormObject(): void {
    $this->setCurrentRoute('entity.node.edit_form');

    $this->assertTrue(Helper::isContentForm(new FormState(), 'default_admin_test_plain_form'));
  }

  /**
   * Tests that the routes the theme ships with are content form routes.
   *
   * The routes are only compared by name, so the modules providing them do not
   * have to be installed.
   */
  public function testIsContentFormMatchesShippedRoute(): void {
    $this->setCurrentRoute('entity.node.edit_form');

    $this->assertTrue(Helper::isContentForm());
  }

  /**
   * Tests that modules can register additional content form routes.
   */
  public function testIsContentFormMatchesRouteFromModule(): void {
    $this->setCurrentRoute('default_admin_test.content_form');

    $this->assertTrue(Helper::isContentForm());
  }

  /**
   * Tests that modules can remove content form routes.
   *
   * One of the routes the theme ships with is menu_ui.link_edit, and the test
   * module removes it in hook_default_admin_content_form_routes_alter(), so
   * unlike the route of ::testIsContentFormMatchesShippedRoute() it no longer
   * gets the content form layout.
   *
   * @see \Drupal\default_admin_test\Hook\DefaultAdminTestHooks::contentFormRoutesAlter()
   */
  public function testIsContentFormRespectsRouteAlterHook(): void {
    $this->setCurrentRoute('menu_ui.link_edit');

    $this->assertFalse(Helper::isContentForm());
  }

  /**
   * Tests that other routes are not content form routes.
   */
  public function testIsContentFormIgnoresOtherRoutes(): void {
    $this->setCurrentRoute('system.modules_list');

    $this->assertFalse(Helper::isContentForm());
  }

  /**
   * Tests that elements other than links are returned unchanged.
   */
  public function testConvertLinkToActionLinkSkipsNonLinks(): void {
    $markup = ['#type' => 'markup', '#markup' => 'Not a link'];
    $this->assertSame($markup, Helper::convertLinkToActionLink($markup, 'cog'));

    // A link without a URL cannot become an action link either, because the
    // classes are also set on the URL object.
    $without_url = ['#type' => 'link', '#title' => 'No URL'];
    $this->assertSame($without_url, Helper::convertLinkToActionLink($without_url, 'cog'));
  }

  /**
   * Tests the classes of a converted action link.
   */
  public function testConvertLinkToActionLink(): void {
    $link = Helper::convertLinkToActionLink($this->linkElement(), 'cog', 'small');

    $classes = $link['#options']['attributes']['class'];
    $this->assertContains('action-link', $classes);
    $this->assertContains('action-link--icon-cog', $classes);
    $this->assertContains('action-link--small', $classes);

    // Items of the 'links' theme hook are rendered from the URL object alone,
    // so the classes have to be set on the URL as well.
    $this->assertSame($classes, $link['#url']->getOption('attributes')['class']);
  }

  /**
   * Tests the variant class of a converted action link.
   */
  public function testConvertLinkToActionLinkVariants(): void {
    $danger = Helper::convertLinkToActionLink($this->linkElement(), NULL, 'default', 'danger');
    $this->assertContains('action-link--danger', $danger['#options']['attributes']['class']);

    // An unsupported variant falls back to the default variant, which has no
    // modifier class of its own.
    $unsupported = Helper::convertLinkToActionLink($this->linkElement(), NULL, 'default', 'warning');
    $this->assertSame(['action-link'], $unsupported['#options']['attributes']['class']);
  }

  /**
   * Tests the size class of a converted action link.
   */
  public function testConvertLinkToActionLinkSizes(): void {
    $extrasmall = Helper::convertLinkToActionLink($this->linkElement(), NULL, 'extrasmall');
    $this->assertContains('action-link--extrasmall', $extrasmall['#options']['attributes']['class']);

    // An unsupported size adds no size class at all.
    $unsupported = Helper::convertLinkToActionLink($this->linkElement(), NULL, 'medium');
    $this->assertSame(['action-link'], $unsupported['#options']['attributes']['class']);
  }

  /**
   * Tests that button classes are dropped when converting a link.
   */
  public function testConvertLinkToActionLinkRemovesButtonClasses(): void {
    $element = $this->linkElement();
    $element['#options']['attributes']['class'] = [
      'button',
      'button--primary',
      'button--small',
      'link',
      'contextual-region',
    ];

    $link = Helper::convertLinkToActionLink($element, NULL, 'small');

    $classes = $link['#options']['attributes']['class'];
    $this->assertNotContains('button', $classes);
    $this->assertNotContains('button--primary', $classes);
    $this->assertNotContains('button--small', $classes);
    $this->assertNotContains('link', $classes);
    // Classes that are not about the button styling are kept.
    $this->assertContains('contextual-region', $classes);
    $this->assertContains('action-link--small', $classes);
  }

  /**
   * Tests that the attributes of a link are folded into its options.
   */
  public function testConvertLinkToActionLinkMovesAttributes(): void {
    $element = $this->linkElement();
    $element['#attributes'] = [
      'id' => 'the-action-link',
      'class' => ['from-attributes'],
    ];
    $element['#options']['attributes']['class'] = ['from-options'];

    $link = Helper::convertLinkToActionLink($element);

    // The conversion takes over what Link::preRenderLink() would do later on
    // and removes '#attributes', so that it does not happen twice.
    $this->assertArrayNotHasKey('#attributes', $link);
    $this->assertSame('the-action-link', $link['#options']['attributes']['id']);

    // Like Link::preRenderLink(), the classes are not merged deeply, so the
    // classes already set in the options win.
    $classes = $link['#options']['attributes']['class'];
    $this->assertContains('from-options', $classes);
    $this->assertNotContains('from-attributes', $classes);
    $this->assertContains('action-link', $classes);
  }

  /**
   * Tests storing and retrieving the form actions of the request.
   */
  public function testFormActions(): void {
    $this->assertNull(Helper::formActions());

    $actions = ['submit' => ['#type' => 'submit', '#value' => 'Save']];
    $this->assertSame($actions, Helper::formActions($actions));

    // The actions stay available for the rest of the request, which is what
    // the page layout relies on.
    $this->assertSame($actions, Helper::formActions());

    // Storing them again replaces the previously stored actions.
    $replacement = ['delete' => ['#type' => 'link', '#title' => 'Delete']];
    Helper::formActions($replacement);
    $this->assertSame($replacement, Helper::formActions());
  }

  /**
   * Reports the given route as the current route.
   *
   * @param string $route_name
   *   The route name to report as current.
   */
  protected function setCurrentRoute(string $route_name): void {
    $request_stack = $this->container->get('request_stack');

    $request = Request::create('/');
    // Without the route object the route match is built as a NullRouteMatch
    // and the route name is lost.
    // @see \Drupal\Core\Routing\RouteMatch::createFromRequest()
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('/'));
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    // Carry over the session of the request the kernel booted with, which the
    // test tears down afterwards.
    // @see \Drupal\KernelTests\KernelTestBase::tearDown()
    $request->setSession($request_stack->getSession());

    $request_stack->push($request);
  }

  /**
   * Builds a link render element to convert to an action link.
   *
   * @return array
   *   A link render element pointing at the front page.
   */
  protected function linkElement(): array {
    return [
      '#type' => 'link',
      '#title' => 'Do something',
      '#url' => Url::fromRoute('<front>'),
    ];
  }

}
