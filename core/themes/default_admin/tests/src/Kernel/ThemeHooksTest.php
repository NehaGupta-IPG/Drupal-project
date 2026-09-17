<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\Core\Render\Element\Container;
use Drupal\Core\Render\Element\Operations;
use Drupal\Core\Render\Element\VerticalTabs;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\default_admin\Hook\ThemeHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the theme hooks and pre-render callbacks of the theme.
 *
 * @see \Drupal\default_admin\Hook\ThemeHooks::elementInfoAlter()
 * @see \Drupal\default_admin\Hook\ThemeHooks::pageAttachmentsAlter()
 * @see \Drupal\default_admin\Hook\ThemeHooks::themeRegistryAlter()
 * @see \Drupal\default_admin\Hook\ThemeHooks::container()
 * @see \Drupal\default_admin\Hook\ThemeHooks::managedFile()
 * @see \Drupal\default_admin\Hook\ThemeHooks::messagePlaceholder()
 * @see \Drupal\default_admin\Hook\ThemeHooks::operations()
 * @see \Drupal\default_admin\Hook\ThemeHooks::textFormat()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class ThemeHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    // Installing the theme creates its default settings and rebuilds the
    // container, which is what registers the #[Hook] implementations of the
    // theme. Activating it makes the theme manager run those implementations
    // for alter hooks such as element_info_alter.
    // @see \Drupal\Core\Hook\ThemeHookCollectorPass
    $this->container->get('theme_installer')->install(['default_admin']);
    $this->container = \Drupal::getContainer();
    \Drupal::theme()->setActiveTheme(\Drupal::service(ThemeInitializationInterface::class)->initTheme('default_admin'));

    // The settings service reads from the administration theme, so the theme
    // has to be the one whose settings the tests manipulate.
    // @see \Drupal\default_admin\Settings::getAdminTheme()
    $this->config('system.theme')->set('admin', 'default_admin')->save();
  }

  /**
   * Tests the pre-render callbacks the theme adds to the element info.
   *
   * The container and the operations callbacks have to run before the
   * callbacks of the element itself, the remaining ones after them.
   */
  public function testElementInfoAlter(): void {
    $info = [
      'container' => ['#pre_render' => [[Container::class, 'preRenderGroup']]],
      'text_format' => [],
      'operations' => ['#pre_render' => [[Operations::class, 'preRenderDropbutton']]],
      'vertical_tabs' => ['#pre_render' => [[VerticalTabs::class, 'preRenderVerticalTabs']]],
      'managed_file' => [],
      'status_messages' => [],
    ];

    $this->themeHooks()->elementInfoAlter($info);

    // The sidebar handling has to happen before the container is rendered.
    $this->assertSame([
      [ThemeHooks::class, 'container'],
      [Container::class, 'preRenderGroup'],
    ], $info['container']['#pre_render']);

    // The dropbutton type has to be set before
    // Dropbutton::preRenderDropbutton() turns it into CSS classes.
    $this->assertSame([
      [ThemeHooks::class, 'operations'],
      [Operations::class, 'preRenderDropbutton'],
    ], $info['operations']['#pre_render']);
    $this->assertContains('core/drupal.dialog.ajax', $info['operations']['#attached']['library']);

    $this->assertSame([
      [VerticalTabs::class, 'preRenderVerticalTabs'],
      [ThemeHooks::class, 'verticalTabs'],
    ], $info['vertical_tabs']['#pre_render']);
    $this->assertSame([[ThemeHooks::class, 'managedFile']], $info['managed_file']['#pre_render']);
    $this->assertSame([[ThemeHooks::class, 'messagePlaceholder']], $info['status_messages']['#pre_render']);

    // The text format callback is not idempotent: registering it twice adds
    // the same classes twice.
    $this->assertSame([[ThemeHooks::class, 'textFormat']], $info['text_format']['#pre_render']);
  }

  /**
   * Tests that element types absent from the element info are not created.
   */
  public function testElementInfoAlterLeavesAbsentElementsAlone(): void {
    $info = ['container' => []];

    $this->themeHooks()->elementInfoAlter($info);

    $this->assertSame(['container'], array_keys($info));
  }

  /**
   * Tests the text format callback in the element info of the active theme.
   */
  public function testTextFormatPreRenderIsRegisteredOnce(): void {
    $info = $this->container->get('plugin.manager.element_info')->getInfo('text_format');

    $this->assertCount(1, array_keys($info['#pre_render'] ?? [], [ThemeHooks::class, 'textFormat'], TRUE));
  }

  /**
   * Tests that an accordion container marks up its group children.
   */
  public function testContainerAccordion(): void {
    $element = [
      '#accordion' => TRUE,
      '#groups' => [
        'advanced' => [
          'meta' => ['#type' => 'details'],
          'author' => ['#type' => 'container'],
        ],
      ],
    ];

    $element = ThemeHooks::container($element);

    $this->assertContains('accordion', $element['#attributes']['class']);
    $this->assertContains('accordion__item', $element['#groups']['advanced']['meta']['#attributes']['class']);
    $this->assertContains('accordion__item', $element['#groups']['advanced']['author']['#attributes']['class']);

    // Only details children become accordion items.
    $this->assertTrue($element['#groups']['advanced']['meta']['#accordion_item']);
    $this->assertArrayNotHasKey('#accordion_item', $element['#groups']['advanced']['author']);
  }

  /**
   * Tests that a container without the accordion flag is left untouched.
   */
  public function testContainerWithoutAccordion(): void {
    $element = [
      '#attributes' => ['class' => ['layout-region']],
      '#groups' => [
        'advanced' => [
          'meta' => ['#type' => 'details'],
        ],
      ],
    ];

    $this->assertSame($element, ThemeHooks::container($element));
  }

  /**
   * Tests the dropbutton variant of operations elements.
   */
  public function testOperations(): void {
    $this->assertSame('extrasmall', ThemeHooks::operations([])['#dropbutton_type']);

    // An explicitly requested variant wins.
    $this->assertSame('small', ThemeHooks::operations(['#dropbutton_type' => 'small'])['#dropbutton_type']);
  }

  /**
   * Tests the classes the text format callback adds.
   */
  public function testTextFormat(): void {
    $element = ThemeHooks::textFormat($this->textFormatElement());

    $this->assertContains('clearfix', $element['format']['#attributes']['class']);
    $this->assertContains('form-item--editor-format', $element['format']['format']['#wrapper_attributes']['class']);
    $this->assertContains('form-element--extrasmall', $element['format']['format']['#attributes']['class']);
    $this->assertContains('form-element--editor-format', $element['format']['format']['#attributes']['class']);

    // A textarea summary is what the JavaScript of the summary widget looks
    // for.
    $this->assertContains('js-text-format-wrapper', $element['#attributes']['class']);
    $this->assertContains('js-form-type-textarea', $element['value']['#wrapper_attributes']['class']);
  }

  /**
   * Tests that a non-textarea summary does not get the summary widget classes.
   */
  public function testTextFormatWithoutTextareaSummary(): void {
    $element = $this->textFormatElement();
    $element['summary'] = ['#type' => 'value'];

    $element = ThemeHooks::textFormat($element);

    $this->assertArrayNotHasKey('#attributes', $element);
    $this->assertArrayNotHasKey('#wrapper_attributes', $element['value']);
  }

  /**
   * Tests the description toggle on a content form.
   */
  public function testTextFormatDescriptionToggle(): void {
    $this->config('default_admin.settings')->set('show_description_toggle', TRUE)->save();
    $this->setCurrentRoute('node.add');

    $element = $this->textFormatElement();
    $element['#description'] = 'Help for the body field.';

    $result = ThemeHooks::textFormat($element);

    // A text format element delegates the toggle to its value element, which
    // is the one rendering the description.
    $this->assertTrue($result['value']['#description_toggle']);
    $this->assertArrayNotHasKey('#description_toggle', $result);

    // Elements that only borrow the callback keep the toggle and hide their
    // own description.
    $element['#type'] = 'textarea';
    $result = ThemeHooks::textFormat($element);
    $this->assertTrue($result['#description_toggle']);
    $this->assertSame('invisible', $result['#description_display']);
  }

  /**
   * Tests that the description toggle is off while the setting is disabled.
   */
  public function testTextFormatDescriptionToggleDisabledBySetting(): void {
    $this->setCurrentRoute('node.add');

    $element = $this->textFormatElement();
    $element['#description'] = 'Help for the body field.';

    $result = ThemeHooks::textFormat($element);

    $this->assertArrayNotHasKey('#description_toggle', $result['value']);
    $this->assertArrayNotHasKey('#description_toggle', $result);
  }

  /**
   * Tests that the description toggle is off outside of content forms.
   */
  public function testTextFormatDescriptionToggleOutsideContentForm(): void {
    $this->config('default_admin.settings')->set('show_description_toggle', TRUE)->save();
    $this->setCurrentRoute('system.admin_content');

    $element = $this->textFormatElement();
    $element['#description'] = 'Help for the body field.';

    $result = ThemeHooks::textFormat($element);

    $this->assertArrayNotHasKey('#description_toggle', $result['value']);
    $this->assertArrayNotHasKey('#description_toggle', $result);
  }

  /**
   * Tests that an empty single cardinality file widget is wrapped in details.
   */
  public function testManagedFileWrapsSingleCardinalityWidget(): void {
    $element = ThemeHooks::managedFile($this->managedFileElement());

    $this->assertArrayHasKey('details', $element['#theme_wrappers']);
    $this->assertSame('Image', $element['#theme_wrappers']['details']['#title']);
    $this->assertTrue($element['#theme_wrappers']['details']['#required']);
    $this->assertSame(['open' => TRUE], $element['#theme_wrappers']['details']['#attributes']);
    $this->assertTrue($element['#single_wrapped']);

    // The wrapper carries the field label, so the widget takes over the label
    // of the multiple value widget.
    $this->assertSame('Add a new file', (string) $element['#title']);
    $this->assertArrayNotHasKey('#title_display', $element);
  }

  /**
   * Tests that the widget label is hidden when no file can be added.
   */
  public function testManagedFileHidesTitleWhenUploadIsUnavailable(): void {
    // A widget that already holds a file.
    $element = $this->managedFileElement();
    $element['#default_value']['fids'] = [7];
    $element = ThemeHooks::managedFile($element);
    $this->assertSame('invisible', $element['#title_display']);
    $this->assertSame('Image', $element['#title']);

    // A widget whose upload element is not accessible.
    $element = $this->managedFileElement();
    $element['upload']['#access'] = FALSE;
    $element = ThemeHooks::managedFile($element);
    $this->assertSame('invisible', $element['#title_display']);
    $this->assertSame('Image', $element['#title']);
  }

  /**
   * Tests the widgets that must not be wrapped in a details element.
   */
  public function testManagedFileWrapsOnlySingleCardinalityWidgets(): void {
    // Widgets can opt out of the wrapper.
    $element = $this->managedFileElement();
    $element['#do_not_wrap_in_details'] = TRUE;
    $element = ThemeHooks::managedFile($element);
    $this->assertArrayNotHasKey('#theme_wrappers', $element);
    $this->assertSame('Image', $element['#title']);

    // Multiple value widgets bring their own wrapper.
    $element = $this->managedFileElement();
    $element['#cardinality'] = -1;
    $element = ThemeHooks::managedFile($element);
    $this->assertArrayNotHasKey('#theme_wrappers', $element);
    $this->assertSame('Image', $element['#title']);
  }

  /**
   * Tests that a widget is only wrapped once.
   */
  public function testManagedFileIsWrappedOnce(): void {
    $element = ThemeHooks::managedFile($this->managedFileElement());

    $element = ThemeHooks::managedFile($element);

    // The wrapper keeps the field label instead of picking up the label the
    // first run moved onto the widget.
    $this->assertSame('Image', $element['#theme_wrappers']['details']['#title']);
    $this->assertSame('Add a new file', (string) $element['#title']);
  }

  /**
   * Tests the classes of the file widget buttons.
   */
  public function testManagedFileButtons(): void {
    $element = ThemeHooks::managedFile($this->managedFileElement());

    $this->assertContains('upload-button', $element['upload_button']['#attributes']['class']);
    $this->assertContains('button--extrasmall', $element['remove_button']['#attributes']['class']);
    $this->assertContains('remove-button', $element['remove_button']['#attributes']['class']);
  }

  /**
   * Tests the markup of the status messages placeholder.
   */
  public function testMessagePlaceholder(): void {
    $element = ThemeHooks::messagePlaceholder([
      'fallback' => ['#markup' => '<div data-drupal-messages-fallback class="hidden"></div>'],
    ]);

    $this->assertSame('<div data-drupal-messages-fallback class="hidden messages-list"></div>', $element['fallback']['#markup']);
  }

  /**
   * Tests that an element without a fallback placeholder is left untouched.
   */
  public function testMessagePlaceholderWithoutFallback(): void {
    $element = ['#theme' => 'status_messages'];

    $this->assertSame($element, ThemeHooks::messagePlaceholder($element));
  }

  /**
   * Tests the theme registry entries the theme changes.
   */
  public function testThemeRegistryAlter(): void {
    $registry = [
      'admin_block_content' => ['variables' => ['content' => NULL]],
      'text_format_wrapper' => ['variables' => ['children' => NULL]],
      'toolbar' => ['path' => 'core/modules/toolbar/templates'],
      'menu__toolbar' => ['path' => 'core/modules/toolbar/templates'],
      'page' => ['path' => 'core/modules/system/templates'],
    ];

    $this->themeHooks()->themeRegistryAlter($registry);

    // The templates of the theme use variables the core hooks do not declare.
    $this->assertSame([], $registry['admin_block_content']['variables']['attributes']);
    $this->assertFalse($registry['text_format_wrapper']['variables']['disabled']);

    // The toolbar templates of the theme live outside of its templates
    // directory, so the registry has to point at them explicitly.
    $this->assertSame('core/themes/default_admin/templates/navigation', $registry['toolbar']['path']);
    $this->assertSame('core/themes/default_admin/templates/navigation', $registry['menu__toolbar']['path']);

    // Unrelated hooks keep their template directory.
    $this->assertSame('core/modules/system/templates', $registry['page']['path']);
  }

  /**
   * Tests that the registry is not extended with hooks it does not contain.
   */
  public function testThemeRegistryAlterLeavesAbsentHooksAlone(): void {
    $registry = ['page' => ['path' => 'core/modules/system/templates']];

    $this->themeHooks()->themeRegistryAlter($registry);

    $this->assertSame(['page'], array_keys($registry));
  }

  /**
   * Tests the libraries, settings and head elements attached to every page.
   */
  public function testPageAttachmentsAlter(): void {
    $page = [];

    $this->themeHooks()->pageAttachmentsAlter($page);

    $libraries = $page['#attached']['library'];
    $this->assertContains('default_admin/init', $libraries);
    $this->assertContains('default_admin/breadcrumb', $libraries);
    $this->assertContains('default_admin/accent', $libraries);
    $this->assertContains('default_admin/sticky', $libraries);

    $settings = $page['#attached']['drupalSettings']['defaultAdmin'];
    $this->assertSame('auto', $settings['dark_mode']);
    $this->assertSame('dark-mode', $settings['dark_mode_class']);
    $this->assertSame('high-contrast-mode', $settings['high_contrast_mode_class']);
    $this->assertSame('blue', $settings['preset_accent_color']);
    $this->assertFalse($settings['high_contrast_mode']);
    $this->assertArrayHasKey('custom', $settings['accent_colors']);

    // Integrations built against the previous theme still read the old key,
    // which has to expose the very same settings.
    $this->assertSame($settings, $page['#attached']['drupalSettings']['gin']);

    // Styles that must apply without JavaScript are attached as a noscript
    // link, cache busted with the asset query string.
    $link = $this->htmlHeadElement($page, 'dropbutton_noscript');
    $this->assertSame('link', $link['#tag']);
    $this->assertTrue($link['#noscript']);
    $this->assertSame('stylesheet', $link['#attributes']['rel']);
    $this->assertSame(
      '/core/themes/default_admin/css/components/dropbutton-noscript.css?' . $this->container->get('asset.query_string')->get(),
      $link['#attributes']['href'],
    );

    // The dark mode is exposed before the page renders, so that the class can
    // be set without a flash of the light theme.
    $script = $this->htmlHeadElement($page, 'default_admin_dark_mode');
    $this->assertSame('script', $script['#tag']);
    $this->assertSame('application/json', $script['#attributes']['type']);
    $this->assertSame('default-admin-setting-dark_mode', $script['#attributes']['id']);
    $this->assertSame('{ "defaultAdminDarkMode": "auto" }', (string) $script['#value']);
  }

  /**
   * Tests the dark mode head element when the setting has no value.
   */
  public function testPageAttachmentsAlterWithoutDarkModeSetting(): void {
    $this->config('default_admin.settings')->clear('enable_dark_mode')->save();

    $page = [];
    $this->themeHooks()->pageAttachmentsAlter($page);

    $this->assertNull($page['#attached']['drupalSettings']['defaultAdmin']['dark_mode']);
    $this->assertSame(
      '{ "defaultAdminDarkMode": "unknown" }',
      (string) $this->htmlHeadElement($page, 'default_admin_dark_mode')['#value'],
    );
  }

  /**
   * Tests that per-user setting overrides make the page vary by user.
   */
  public function testPageAttachmentsAlterVariesByUserWhenOverridesAreAllowed(): void {
    $this->config('default_admin.settings')->set('show_user_theme_settings', TRUE)->save();

    // The hook is handed pages that carry no cache metadata of their own yet.
    $page = [];
    $this->themeHooks()->pageAttachmentsAlter($page);

    $this->assertSame(['user'], $page['#cache']['contexts']);

    // Cache metadata the page already declared has to survive.
    $page = [
      '#cache' => [
        'contexts' => ['route'],
        'tags' => ['config:system.menu.admin'],
      ],
    ];
    $this->themeHooks()->pageAttachmentsAlter($page);

    $this->assertEqualsCanonicalizing(['route', 'user'], $page['#cache']['contexts']);
    $this->assertSame(['config:system.menu.admin'], $page['#cache']['tags']);
  }

  /**
   * Tests that pages stay shareable while per-user overrides are disabled.
   */
  public function testPageAttachmentsAlterDoesNotVaryByUserWithoutOverrides(): void {
    $page = ['#cache' => ['contexts' => ['route']]];

    $this->themeHooks()->pageAttachmentsAlter($page);

    $this->assertSame(['route'], $page['#cache']['contexts']);
  }

  /**
   * Builds the hook implementations from the container.
   *
   * @return \Drupal\default_admin\Hook\ThemeHooks
   *   The theme related hook implementations.
   */
  protected function themeHooks(): ThemeHooks {
    return new ThemeHooks(
      $this->container->get('extension.list.theme'),
      $this->container->get('request_stack'),
      $this->container->get('asset.query_string'),
    );
  }

  /**
   * Makes the given route the current one.
   *
   * @param string $route_name
   *   The route name to report as current.
   */
  protected function setCurrentRoute(string $route_name): void {
    $request_stack = $this->container->get('request_stack');
    $request = Request::create('/');
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('/'));
    // The tear down of a kernel test cleans up the session of whatever request
    // is current by then, so every pushed request has to carry one.
    $request->setSession($request_stack->getSession());
    $request_stack->push($request);
  }

  /**
   * Builds a text format element with a summary.
   *
   * @return array
   *   The element as the text_textarea_with_summary widget builds it.
   */
  protected function textFormatElement(): array {
    return [
      '#type' => 'text_format',
      'value' => ['#type' => 'textarea'],
      'summary' => ['#type' => 'textarea'],
      'format' => [
        'format' => ['#type' => 'select'],
      ],
    ];
  }

  /**
   * Builds an empty single cardinality managed file element.
   *
   * @return array
   *   The managed file element.
   */
  protected function managedFileElement(): array {
    return [
      '#title' => 'Image',
      '#required' => TRUE,
      '#cardinality' => 1,
      '#default_value' => ['fids' => []],
      'upload' => ['#type' => 'file'],
      'upload_button' => ['#type' => 'submit'],
      'remove_button' => ['#type' => 'submit'],
    ];
  }

  /**
   * Returns the attached head element registered under the given key.
   *
   * @param array $page
   *   The page render array the head elements were attached to.
   * @param string $key
   *   The key the head element was registered under.
   *
   * @return array
   *   The head element render array.
   */
  protected function htmlHeadElement(array $page, string $key): array {
    foreach ($page['#attached']['html_head'] ?? [] as $element) {
      if (($element[1] ?? NULL) === $key) {
        return $element[0];
      }
    }
    $this->fail(sprintf('No head element is attached under the key %s.', $key));
  }

}
