<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\default_admin\Helper;
use Drupal\default_admin\Hook\FormHooks;
use Drupal\views\Form\ViewsForm;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form alterations the theme applies to administrative forms.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::formAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formMediaFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formMediaLibraryAddFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formMenuLinkContentFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formNodeFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formSystemModulesAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formSystemThemeSettingsAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formViewsExposedFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formViewsUiAddHandlerFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::formViewsUiConfigItemFormAlter()
 * @see \Drupal\default_admin\Hook\FormHooks::stickyActionButtonsAndSidebar()
 * @see \Drupal\default_admin\Hook\FormHooks::formAfterBuild()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class FormHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'system',
    'user',
    'views',
  ];

  /**
   * The hook implementations under test.
   */
  protected FormHooks $formHooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);

    // Install and activate the theme. Helper::isContentForm() runs the
    // default_admin_content_form_routes alter through the theme manager, which
    // needs an active theme, and the theme settings form reads its defaults
    // from the configuration the theme installer writes.
    $this->container->get('theme_installer')->install(['default_admin']);
    $this->container = \Drupal::getContainer();
    $this->config('system.theme')->set('admin', 'default_admin')->save();
    \Drupal::theme()->setActiveTheme(\Drupal::service(ThemeInitializationInterface::class)->initTheme('default_admin'));

    $this->formHooks = new FormHooks(
      $this->container->get('current_route_match'),
      $this->container->get('current_user'),
    );
  }

  /**
   * Tests that the delete links of entity forms become action links.
   */
  public function testEntityFormDeleteLinksBecomeActionLinks(): void {
    $form = [
      'actions' => [
        'delete' => [
          '#type' => 'link',
          '#title' => 'Delete',
          '#url' => Url::fromRoute('<front>'),
          '#attributes' => ['class' => ['button', 'button--danger']],
        ],
        'delete_translation' => [
          '#type' => 'link',
          '#title' => 'Delete translation',
          '#url' => Url::fromRoute('<front>'),
          '#attributes' => ['class' => ['button', 'button--danger']],
        ],
      ],
    ];

    $this->formHooks->formAlter($form, $this->entityFormState(), 'node_article_edit_form');

    $expected = [
      'action-link',
      'action-link--danger',
      'action-link--icon-trash',
    ];
    $this->assertSame($expected, $form['actions']['delete']['#options']['attributes']['class']);
    $this->assertSame($expected, $form['actions']['delete_translation']['#options']['attributes']['class']);

    // The classes have to reach the URL options, because that is what the
    // renderer emits for link elements, and the element level attributes are
    // dropped so that they cannot win over them again.
    $this->assertSame($expected, $form['actions']['delete']['#url']->getOption('attributes')['class']);
    $this->assertArrayNotHasKey('#attributes', $form['actions']['delete']);
  }

  /**
   * Tests that views form overrides are laid out with flex instead of floats.
   */
  public function testViewsFormOverrideUsesFlexLayout(): void {
    $form_object = $this->createStub(ViewsForm::class);
    $form_object->method('getBaseFormId')->willReturn('views_form_content_page_1');
    $form_state = new FormState();
    $form_state->setFormObject($form_object);

    $form = [
      'override' => [
        '#prefix' => '<div class="views-override form--inline clearfix">',
        '#suffix' => '</div>',
      ],
    ];

    $this->formHooks->formAlter($form, $form_state, 'views_form_content_page_1');

    $this->assertSame('<div class="views-override form--flex clearfix">', $form['override']['#prefix']);
  }

  /**
   * Tests that delete forms get a destructive submit and a muted cancel.
   */
  public function testDeleteFormButtonVariants(): void {
    $form = [
      'actions' => [
        'submit' => ['#type' => 'submit', '#value' => 'Delete'],
        'cancel' => ['#type' => 'link', '#title' => 'Cancel'],
      ],
    ];

    $this->formHooks->formAlter($form, $this->genericFormState(), 'node_1_delete_form');

    $this->assertContains('button--danger', $form['actions']['submit']['#attributes']['class']);
    $this->assertContains('button--secondary', $form['actions']['cancel']['#attributes']['class']);
  }

  /**
   * Tests that the media form gets the theme's media form styling.
   */
  public function testMediaFormLibrary(): void {
    $form = [];

    $this->formHooks->formMediaFormAlter($form, new FormState());

    $this->assertSame(['default_admin/media-form'], $form['#attached']['library']);
  }

  /**
   * Tests the media library add form with unsaved media items.
   */
  public function testMediaLibraryAddFormWithInput(): void {
    $form = [
      'media' => [['#type' => 'container']],
      'description' => ['#type' => 'inline_template'],
    ];

    $this->formHooks->formMediaLibraryAddFormAlter($form);

    $this->assertSame([
      'media-library-add-form',
      'media-library-add-form--with-input',
    ], $form['#attributes']['class']);
    $this->assertSame(['default_admin/media_library.theme'], $form['#attached']['library']);
    $this->assertSame('<p class="media-library-add-form__description">{{ text }}</p>', $form['description']['#template']);
  }

  /**
   * Tests the media library add form without unsaved media items.
   */
  public function testMediaLibraryAddFormWithoutInput(): void {
    $form = ['description' => ['#type' => 'inline_template']];

    $this->formHooks->formMediaLibraryAddFormAlter($form);

    $this->assertSame([
      'media-library-add-form',
      'media-library-add-form--without-input',
    ], $form['#attributes']['class']);
    $this->assertArrayNotHasKey('#template', $form['description']);
  }

  /**
   * Tests the oEmbed variant of the media library add form.
   */
  public function testMediaLibraryAddFormOembed(): void {
    $form = [
      'container' => [
        '#type' => 'container',
        'url' => ['#type' => 'url'],
        'submit' => ['#type' => 'submit'],
      ],
    ];

    $this->formHooks->formMediaLibraryAddFormOembedAlter($form);

    $this->assertSame(['media-library-add-form--oembed'], $form['#attributes']['class']);
    $this->assertSame(['media-library-add-form__input-wrapper'], $form['container']['#attributes']['class']);
    $this->assertSame(['media-library-add-form-oembed-url'], $form['container']['url']['#attributes']['class']);
    $this->assertSame(['media-library-add-form-oembed-submit'], $form['container']['submit']['#attributes']['class']);
  }

  /**
   * Tests the oEmbed form before an initial URL wrapper exists.
   */
  public function testMediaLibraryAddFormOembedWithoutContainer(): void {
    $form = [];

    $this->formHooks->formMediaLibraryAddFormOembedAlter($form);

    $this->assertSame(['media-library-add-form--oembed'], $form['#attributes']['class']);
    $this->assertArrayNotHasKey('container', $form);
  }

  /**
   * Tests the upload variant of the media library add form.
   */
  public function testMediaLibraryAddFormUpload(): void {
    $form = [
      'container' => [
        '#type' => 'container',
        'upload' => ['#type' => 'managed_file'],
      ],
    ];

    $this->formHooks->formMediaLibraryAddFormUploadAlter($form);

    $this->assertSame(['media-library-add-form--upload'], $form['#attributes']['class']);
    $this->assertSame(['media-library-add-form__input-wrapper'], $form['container']['#attributes']['class']);

    // The flag keeps ThemeHooks::managedFile() from wrapping the upload widget
    // in a details element inside the media library.
    $this->assertTrue($form['container']['upload']['#do_not_wrap_in_details']);
  }

  /**
   * Tests that the menu link form is reorganized into a two column layout.
   */
  public function testMenuLinkContentForm(): void {
    $form = [
      'title' => ['#type' => 'textfield'],
      'menu_parent' => ['#type' => 'select'],
      'weight' => ['#type' => 'number', '#weight' => 5],
      'expanded' => ['#type' => 'checkbox'],
      'description' => ['#type' => 'textfield'],
    ];

    $this->formHooks->formMenuLinkContentFormAlter($form);

    $this->assertSame(['menu_link_form'], $form['#theme']);
    $this->assertSame(['default_admin/form-two-columns'], $form['#attached']['library']);

    // The sidebar column is a plain accordion container.
    $this->assertSame('container', $form['advanced']['#type']);
    $this->assertTrue($form['advanced']['#accordion']);

    // The parent selector becomes the first accordion item of the sidebar.
    $this->assertSame('advanced', $form['menu_parent']['#group']);
    $this->assertSame([
      'accordion__item',
      'entity-meta__header',
    ], $form['menu_parent']['#wrapper_attributes']['class']);
    $this->assertSame('<div class="accordion">', $form['menu_parent']['#prefix']);
    $this->assertSame('</div>', $form['menu_parent']['#suffix']);

    // Weight and expanded move into the display settings section, and the
    // weight of the weight element is dropped so it does not reorder the
    // section it was moved into.
    $this->assertSame('advanced', $form['menu_link_display_settings']['#group']);
    $this->assertTrue($form['menu_link_display_settings']['#accordion']);
    $this->assertSame('number', $form['menu_link_display_settings']['weight']['#type']);
    $this->assertArrayNotHasKey('#weight', $form['menu_link_display_settings']['weight']);
    $this->assertSame('checkbox', $form['menu_link_display_settings']['expanded']['#type']);
    $this->assertArrayNotHasKey('weight', $form);
    $this->assertArrayNotHasKey('expanded', $form);

    // The description moves into its own section.
    $this->assertSame('advanced', $form['menu_link_description']['#group']);
    $this->assertSame('textfield', $form['menu_link_description']['description']['#type']);
    $this->assertArrayNotHasKey('description', $form);
  }

  /**
   * Tests that the menu link edit form reuses the menu link form layout.
   */
  public function testMenuLinkEditFormReusesMenuLinkLayout(): void {
    $form = [
      'menu_parent' => ['#type' => 'select'],
      'weight' => ['#type' => 'number', '#weight' => 5],
    ];

    $this->formHooks->formMenuLinkEditAlter($form);

    $this->assertSame(['menu_link_form'], $form['#theme']);
    $this->assertSame('number', $form['menu_link_display_settings']['weight']['#type']);
    $this->assertArrayNotHasKey('weight', $form);
  }

  /**
   * Tests that the node form uses the two column edit layout.
   */
  public function testNodeForm(): void {
    $form = [
      'advanced' => ['#type' => 'vertical_tabs'],
      'meta' => ['#type' => 'details', '#access' => FALSE],
      'revision_information' => ['#type' => 'details'],
    ];

    $this->formHooks->formNodeFormAlter($form);

    $this->assertSame(['node_edit_form'], $form['#theme']);
    $this->assertSame(['default_admin/form-two-columns'], $form['#attached']['library']);

    // Vertical tabs are replaced by accordion containers.
    $this->assertSame('container', $form['advanced']['#type']);
    $this->assertTrue($form['advanced']['#accordion']);
    $this->assertSame('container', $form['meta']['#type']);
    $this->assertTrue($form['meta']['#access']);

    // Revision information is rendered inside the meta section.
    $this->assertSame('container', $form['revision_information']['#type']);
    $this->assertSame('meta', $form['revision_information']['#group']);
    $this->assertSame(['entity-meta__revision'], $form['revision_information']['#attributes']['class']);
  }

  /**
   * Tests that the node preview backlink becomes an action link.
   */
  public function testNodePreviewBacklink(): void {
    $form = ['backlink' => ['#type' => 'link']];

    $this->formHooks->formNodePreviewFormSelectAlter($form);

    $this->assertSame([
      'action-link',
      'action-link--icon-chevron-left',
    ], $form['backlink']['#options']['attributes']['class']);

    // Without a backlink there is nothing to style, and no empty element may
    // be created for it.
    $without_backlink = ['view_mode' => ['#type' => 'select']];
    $this->formHooks->formNodePreviewFormSelectAlter($without_backlink);
    $this->assertSame(['view_mode' => ['#type' => 'select']], $without_backlink);
  }

  /**
   * Tests the alterations of the extend (modules) form.
   */
  public function testSystemModulesForm(): void {
    $form = [
      'filters' => [
        'text' => [
          '#type' => 'search',
          '#title' => 'Filter modules',
          '#title_display' => 'invisible',
        ],
      ],
      'modules' => [
        'Core' => [
          '#type' => 'details',
          'system' => [
            'links' => [
              'help' => ['#type' => 'link'],
              'permissions' => ['#type' => 'link'],
              'configure' => ['#type' => 'link'],
              'uninstall' => ['#type' => 'link'],
            ],
          ],
        ],
        'no_package' => [
          '#type' => 'container',
          'system' => [
            'links' => [
              'help' => ['#type' => 'link'],
            ],
          ],
        ],
      ],
    ];

    $this->formHooks->formSystemModulesAlter($form);

    $this->assertSame(['modules-table-filter'], $form['filters']['#attributes']['class']);
    $this->assertSame('Filter', (string) $form['filters']['text']['#title']);
    $this->assertArrayNotHasKey('#title_display', $form['filters']['text']);

    // Package details elements are rendered as the module package listing.
    $this->assertTrue($form['modules']['Core']['#module_package_listing']);

    $links = $form['modules']['Core']['system']['links'];
    $this->assertSame([
      'action-link',
      'action-link--small',
      'action-link--icon-questionmark',
    ], $links['help']['#options']['attributes']['class']);
    $this->assertSame([
      'action-link',
      'action-link--small',
      'action-link--icon-key',
    ], $links['permissions']['#options']['attributes']['class']);
    $this->assertSame([
      'action-link',
      'action-link--small',
      'action-link--icon-cog',
    ], $links['configure']['#options']['attributes']['class']);

    // Links without a known icon, and rows that are not a package, are left
    // alone.
    $this->assertArrayNotHasKey('#options', $links['uninstall']);
    $this->assertArrayNotHasKey('#module_package_listing', $form['modules']['no_package']);
    $this->assertArrayNotHasKey('#options', $form['modules']['no_package']['system']['links']['help']);
  }

  /**
   * Tests that the theme settings form exposes the theme's own settings.
   */
  public function testSystemThemeSettingsForm(): void {
    $form = [
      'config_key' => ['#type' => 'value', '#value' => 'default_admin.settings'],
      'logo' => ['#type' => 'details', '#open' => TRUE],
      'favicon' => ['#type' => 'details', '#open' => TRUE],
      'theme_settings' => ['#type' => 'details', '#open' => TRUE],
    ];

    $this->formHooks->formSystemThemeSettingsAlter($form);

    // The theme's own settings come first, the generic sections are collapsed
    // and pushed to the bottom.
    $this->assertSame('details', $form['custom_settings']['#type']);
    $this->assertTrue($form['custom_settings']['#open']);
    $this->assertFalse($form['logo']['#open']);
    $this->assertFalse($form['favicon']['#open']);
    $this->assertFalse($form['theme_settings']['#open']);
    $this->assertSame([97, 98, 99], [
      $form['logo']['#weight'],
      $form['favicon']['#weight'],
      $form['theme_settings']['#weight'],
    ]);

    // The settings form elements and the user override switch are present.
    $this->assertArrayHasKey('enable_dark_mode', $form['custom_settings']);
    $this->assertArrayHasKey('preset_accent_color', $form['custom_settings']);
    $this->assertArrayHasKey('layout_density', $form['custom_settings']);
    $this->assertSame('checkbox', $form['custom_settings']['show_user_theme_settings']['#type']);

    $this->assertContains([FormHooks::class, 'formSystemThemeSettingsAlterValidate'], $form['#validate']);
    $this->assertContains([FormHooks::class, 'formSystemThemeSettingsAlterSubmit'], $form['#submit']);
    $this->assertSame(['default_admin/settings'], $form['#attached']['library']);
  }

  /**
   * Tests that the theme settings of other themes are left untouched.
   */
  public function testSystemThemeSettingsFormOfOtherTheme(): void {
    $other_theme = [
      'config_key' => ['#type' => 'value', '#value' => 'olivero.settings'],
      'logo' => ['#type' => 'details', '#open' => TRUE],
    ];
    $form = $other_theme;
    $this->formHooks->formSystemThemeSettingsAlter($form);
    $this->assertSame($other_theme, $form);

    // The global theme settings form has no config key at all.
    $global = ['logo' => ['#type' => 'details', '#open' => TRUE]];
    $form = $global;
    $this->formHooks->formSystemThemeSettingsAlter($form);
    $this->assertSame($global, $form);
  }

  /**
   * Tests that the permissions form filter gets a visible label.
   */
  public function testUserAdminPermissionsForm(): void {
    $form = [
      'filters' => [
        'text' => [
          '#type' => 'search',
          '#title' => 'Filter permissions',
          '#title_display' => 'invisible',
        ],
      ],
    ];

    $this->formHooks->formUserAdminPermissionsAlter($form);

    $this->assertSame(['permissions-table-filter'], $form['filters']['#attributes']['class']);
    $this->assertSame('Filter', (string) $form['filters']['text']['#title']);
    $this->assertArrayNotHasKey('#title_display', $form['filters']['text']);
  }

  /**
   * Tests that exposed forms are labeled with the view they filter.
   */
  public function testViewsExposedFormLabel(): void {
    $view = $this->createStub(ViewExecutable::class);
    $view->method('getTitle')->willReturn('Content');
    $form_state = new FormState();
    $form_state->set('view', $view);

    $form = [];
    $this->formHooks->formViewsExposedFormAlter($form, $form_state);

    $this->assertSame(
      'Filter the contents of the <em class="placeholder">Content</em> view',
      (string) $form['#attributes']['aria-label'],
    );
  }

  /**
   * Tests that the views UI add handler controls become flex children.
   */
  public function testViewsUiAddHandlerForm(): void {
    $form = [
      'selected' => [
        '#type' => 'select',
        '#attributes' => [
          'class' => [
            'container-inline',
            'views-add-form-selected',
            'views-offset-bottom',
          ],
        ],
      ],
      'override' => [
        'controls' => [
          '#type' => 'container',
          '#id' => 'views-filterable-options-controls',
          'options_search' => ['#type' => 'textfield'],
          'group' => ['#type' => 'select'],
        ],
      ],
    ];

    $this->formHooks->formViewsUiAddHandlerFormAlter($form);

    // The inline container class is dropped, the other classes stay.
    $this->assertNotContains('container-inline', $form['selected']['#attributes']['class']);
    $this->assertContains('views-add-form-selected', $form['selected']['#attributes']['class']);

    // The children of the controls wrapper are moved next to it so they share
    // a single flex parent, and they keep the wrapper's no-JavaScript hiding
    // through the js-show class.
    $this->assertArrayNotHasKey('controls', $form['override']);
    $this->assertSame('textfield', $form['override']['controls_options_search']['#type']);
    $this->assertSame('select', $form['override']['controls_group']['#type']);
    $this->assertSame(['js-show'], $form['override']['controls_options_search']['#wrapper_attributes']['class']);
    $this->assertSame(['js-show'], $form['override']['controls_group']['#wrapper_attributes']['class']);
  }

  /**
   * Tests that only the filterable options controls are hidden without JS.
   */
  public function testViewsUiAddHandlerFormOtherControls(): void {
    $form = [
      'override' => [
        'controls' => [
          '#type' => 'container',
          '#id' => 'views-other-controls',
          'options_search' => ['#type' => 'textfield'],
        ],
      ],
    ];

    $this->formHooks->formViewsUiAddHandlerFormAlter($form);

    $this->assertArrayNotHasKey('controls', $form['override']);
    $this->assertArrayNotHasKey('#wrapper_attributes', $form['override']['controls_options_search']);
  }

  /**
   * Tests the views UI filter configuration markup.
   */
  public function testViewsUiConfigItemFormFilter(): void {
    $form_state = new FormState();
    $form_state->set('type', 'filter');

    $form = [
      'options' => [
        'clear_markup_start' => ['#markup' => '<div class="clearfix">'],
        'clear_markup_end' => ['#markup' => '</div>'],
        'expose_button' => ['#prefix' => '<div class="views-expose clearfix">'],
        'group_button' => ['#prefix' => '<div class="views-group clearfix">'],
        'operator' => [
          '#prefix' => '<div class="views-group-box views-left-30">',
          '#suffix' => '</div>',
          'operator' => ['#type' => 'radios'],
        ],
        'value' => [
          '#prefix' => '<div class="views-group-box views-right-70"><div id="edit-options-value-wrapper">',
          '#suffix' => '</div></div>',
          'value' => ['#type' => 'select'],
        ],
      ],
    ];

    $this->formHooks->formViewsUiConfigItemFormAlter($form, $form_state);

    // The float based clearfix scaffolding is gone.
    $this->assertArrayNotHasKey('clear_markup_start', $form['options']);
    $this->assertArrayNotHasKey('clear_markup_end', $form['options']);
    $this->assertStringNotContainsString('clearfix', $form['options']['expose_button']['#prefix']);
    $this->assertStringNotContainsString('clearfix', $form['options']['group_button']['#prefix']);

    // Operator and value are wrapped in a common region and get semantic
    // classes instead of the width based ones.
    $this->assertSame(
      '<div class="views-config-group-region"><div class="views-group-box views-group-box--operator">',
      $form['options']['operator']['#prefix'],
    );
    $this->assertSame(
      '<div class="views-group-box views-group-box--value">',
      $form['options']['value']['#prefix'],
    );

    // The extra wrapper div of InOperator::valueForm() is removed together
    // with exactly one of its closing tags, so the markup stays balanced.
    $this->assertStringNotContainsString('edit-options-value-wrapper', $form['options']['value']['#prefix']);
    $this->assertSame('</div></div>', $form['options']['value']['#suffix']);
  }

  /**
   * Tests that a value element without a wrapper gets one.
   */
  public function testViewsUiConfigItemFormAddsValueWrapper(): void {
    $form_state = new FormState();
    $form_state->set('type', 'filter');

    $form = [
      'options' => [
        'value' => ['value' => ['#type' => 'textfield']],
      ],
    ];

    $this->formHooks->formViewsUiConfigItemFormAlter($form, $form_state);

    $this->assertSame(
      '<div class="views-group-box views-group-box--value">',
      $form['options']['value']['#prefix'],
    );
    $this->assertSame('</div>', $form['options']['value']['#suffix']);
  }

  /**
   * Tests that operator and value elements without children are removed.
   */
  public function testViewsUiConfigItemFormDropsEmptyElements(): void {
    $form_state = new FormState();
    $form_state->set('type', 'filter');

    $form = [
      'options' => [
        'operator' => [
          '#prefix' => '<div class="views-group-box views-left-30">',
          '#suffix' => '</div>',
        ],
        'value' => [],
      ],
    ];

    $this->formHooks->formViewsUiConfigItemFormAlter($form, $form_state);

    // Without children these elements would only render empty wrapper divs.
    $this->assertSame(['options' => []], $form);
  }

  /**
   * Tests that handlers other than filters keep their markup.
   */
  public function testViewsUiConfigItemFormOtherHandlerType(): void {
    $form_state = new FormState();
    $form_state->set('type', 'field');

    $original = [
      'options' => [
        'clear_markup_start' => ['#markup' => '<div class="clearfix">'],
        'value' => ['#prefix' => '<div class="views-group-box views-right-70">'],
      ],
    ];
    $form = $original;

    $this->formHooks->formViewsUiConfigItemFormAlter($form, $form_state);

    $this->assertSame($original, $form);
  }

  /**
   * Tests that exposed forms and views UI forms never get a sticky bar.
   */
  public function testStickyActionsSkippedForViewsForms(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'always');

    $exposed = ['actions' => ['submit' => ['#type' => 'submit']]];
    $this->formHooks->stickyActionButtonsAndSidebar($exposed, $this->genericFormState(), 'views_exposed_form');
    $this->assertSame(['actions' => ['submit' => ['#type' => 'submit']]], $exposed);

    $views_ui = ['actions' => ['submit' => ['#type' => 'submit']]];
    $this->formHooks->stickyActionButtonsAndSidebar($views_ui, $this->genericFormState(), 'views_ui_config_item_form');
    $this->assertSame(['actions' => ['submit' => ['#type' => 'submit']]], $views_ui);
  }

  /**
   * Tests the sticky action bar of a plain form when it is always enabled.
   */
  public function testStickyActionsAlwaysOnPlainForm(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'always');

    $form = [
      '#id' => 'system-site-information-settings',
      'actions' => ['submit' => ['#type' => 'submit']],
    ];

    $this->formHooks->stickyActionButtonsAndSidebar($form, $this->genericFormState(), 'system_site_information_settings');

    $this->assertSame(['sticky-form-actions'], $form['default_admin_sticky_actions']['#attributes']['class']);
    $this->assertSame('container', $form['default_admin_sticky_actions']['status']['#type']);
    $this->assertSame(['sticky-form-actions'], $form['actions']['#attributes']['class']);
    $this->assertSame(['has-sticky-form-actions'], $form['#attributes']['class']);
    $this->assertContains([FormHooks::class, 'formAfterBuild'], $form['#after_build']);
    $this->assertContains('default_admin/more_actions', $form['#attached']['library']);
    $this->assertStringContainsString('data-move-focus-to-sticky-bar', $form['default_admin_move_focus_to_sticky_bar']['#markup']);

    // A plain form gets no sidebar.
    $this->assertArrayNotHasKey('default_admin_sidebar', $form);
    $this->assertArrayNotHasKey('#theme', $form);
  }

  /**
   * Tests that a plain form gets nothing while the feature is off.
   */
  public function testStickyActionsNeverOnPlainForm(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'never');

    $form = [
      '#id' => 'system-site-information-settings',
      'actions' => ['submit' => ['#type' => 'submit']],
    ];

    $this->formHooks->stickyActionButtonsAndSidebar($form, $this->genericFormState(), 'system_site_information_settings');

    $this->assertSame([
      '#id' => 'system-site-information-settings',
      'actions' => ['submit' => ['#type' => 'submit']],
    ], $form);
  }

  /**
   * Tests that the content form flag leaves plain forms unchanged.
   */
  public function testStickyActionsContentFormsOnlySkipsPlainForm(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'content_forms');

    $form = [
      '#id' => 'system-site-information-settings',
      'actions' => ['submit' => ['#type' => 'submit']],
    ];

    $this->formHooks->stickyActionButtonsAndSidebar($form, $this->genericFormState(), 'system_site_information_settings');

    $this->assertSame([
      '#id' => 'system-site-information-settings',
      'actions' => ['submit' => ['#type' => 'submit']],
    ], $form);
  }

  /**
   * Tests the sidebar a content form gets without sticky action buttons.
   */
  public function testContentFormSidebarWithoutStickyButtons(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'never');

    $form = [
      '#id' => 'node-article-form',
      'title' => ['#type' => 'textfield'],
      'footer' => ['#type' => 'container', 'comment' => ['#type' => 'details']],
      'advanced' => ['#type' => 'vertical_tabs'],
      'revision_information' => ['#type' => 'details'],
      'actions' => ['submit' => ['#type' => 'submit']],
    ];

    $this->formHooks->stickyActionButtonsAndSidebar($form, $this->contentFormState(), 'node_article_form');

    // The container is needed for the sidebar toggle even though the action
    // buttons themselves do not become sticky.
    $this->assertArrayHasKey('default_admin_sticky_actions', $form);
    $this->assertContains([FormHooks::class, 'formAfterBuild'], $form['#after_build']);
    $this->assertArrayNotHasKey('#attributes', $form['actions']);
    $this->assertArrayNotHasKey('default_admin_move_focus_to_sticky_bar', $form);
    $this->assertNotContains('default_admin/more_actions', $form['#attached']['library']);

    // The sidebar toggle lives in the sticky bar, the sidebar itself in the
    // meta group.
    $this->assertStringContainsString('meta-sidebar__trigger', $form['default_admin_sticky_actions']['default_admin_sidebar_toggle']['#markup']);
    $this->assertSame('meta', $form['default_admin_sidebar']['#group']);
    $this->assertSame(['admin-sidebar'], $form['default_admin_sidebar']['#attributes']['class']);
    $this->assertStringContainsString('meta-sidebar__close', $form['default_admin_sidebar']['default_admin_sidebar_close']['#markup']);
    $this->assertStringContainsString('meta-sidebar__overlay', $form['default_admin_sidebar_overlay']['#markup']);

    // The footer is moved into the sidebar.
    $this->assertSame(['#type' => 'container', 'comment' => ['#type' => 'details']], $form['default_admin_sidebar']['footer']);

    // A meta group is provided for forms that do not have one, and the
    // advanced group becomes the accordion of the sidebar.
    $this->assertSame('advanced', $form['meta']['#group']);
    $this->assertSame('container', $form['meta']['#type']);
    $this->assertTrue($form['meta']['#access']);
    $this->assertSame(['entity-meta__header'], $form['meta']['#attributes']['class']);
    $this->assertSame(['entity-meta'], $form['advanced']['#attributes']['class']);
    $this->assertSame('container', $form['advanced']['#type']);
    $this->assertTrue($form['advanced']['#accordion']);
    $this->assertSame('meta', $form['revision_information']['#group']);

    // The form is rendered and scripted as an edit form.
    $this->assertSame(['node_edit_form'], $form['#theme']);
    $this->assertSame(['node-edit-form'], $form['#attributes']['class']);
    $this->assertContains('default_admin/edit_form', $form['#attached']['library']);
    $this->assertContains('default_admin/node-form', $form['#attached']['library']);
    $this->assertContains('default_admin/sidebar', $form['#attached']['library']);
  }

  /**
   * Tests that a content form moves its status checkbox into the sticky bar.
   */
  public function testContentFormStickyButtonsGroupStatusCheckbox(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'content_forms');

    $form = [
      '#id' => 'node-article-form',
      'status' => [
        'widget' => [
          'value' => ['#type' => 'checkbox'],
        ],
      ],
      'footer' => [],
      'actions' => ['submit' => ['#type' => 'submit']],
    ];

    $this->formHooks->stickyActionButtonsAndSidebar($form, $this->contentFormState(), 'node_article_form');

    $this->assertSame(['sticky-form-actions'], $form['actions']['#attributes']['class']);
    $this->assertSame([
      'has-sticky-form-actions',
      'node-edit-form',
    ], $form['#attributes']['class']);
    $this->assertContains('default_admin/more_actions', $form['#attached']['library']);

    // The status checkbox is grouped into the sticky bar, and keeps submitting
    // to the form it was moved out of.
    $this->assertSame('status', $form['status']['#group']);
    $this->assertSame('node-article-form', $form['status']['widget']['value']['#attributes']['form']);
    $this->assertSame('container', $form['default_admin_sticky_actions']['status']['#type']);
  }

  /**
   * Tests that anonymous users do not see authoring data on node add forms.
   */
  public function testAnonymousNodeAddFormHidesAuthoringMeta(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'never');

    $route_match = $this->createStub(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('node.add');
    $form_hooks = new FormHooks($route_match, new AnonymousUserSession());

    $form = [
      '#id' => 'node-article-form',
      'footer' => [],
      'meta' => [
        '#type' => 'details',
        'published' => ['#type' => 'item'],
        'changed' => ['#type' => 'item'],
        'author' => ['#type' => 'item'],
      ],
      'actions' => ['submit' => ['#type' => 'submit']],
    ];

    $form_hooks->stickyActionButtonsAndSidebar($form, $this->contentFormState(), 'node_article_form');

    $this->assertArrayNotHasKey('changed', $form['meta']);
    $this->assertArrayNotHasKey('author', $form['meta']);
    $this->assertArrayHasKey('published', $form['meta']);
  }

  /**
   * Tests which action buttons are copied into the sticky action bar.
   */
  public function testFormAfterBuildCopiesAllowedButtons(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'always');

    $form = [
      '#form_id' => 'node_article_form',
      '#id' => 'node-article-form',
      'actions' => [
        '#type' => 'actions',
        'submit' => $this->actionButton('submit', 'edit-submit'),
        'preview' => $this->actionButton('submit', 'edit-preview'),
        'delete' => $this->actionButton('link', 'edit-delete'),
        'other' => $this->actionButton('submit', 'edit-other'),
        'unavailable' => ['#access' => FALSE] + $this->actionButton('submit', 'edit-unavailable'),
        'custom' => ['#default_admin_action_item' => TRUE] + $this->actionButton('submit', 'edit-custom'),
      ],
      'default_admin_sticky_actions' => ['#type' => 'container'],
    ];

    $result = FormHooks::formAfterBuild($form);

    // The sticky container is handed over to the page template and must not be
    // rendered inside the form itself.
    $this->assertArrayNotHasKey('default_admin_sticky_actions', $result);
    $sticky = Helper::formActions() ?? [];
    $this->assertArrayHasKey('actions', $sticky);

    // Only submit buttons from the allowlist, plus buttons explicitly opting
    // in, are duplicated. Inaccessible buttons and links are not.
    $this->assertSame(['submit', 'preview', 'custom'], array_keys($sticky['actions']));

    // The copies are distinct elements that still submit the original form.
    $this->assertSame('sticky-edit-submit', $sticky['actions']['submit']['#attributes']['id']);
    $this->assertSame('node-article-form', $sticky['actions']['submit']['#attributes']['form']);
    $this->assertSame('sticky-edit-submit', $sticky['actions']['submit']['#attributes']['data-drupal-selector']);
    $this->assertSame('sticky-edit-submit', $sticky['actions']['submit']['#attributes']['data-sticky-form-selector']);

    // The original buttons are untouched.
    $this->assertSame('edit-submit', $result['actions']['submit']['#attributes']['data-drupal-selector']);
  }

  /**
   * Tests the media type add form exception for buttons that do not submit.
   */
  public function testFormAfterBuildMediaTypeAddForm(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'always');

    $form = [
      '#form_id' => 'media_type_add_form',
      '#id' => 'media-type-add-form',
      'actions' => [
        '#type' => 'actions',
        'submit' => $this->actionButton('button', 'edit-submit'),
      ],
      'default_admin_sticky_actions' => ['#type' => 'container'],
    ];

    FormHooks::formAfterBuild($form);

    $sticky = Helper::formActions() ?? [];
    $this->assertArrayHasKey('actions', $sticky);
    $this->assertSame('sticky-edit-submit', $sticky['actions']['submit']['#attributes']['id']);
  }

  /**
   * Tests that the sticky container is registered without sticky buttons.
   */
  public function testFormAfterBuildWithoutStickyButtons(): void {
    $this->setSetting('core_admin_theme_use_sticky_action_buttons', 'never');

    $form = [
      '#form_id' => 'node_article_form',
      '#id' => 'node-article-form',
      'actions' => [
        '#type' => 'actions',
        'submit' => $this->actionButton('submit', 'edit-submit'),
      ],
      'default_admin_sticky_actions' => [
        '#type' => 'container',
        'default_admin_sidebar_toggle' => ['#markup' => 'toggle'],
      ],
    ];

    $result = FormHooks::formAfterBuild($form);

    // The sidebar toggle still needs the container, but no button is copied.
    $this->assertSame([
      '#type' => 'container',
      'default_admin_sidebar_toggle' => ['#markup' => 'toggle'],
    ], Helper::formActions());
    $this->assertArrayNotHasKey('default_admin_sticky_actions', $result);
  }

  /**
   * Builds an action button element.
   *
   * @param string $type
   *   The element type.
   * @param string $id
   *   The element ID, which is also used as the Drupal selector.
   *
   * @return array
   *   The button element.
   */
  protected function actionButton(string $type, string $id): array {
    return [
      '#type' => $type,
      '#id' => $id,
      '#attributes' => ['data-drupal-selector' => $id],
    ];
  }

  /**
   * Builds a form state for a form that is not built by a form object.
   *
   * @return \Drupal\Core\Form\FormState
   *   The form state.
   */
  protected function genericFormState(): FormState {
    $form_state = new FormState();
    $form_state->setFormObject($this->createStub(FormInterface::class));
    return $form_state;
  }

  /**
   * Builds a form state for an entity form.
   *
   * @return \Drupal\Core\Form\FormState
   *   The form state.
   */
  protected function entityFormState(): FormState {
    $form_state = new FormState();
    $form_state->setFormObject($this->createStub(EntityForm::class));
    return $form_state;
  }

  /**
   * Builds a form state that Helper::isContentForm() accepts.
   *
   * @return \Drupal\Core\Form\FormState
   *   The form state.
   */
  protected function contentFormState(): FormState {
    $form_state = new FormState();
    $form_state->setBuildInfo(['base_form_id' => 'node_form', 'args' => []]);
    $form_state->setFormObject($this->createStub(FormInterface::class));
    return $form_state;
  }

}
