<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\default_admin\Hook\ViewsHooks;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the views hook implementations of the theme.
 *
 * @see \Drupal\default_admin\Hook\ViewsHooks::viewsUiDisplayTabAlter()
 * @see \Drupal\default_admin\Hook\ViewsHooks::viewsUiDisplayTopAlter()
 * @see \Drupal\default_admin\Hook\ViewsHooks::viewsPreRender()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class ViewsHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'image',
    'media',
    'media_library',
    'system',
    'user',
    'views',
  ];

  /**
   * The hook implementations under test.
   */
  protected ViewsHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('media');
    $this->installConfig([
      'field',
      'system',
      'file',
      'image',
      'media',
      'media_library',
    ]);

    // The hook class is stateless and takes no services, so it can be called
    // directly instead of installing the theme and going through the theme
    // manager. That keeps the assertions about this theme alone, rather than
    // about the sum of every views_pre_render implementation that happens to
    // be enabled.
    $this->hooks = new ViewsHooks();
  }

  /**
   * Tests that the display actions are marked up as a dropbutton.
   */
  public function testDisplayTabActionsBecomeDropbutton(): void {
    $element = $this->buildDisplayTab([
      'duplicate' => $this->actionButton('duplicate'),
      'delete' => $this->actionButton('delete'),
      'duplicate_as' => [
        'page' => $this->actionButton('duplicate'),
        'block' => $this->actionButton('duplicate'),
      ],
    ]);

    $this->hooks->viewsUiDisplayTabAlter($element);
    $actions = $element['details']['top']['actions'];

    // Every button is a list item of the dropbutton and has to say so.
    $this->assertSame('<li class="duplicate dropbutton__item">', $actions['duplicate']['#prefix']);
    $this->assertSame('<li class="delete dropbutton__item">', $actions['delete']['#prefix']);
    // The "Duplicate as" buttons sit one level deeper but are list items of
    // the very same list.
    $this->assertSame('<li class="duplicate dropbutton__item">', $actions['duplicate_as']['page']['#prefix']);
    $this->assertSame('<li class="duplicate dropbutton__item">', $actions['duplicate_as']['block']['#prefix']);

    // The list itself carries the size and, because four buttons went into
    // it, the multiple variant.
    $this->assertSame('<ul class="dropbutton dropbutton--extrasmall dropbutton--multiple">', $actions['prefix']['#markup']);

    // The raw markup children are the list itself, not items in it, so they
    // are left alone.
    $this->assertSame('</ul>', $actions['suffix']['#markup']);
    $this->assertArrayNotHasKey('#prefix', $actions['prefix']);
    $this->assertArrayNotHasKey('#prefix', $actions['suffix']);
  }

  /**
   * Tests that a lone display action yields a single dropbutton.
   */
  public function testDisplayTabSingleActionDropbutton(): void {
    $element = $this->buildDisplayTab([
      'delete' => $this->actionButton('delete'),
    ]);

    $this->hooks->viewsUiDisplayTabAlter($element);
    $actions = $element['details']['top']['actions'];

    $this->assertSame('<li class="delete dropbutton__item">', $actions['delete']['#prefix']);
    $this->assertSame('<ul class="dropbutton dropbutton--extrasmall dropbutton--single">', $actions['prefix']['#markup']);
  }

  /**
   * Tests display actions that come without the list markup around them.
   */
  public function testDisplayTabActionsWithoutListMarkup(): void {
    $element = $this->buildDisplayTab([
      'delete' => $this->actionButton('delete'),
    ], FALSE);

    $this->hooks->viewsUiDisplayTabAlter($element);
    $actions = $element['details']['top']['actions'];

    // The buttons are still marked up as list items.
    $this->assertSame('<li class="delete dropbutton__item">', $actions['delete']['#prefix']);
    // There is no list to put the dropbutton variant classes on, and none may
    // be conjured up either.
    $this->assertArrayNotHasKey('prefix', $actions);
  }

  /**
   * Tests that actions next to a display title are reordered.
   */
  public function testDisplayTabActionsWeight(): void {
    $element = $this->buildDisplayTab([
      'delete' => $this->actionButton('delete'),
    ]);

    $this->hooks->viewsUiDisplayTabAlter($element);
    $top = $element['details']['top'];

    // The actions render after the display title so that the DOM order
    // matches the visual order.
    $this->assertSame(10, $top['actions']['#weight']);
    // With the actions no longer floated, the clearfix is dead weight.
    $this->assertNotContains('clearfix', $top['#attributes']['class']);
    $this->assertContains('views-ui-display-tab-actions', $top['#attributes']['class']);
  }

  /**
   * Tests that the display tab is left as is when a part is missing.
   */
  public function testDisplayTabActionsWeightUnchanged(): void {
    // Without a display title there is nothing to order the actions against.
    $element = $this->buildDisplayTab([
      'delete' => $this->actionButton('delete'),
    ]);
    unset($element['details']['top']['display_title']);

    $this->hooks->viewsUiDisplayTabAlter($element);

    $this->assertArrayNotHasKey('#weight', $element['details']['top']['actions']);
    $this->assertContains('clearfix', $element['details']['top']['#attributes']['class']);

    // A display tab can also hold a title and no actions at all, and then the
    // title keeps the whole row to itself.
    $element = $this->buildDisplayTab([], FALSE);
    unset($element['details']['top']['actions']);

    $this->hooks->viewsUiDisplayTabAlter($element);

    $this->assertContains('clearfix', $element['details']['top']['#attributes']['class']);
  }

  /**
   * Tests that the display tabs get their own classes and theme hook.
   */
  public function testDisplayTopTabs(): void {
    $element = $this->buildDisplayTop();

    $this->hooks->viewsUiDisplayTopAlter($element);

    // The display tabs are not the local task tabs of the page, so they get
    // their own class names and their own theme hook to go with them.
    $this->assertStringContainsString('class="views-tabs views-tabs--secondary"', $element['tabs']['#prefix']);
    $this->assertStringNotContainsString('class="tabs secondary"', $element['tabs']['#prefix']);
    // Only the list is renamed; the heading above it keeps its own class.
    $this->assertStringContainsString('<h2 class="visually-hidden">', $element['tabs']['#prefix']);
    $this->assertSame('menu_local_task__views_ui', $element['tabs']['page_1']['#theme']);
    $this->assertSame('menu_local_task__views_ui', $element['tabs']['block_1']['#theme']);
  }

  /**
   * Tests the rearranged top bar of the views edit form.
   */
  public function testDisplayTopExtraActionsAndAddDisplay(): void {
    $element = $this->buildDisplayTop();

    $this->hooks->viewsUiDisplayTopAlter($element);

    $this->assertSame('small', $element['extra_actions']['#dropbutton_type']);
    // Both button types of the add display dropdown are styled through one
    // selector, and the classes the views UI put there survive.
    $this->assertSame([
      'add-display',
      'views-tabs__action-list-button',
    ], $element['add_display']['page']['#attributes']['class']);
    $this->assertSame([
      'add-display',
      'views-tabs__action-list-button',
    ], $element['add_display']['block']['#attributes']['class']);

    // The extra actions move behind the add display buttons and are wrapped,
    // which positions them without floats and so without a clearfix.
    $this->assertSame(10, $element['extra_actions']['#weight']);
    $this->assertSame([
      'container' => [
        '#attributes' => [
          'class' => ['views-display-top__extra-actions-wrapper'],
        ],
      ],
    ], $element['extra_actions']['#theme_wrappers']);
    $this->assertNotContains('clearfix', $element['#attributes']['class']);
    $this->assertContains('views-display-top', $element['#attributes']['class']);
  }

  /**
   * Tests a top bar that has no add display buttons.
   */
  public function testDisplayTopWithoutAddDisplay(): void {
    $element = $this->buildDisplayTop();
    unset($element['add_display']);

    $this->hooks->viewsUiDisplayTopAlter($element);

    // The extra actions still become a small dropbutton.
    $this->assertSame('small', $element['extra_actions']['#dropbutton_type']);
    // With nothing to sit beside, they are neither moved nor wrapped, so the
    // original layout and its clearfix stay.
    $this->assertArrayNotHasKey('#weight', $element['extra_actions']);
    $this->assertArrayNotHasKey('#theme_wrappers', $element['extra_actions']);
    $this->assertContains('clearfix', $element['#attributes']['class']);
  }

  /**
   * Tests a top bar that has no extra actions.
   */
  public function testDisplayTopWithoutExtraActions(): void {
    $element = $this->buildDisplayTop();
    unset($element['extra_actions']);

    $this->hooks->viewsUiDisplayTopAlter($element);

    // The add display buttons are styled independently of the extra actions.
    $this->assertContains('views-tabs__action-list-button', $element['add_display']['page']['#attributes']['class']);
    // There is no dropbutton to reposition, so the top bar keeps its layout.
    $this->assertContains('clearfix', $element['#attributes']['class']);
  }

  /**
   * Tests the classes added to the media library page display.
   */
  public function testMediaLibraryPageDisplay(): void {
    $view = $this->initializeView('media_library', 'page');

    $this->hooks->viewsPreRender($view);

    $this->assertSame('media-library-item', $view->style_plugin->options['row_class']);
    $this->assertSame('media-library-view', $view->display_handler->getOption('css_class'));
    $this->assertSame('media-library-item__click-to-select-checkbox', $view->field['media_bulk_form']->options['element_class']);
    $this->assertSame('media-library-item__content', $view->field['rendered_entity']->options['element_class']);
    // The operations of a media item are icon links rather than text links.
    $this->assertSame('media-library-item__edit icon-link', $view->field['edit_media']->options['alter']['link_class']);
    $this->assertSame('media-library-item__remove icon-link', $view->field['delete_media']->options['alter']['link_class']);
  }

  /**
   * Tests the classes added to the media library widget display.
   */
  public function testMediaLibraryWidgetDisplay(): void {
    $view = $this->initializeView('media_library', 'widget');

    $this->hooks->viewsPreRender($view);

    $this->assertSame('media-library-item', $view->style_plugin->options['row_class']);
    $this->assertSame('media-library-view media-library-view--widget', $view->display_handler->getOption('css_class'));
    // The widget display overrides the CSS class rather than inheriting it,
    // so the widget variant must not leak to the displays that do inherit it.
    $this->assertSame('', $view->displayHandlers->get('default')->options['css_class']);

    $this->assertSame('media-library-item__content', $view->field['rendered_entity']->options['element_class']);
    // The class goes on the wrapper of the select form, because that is the
    // element the click-to-select behavior is bound to, and not on the form
    // element itself.
    $this->assertSame('media-library-item__click-to-select-checkbox', $view->field['media_library_select_form']->options['element_wrapper_class']);
    $this->assertSame('', $view->field['media_library_select_form']->options['element_class']);
  }

  /**
   * Tests that another view is left untouched.
   */
  public function testOtherViewIsUntouched(): void {
    $view = $this->initializeView('media', 'media_page_list');

    $this->hooks->viewsPreRender($view);

    // The media overview lists the same entities with the same bulk form
    // field as the media library does, but it is not a media library and must
    // not be dressed up as one.
    $this->assertSame('', $view->style_plugin->options['row_class']);
    $this->assertSame('', $view->display_handler->getOption('css_class'));
    $this->assertSame('', $view->field['media_bulk_form']->options['element_class']);
  }

  /**
   * Loads a view and initializes the handlers of one of its displays.
   *
   * @param string $view_id
   *   The ID of the view to load.
   * @param string $display_id
   *   The ID of the display to make current.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executable, in the state hook_views_pre_render() receives it in.
   */
  protected function initializeView(string $view_id, string $display_id): ViewExecutable {
    $view = Views::getView($view_id);
    $this->assertInstanceOf(ViewExecutable::class, $view);
    $view->setDisplay($display_id);
    $view->initHandlers();
    $view->initStyle();

    return $view;
  }

  /**
   * Builds the display tab of the views edit form.
   *
   * @param array $actions
   *   The buttons of the actions element, keyed by child name.
   * @param bool $with_list_markup
   *   (optional) Whether the actions element carries the raw list markup the
   *   views UI wraps the buttons in. Defaults to TRUE.
   *
   * @return array
   *   A display tab render array, as hook_views_ui_display_tab_alter()
   *   receives it.
   *
   * @see \Drupal\views_ui\ViewEditForm::getDisplayDetails()
   */
  protected function buildDisplayTab(array $actions, bool $with_list_markup = TRUE): array {
    if ($with_list_markup) {
      $actions = ['prefix' => ['#markup' => '<ul class="dropbutton">']] + $actions;
      $actions['suffix']['#markup'] = '</ul>';
    }
    $actions['#theme_wrappers'] = ['dropbutton_wrapper'];

    return [
      'details' => [
        '#theme_wrappers' => ['container'],
        '#attributes' => ['id' => 'edit-display-settings-details'],
        'top' => [
          '#theme_wrappers' => ['container'],
          '#attributes' => [
            'id' => 'edit-display-settings-top',
            'class' => [
              'views-ui-display-tab-actions',
              'edit-display-settings-top',
              'views-ui-display-tab-bucket',
              'clearfix',
            ],
          ],
          'actions' => $actions,
          'display_title' => [
            '#theme' => 'views_ui_display_tab_setting',
            '#description' => 'Display name',
          ],
        ],
      ],
    ];
  }

  /**
   * Builds the top bar of the views edit form.
   *
   * @return array
   *   A display top render array, as hook_views_ui_display_top_alter()
   *   receives it.
   *
   * @see \Drupal\views_ui\ViewEditForm::renderDisplayTop()
   */
  protected function buildDisplayTop(): array {
    return [
      '#theme_wrappers' => ['views_ui_container'],
      '#attributes' => [
        'class' => ['views-display-top', 'clearfix'],
        'id' => ['views-display-top'],
      ],
      'extra_actions' => [
        '#type' => 'dropbutton',
        '#attributes' => ['id' => 'views-display-extra-actions'],
        '#links' => [
          'analyze' => ['title' => 'Analyze view'],
          'duplicate' => ['title' => 'Duplicate view'],
        ],
      ],
      'tabs' => [
        '#prefix' => '<h2 class="visually-hidden">Secondary tabs</h2><ul id = "views-display-menu-tabs" class="tabs secondary">',
        '#suffix' => '</ul>',
        'page_1' => [
          '#theme' => 'menu_local_task',
          '#link' => ['title' => 'Page'],
        ],
        'block_1' => [
          '#theme' => 'menu_local_task',
          '#link' => ['title' => 'Block'],
        ],
      ],
      'add_display' => [
        'page' => [
          '#type' => 'submit',
          '#value' => 'Add Page',
          '#attributes' => ['class' => ['add-display']],
        ],
        'block' => [
          '#type' => 'button',
          '#value' => 'Add Block',
          '#attributes' => ['class' => ['add-display']],
        ],
      ],
    ];
  }

  /**
   * Builds one of the display action buttons of the views edit form.
   *
   * @param string $class
   *   The class of the list item the views UI wraps the button in.
   *
   * @return array
   *   A submit button render array.
   */
  protected function actionButton(string $class): array {
    return [
      '#type' => 'submit',
      '#value' => 'Action',
      '#limit_validation_errors' => [],
      '#prefix' => '<li class="' . $class . '">',
      '#suffix' => '</li>',
    ];
  }

}
