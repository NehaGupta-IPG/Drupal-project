<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\block_content\BlockContentTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\default_admin\Helper;
use Drupal\default_admin\Hook\PreprocessHooks;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the preprocess implementations of the Default Admin theme.
 *
 * The preprocessors are invoked directly, because what they guarantee to the
 * templates are the CSS classes, the attributes and the flags they put into
 * the variables.
 *
 * @see \Drupal\default_admin\Hook\PreprocessHooks
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class PreprocessHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'filter', 'language', 'node']);
    $this->container->get('theme_installer')->install(['default_admin']);
    // Installing a theme rebuilds the container.
    $this->container = \Drupal::getContainer();

    // The theme settings that the preprocessors consult are resolved against
    // the configured administration theme, so the settings of this theme are
    // only used when it is the administration theme.
    // @see \Drupal\default_admin\Settings::get()
    $this->config('system.theme')->set('admin', 'default_admin')->save();
  }

  /**
   * Tests that the content of an admin block is styled as a panel list.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessAdminBlock()
   */
  public function testAdminBlock(): void {
    $variables = [
      'block' => [
        'content' => ['#theme' => 'admin_block_content'],
      ],
    ];

    $this->hooks()->preprocessAdminBlock($variables);

    $this->assertContains('admin-list--panel', $variables['block']['content']['#attributes']['class']);
  }

  /**
   * Tests the links and the descriptions of admin block content items.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessAdminBlockContent()
   */
  public function testAdminBlockContent(): void {
    $variables = [
      'content' => [
        [
          'title' => 'People',
          'url' => Url::fromRoute('system.admin_content', [], [
            'attributes' => ['title' => 'Manage people'],
          ]),
          'description' => ['#markup' => ''],
        ],
        [
          'title' => 'Content',
          'url' => Url::fromRoute('system.admin_content'),
          'description' => ['#markup' => 'Manage content.'],
        ],
      ],
    ];

    $this->hooks()->preprocessAdminBlockContent($variables);

    $this->assertInstanceOf(Link::class, $variables['content'][0]['link']);
    $attributes = $variables['content'][0]['link']->getUrl()->getOption('attributes');
    $this->assertContains('admin-item__link', $attributes['class']);
    // The attributes the link already had have to survive the conversion.
    $this->assertSame('Manage people', $attributes['title']);

    // An empty description would render an empty element, so it is removed.
    $this->assertArrayNotHasKey('description', $variables['content'][0]);
    $this->assertSame('Manage content.', $variables['content'][1]['description']['#markup']);
  }

  /**
   * Tests that contextual links are removed from ordinary blocks.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessBlock()
   */
  public function testBlockWithoutLayoutBuilder(): void {
    $variables = [
      'attributes' => ['class' => ['block', 'contextual-region']],
      'title_suffix' => [
        'contextual_links' => ['#type' => 'contextual_links_placeholder'],
      ],
      'elements' => [
        '#contextual_links' => ['block' => ['route_parameters' => []]],
      ],
    ];

    $this->hooks()->preprocessBlock($variables);

    $this->assertArrayNotHasKey('contextual_links', $variables['title_suffix']);
    $this->assertArrayNotHasKey('#contextual_links', $variables['elements']);
    $this->assertNotContains('contextual-region', $variables['attributes']['class']);
    $this->assertContains('block', $variables['attributes']['class']);
  }

  /**
   * Tests that layout builder blocks keep their contextual links.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessBlock()
   */
  public function testBlockWithLayoutBuilder(): void {
    $variables = [
      'attributes' => ['class' => ['block', 'contextual-region']],
      'title_suffix' => [
        'contextual_links' => ['#type' => 'contextual_links_placeholder'],
      ],
      'elements' => [
        '#contextual_links' => [
          'layout_builder_block' => ['route_parameters' => []],
        ],
      ],
    ];

    $this->hooks()->preprocessBlock($variables);

    $this->assertArrayHasKey('contextual_links', $variables['title_suffix']);
    $this->assertArrayHasKey('#contextual_links', $variables['elements']);
    $this->assertContains('contextual-region', $variables['attributes']['class']);
  }

  /**
   * Tests that block type add links keep the query of the current request.
   *
   * The destination query argument is what returns the user to the page the
   * block is being added from, so it has to be carried over to the add form.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessBlockContentAddList()
   */
  public function testBlockContentAddList(): void {
    $request = Request::create('/admin/content/block/add', 'GET', [
      'destination' => '/admin/content/block',
    ]);
    $this->container->get('request_stack')->push($request);

    $block_type = $this->createStub(BlockContentTypeInterface::class);
    $block_type->method('id')->willReturn('basic');
    $block_type->method('label')->willReturn('Basic block');
    $block_type->method('getDescription')->willReturn('A basic block.');

    $variables = ['content' => ['basic' => $block_type]];

    $this->hooks()->preprocessBlockContentAddList($variables);

    $bundle = $variables['bundles']['basic'];
    $this->assertSame('Basic block', $bundle['label']);
    $this->assertInstanceOf(Link::class, $bundle['add_link']);
    $url = $bundle['add_link']->getUrl();
    $this->assertSame('block_content.add_form', $url->getRouteName());
    $this->assertSame([
      'block_content_type' => 'basic',
    ], $url->getRouteParameters());
    $this->assertSame([
      'destination' => '/admin/content/block',
    ], $url->getOption('query'));
    $this->assertSame('A basic block.', $bundle['description']['#markup']);
  }

  /**
   * Tests that erroneous date and time elements are marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDatetimeWrapper()
   */
  public function testDatetimeWrapperWithError(): void {
    $variables = [
      'element' => ['#errors' => 'The date is invalid.'],
      'title_attributes' => [],
    ];

    $this->hooks()->preprocessDatetimeWrapper($variables);

    $this->assertContains('has-error', $variables['title_attributes']['class']);
  }

  /**
   * Tests that disabled date and time elements are marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDatetimeWrapper()
   */
  public function testDatetimeWrapperWhenDisabled(): void {
    $variables = [
      'element' => ['#disabled' => TRUE],
      'title_attributes' => [],
      'description_attributes' => new Attribute(),
    ];

    $this->hooks()->preprocessDatetimeWrapper($variables);

    $this->assertContains('is-disabled', $variables['title_attributes']['class']);
    $this->assertTrue($variables['description_attributes']->hasClass('is-disabled'));
  }

  /**
   * Tests the accordion flags of details elements.
   *
   * @param string $property
   *   The element property requesting the accordion display.
   * @param string $variable
   *   The variable the template uses to render the accordion.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDetails()
   */
  #[DataProvider('providerDetailsAccordion')]
  public function testDetailsAccordion(string $property, string $variable): void {
    $variables = ['element' => [$property => TRUE]];

    $this->hooks()->preprocessDetails($variables);

    $this->assertTrue($variables[$variable]);
    $this->assertFalse($variables['disabled']);
  }

  /**
   * Provides the accordion properties of details elements.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerDetailsAccordion(): array {
    return [
      'standalone accordion' => ['#accordion', 'accordion'],
      'accordion item' => ['#accordion_item', 'accordion_item'],
    ];
  }

  /**
   * Tests that details elements expose their disabled state.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDetails()
   */
  public function testDetailsDisabled(): void {
    $variables = ['element' => ['#disabled' => TRUE]];

    $this->hooks()->preprocessDetails($variables);

    $this->assertTrue($variables['disabled']);
    $this->assertArrayNotHasKey('accordion', $variables);
    $this->assertArrayNotHasKey('accordion_item', $variables);
  }

  /**
   * Tests the required state and the errors of multiple file widgets.
   *
   * The widget renders its own error message, so the details element wrapping
   * it must not repeat it.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDetails()
   */
  public function testDetailsOfFileWidgetMultiple(): void {
    $variables = [
      'element' => [
        '#theme' => 'file_widget_multiple',
        '#errors' => 'A file is required.',
        0 => ['#required' => TRUE],
      ],
      'errors' => 'A file is required.',
      'required' => FALSE,
    ];

    $this->hooks()->preprocessDetails($variables);

    $this->assertTrue($variables['required']);
    $this->assertArrayNotHasKey('errors', $variables);
  }

  /**
   * Tests that the required state falls back to the widget itself.
   *
   * Widgets accepting a single file only have no child element to read the
   * required state from.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDetails()
   */
  public function testDetailsOfFileWidgetMultipleWithoutChildren(): void {
    $variables = [
      'element' => [
        '#theme' => 'file_widget_multiple',
        '#required' => TRUE,
      ],
      'errors' => 'A file is required.',
      'required' => FALSE,
    ];

    $this->hooks()->preprocessDetails($variables);

    $this->assertTrue($variables['required']);
    // The details element has no error of its own to compare with, so the
    // error of the widget stays.
    $this->assertSame('A file is required.', $variables['errors']);
  }

  /**
   * Tests that a details element can request a description toggle.
   *
   * The description of a text format element is rendered by its wrapper, so
   * the toggle is requested without a description being present.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessDetails()
   */
  public function testDetailsDescriptionToggle(): void {
    $variables = ['element' => ['#description_toggle' => TRUE]];

    $this->hooks()->preprocessDetails($variables);

    $this->assertTrue($variables['description_toggle']);
  }

  /**
   * Tests that empty bundle descriptions are emptied out.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessEntityAddList()
   */
  public function testEntityAddList(): void {
    $variables = [
      'bundles' => [
        'empty' => [
          'label' => 'Empty',
          'description' => ['#markup' => ''],
        ],
        'described' => [
          'label' => 'Described',
          'description' => ['#markup' => 'A description.'],
        ],
      ],
    ];

    $this->hooks()->preprocessEntityAddList($variables);

    $this->assertSame([], $variables['bundles']['empty']['description']);
    $this->assertSame([
      '#markup' => 'A description.',
    ], $variables['bundles']['described']['description']);
  }

  /**
   * Tests the label of a multiple value field widget.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFieldMultipleValueForm()
   */
  public function testFieldMultipleValueForm(): void {
    $variables = [
      'element' => [],
      'multiple' => TRUE,
      'table' => [
        '#attributes' => [],
        '#header' => [
          [
            'data' => [
              '#type' => 'label',
              '#title' => 'Tags',
              '#attributes' => new Attribute(['class' => ['label']]),
            ],
          ],
          'Order',
        ],
      ],
    ];

    $this->hooks()->preprocessFieldMultipleValueForm($variables);

    $label_attributes = $variables['table']['#header'][0]['data']['#attributes'];
    $this->assertFalse($label_attributes->hasClass('label'));
    $this->assertTrue($label_attributes->hasClass('form-item__label'));
    $this->assertTrue($label_attributes->hasClass('form-item__label--multiple-value-form'));

    // An enabled widget keeps its table and its header cells untouched.
    $this->assertFalse($variables['disabled']);
    $this->assertSame([], $variables['table']['#attributes']);
    $this->assertSame('Order', $variables['table']['#header'][1]);
  }

  /**
   * Tests that a disabled multiple value field widget is marked as such.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFieldMultipleValueForm()
   */
  public function testFieldMultipleValueFormWhenDisabled(): void {
    $variables = [
      'element' => ['#disabled' => TRUE],
      'multiple' => TRUE,
      'table' => [
        '#attributes' => [],
        '#header' => [
          [
            'data' => [
              '#type' => 'label',
              '#title' => 'Tags',
              '#attributes' => new Attribute(['class' => ['label']]),
            ],
          ],
          'Order',
        ],
      ],
    ];

    $this->hooks()->preprocessFieldMultipleValueForm($variables);

    $this->assertTrue($variables['disabled']);
    $this->assertSame([
      'tabledrag-disabled',
      'js-tabledrag-disabled',
    ], $variables['table']['#attributes']['class']);

    $this->assertContains('is-disabled', $variables['table']['#header'][0]['class']);
    // A header cell that is not an array yet has to be converted into one, so
    // that it can carry the CSS class.
    $this->assertSame([
      'data' => 'Order',
      'class' => ['is-disabled'],
    ], $variables['table']['#header'][1]);
  }

  /**
   * Tests that erroneous composite form elements are marked.
   *
   * @param string $type
   *   The #type of the fieldset.
   * @param bool $expected
   *   Whether the legend is expected to be marked as erroneous.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFieldset()
   */
  #[DataProvider('providerFieldsetError')]
  public function testFieldsetError(string $type, bool $expected): void {
    $variables = [
      'element' => [
        '#type' => $type,
        '#children_errors' => ['A choice is required.'],
      ],
      'legend_span' => ['attributes' => new Attribute()],
    ];

    $this->hooks()->preprocessFieldset($variables);

    $this->assertSame($expected, $variables['legend_span']['attributes']->hasClass('has-error'));
  }

  /**
   * Provides fieldset types with child errors.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerFieldsetError(): array {
    return [
      'checkboxes' => ['checkboxes', TRUE],
      'radios' => ['radios', TRUE],
      // Only composite elements render the errors of their children on their
      // own legend.
      'actions' => ['actions', FALSE],
    ];
  }

  /**
   * Tests that disabled fieldsets are marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFieldset()
   */
  public function testFieldsetWhenDisabled(): void {
    $variables = [
      'element' => ['#type' => 'fieldset', '#disabled' => TRUE],
      'legend_span' => ['attributes' => new Attribute()],
      'description' => ['attributes' => new Attribute()],
    ];

    $this->hooks()->preprocessFieldset($variables);

    $this->assertTrue($variables['legend_span']['attributes']->hasClass('is-disabled'));
    $this->assertTrue($variables['description']['attributes']->hasClass('is-disabled'));
  }

  /**
   * Tests that the inline container class becomes a template flag.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFieldset()
   */
  public function testFieldsetInlineItems(): void {
    $variables = [
      'element' => ['#type' => 'fieldset'],
      'legend_span' => ['attributes' => new Attribute()],
      'attributes' => ['class' => ['form-wrapper', 'container-inline']],
    ];

    $this->hooks()->preprocessFieldset($variables);

    $this->assertTrue($variables['inline_items']);
    $this->assertNotContains('container-inline', $variables['attributes']['class']);
    $this->assertContains('form-wrapper', $variables['attributes']['class']);
  }

  /**
   * Tests that a fieldset without the inline class gets no flag.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFieldset()
   */
  public function testFieldsetWithoutInlineItems(): void {
    $variables = [
      'element' => ['#type' => 'fieldset'],
      'legend_span' => ['attributes' => new Attribute()],
      'attributes' => ['class' => ['form-wrapper']],
    ];

    $this->hooks()->preprocessFieldset($variables);

    $this->assertArrayNotHasKey('inline_items', $variables);
    $this->assertSame(['form-wrapper'], $variables['attributes']['class']);
  }

  /**
   * Tests the upload availability class of multiple file widgets.
   *
   * The class is what removes the border of the last table row, so it has to
   * tell whether a file can still be uploaded.
   *
   * @param array $element
   *   The widget element.
   * @param string $expected_class
   *   The expected variant CSS class of the table.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFileWidgetMultiple()
   */
  #[DataProvider('providerFileWidgetMultipleUpload')]
  public function testFileWidgetMultipleUpload(array $element, string $expected_class): void {
    $variables = [
      'element' => $element,
      'table' => [
        '#type' => 'table',
        '#header' => ['File name'],
        '#rows' => [['data' => [['data' => 'first.txt']]]],
      ],
    ];

    $this->hooks()->preprocessFileWidgetMultiple($variables);

    $this->assertContains('table-file-multiple-widget', $variables['table']['#attributes']['class']);
    $this->assertContains($expected_class, $variables['table']['#attributes']['class']);
  }

  /**
   * Provides multiple file widgets with and without upload elements.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerFileWidgetMultipleUpload(): array {
    return [
      'accessible upload' => [
        [0 => ['upload' => ['#access' => TRUE]]],
        'table-file-multiple-widget--has-upload',
      ],
      'upload without access information' => [
        [0 => ['upload' => []]],
        'table-file-multiple-widget--has-upload',
      ],
      'inaccessible upload' => [
        [0 => ['upload' => ['#access' => FALSE]]],
        'table-file-multiple-widget--no-upload',
      ],
    ];
  }

  /**
   * Tests that the operations cells of multiple file widgets are marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFileWidgetMultiple()
   */
  public function testFileWidgetMultipleOperationsCell(): void {
    $variables = [
      'element' => [0 => ['upload' => ['#access' => FALSE]]],
      'table' => [
        '#type' => 'table',
        '#header' => ['File name', 'Operations'],
        '#rows' => [
          [
            'data' => [
              ['data' => 'first.txt'],
              [
                'data' => [
                  [
                    '#type' => 'submit',
                    '#attributes' => ['class' => ['remove-button']],
                  ],
                ],
              ],
            ],
          ],
          [
            'data' => [
              ['data' => 'second.txt'],
              [
                'data' => [
                  [
                    '#type' => 'submit',
                    '#attributes' => ['class' => ['upload-button']],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $this->hooks()->preprocessFileWidgetMultiple($variables);

    $this->assertContains('file-operations-cell', $variables['table']['#rows'][0]['data'][1]['class']);
    // Only cells holding a remove button are operations cells.
    $this->assertArrayNotHasKey('class', $variables['table']['#rows'][1]['data'][1]);
  }

  /**
   * Tests when the table of a multiple file widget is rendered.
   *
   * @param array $rows
   *   The rows of the table.
   * @param bool|null $access
   *   The value of the #access property of the table, or NULL to omit it.
   * @param bool $expected
   *   Whether the table is expected to be rendered.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFileWidgetMultiple()
   */
  #[DataProvider('providerFileWidgetMultipleHasTable')]
  public function testFileWidgetMultipleHasTable(array $rows, ?bool $access, bool $expected): void {
    $variables = [
      'element' => [0 => ['upload' => ['#access' => TRUE]]],
      'table' => [
        '#type' => 'table',
        '#header' => ['File name'],
        '#rows' => $rows,
      ],
    ];

    if ($access !== NULL) {
      $variables['table']['#access'] = $access;
    }

    $this->hooks()->preprocessFileWidgetMultiple($variables);

    $this->assertSame($expected, $variables['has_table']);
  }

  /**
   * Provides multiple file widget tables.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerFileWidgetMultipleHasTable(): array {
    return [
      'accessible table with rows' => [
        [['data' => [['data' => 'first.txt']]]],
        NULL,
        TRUE,
      ],
      'table without rows' => [[], NULL, FALSE],
      'inaccessible table' => [
        [['data' => [['data' => 'first.txt']]]],
        FALSE,
        FALSE,
      ],
    ];
  }

  /**
   * Tests that the label of an erroneous form element is marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFormElement()
   */
  public function testFormElementWithError(): void {
    $variables = [
      'element' => ['#errors' => 'This value is not valid.'],
      'disabled' => FALSE,
      'label' => ['#type' => 'label'],
    ];

    $this->hooks()->preprocessFormElement($variables);

    $this->assertContains('has-error', $variables['label']['#attributes']['class']);
  }

  /**
   * Tests that the label of a disabled form element is marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFormElement()
   */
  public function testFormElementWhenDisabled(): void {
    $variables = [
      'element' => [],
      'disabled' => TRUE,
      'label' => ['#type' => 'label'],
      'description' => ['attributes' => new Attribute()],
    ];

    $this->hooks()->preprocessFormElement($variables);

    $this->assertContains('is-disabled', $variables['label']['#attributes']['class']);
    $this->assertTrue($variables['description']['attributes']->hasClass('is-disabled'));
  }

  /**
   * Tests the description toggle of a form element.
   *
   * The description is hidden and the display it would have had is handed to
   * the toggle, so that the toggle can restore it.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFormElement()
   */
  public function testFormElementDescriptionToggle(): void {
    $variables = [
      'element' => ['#description_toggle' => TRUE],
      'disabled' => FALSE,
      'label' => ['#type' => 'label'],
      'description' => [
        'attributes' => new Attribute(),
        'content' => 'Help text.',
      ],
    ];

    $this->hooks()->preprocessFormElement($variables);

    $this->assertTrue($variables['description_toggle']);
    $this->assertSame('invisible', $variables['description_display']);
    $this->assertSame('after', $variables['description_display_toggle']);
  }

  /**
   * Tests that a form element without a toggle keeps its description.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessFormElement()
   */
  public function testFormElementWithoutDescriptionToggle(): void {
    $variables = [
      'element' => [],
      'disabled' => FALSE,
      'label' => ['#type' => 'label'],
      'description' => [
        'attributes' => new Attribute(),
        'content' => 'Help text.',
      ],
    ];

    $this->hooks()->preprocessFormElement($variables);

    $this->assertArrayNotHasKey('description_toggle', $variables);
    $this->assertArrayNotHasKey('description_display', $variables);
  }

  /**
   * Tests the CSS classes of text-like inputs.
   *
   * @param string $type_html
   *   The value of the type HTML attribute.
   * @param string $type_api
   *   The #type of the form element.
   * @param string $expected_type_class
   *   The expected CSS class naming the type attribute.
   * @param string $expected_api_class
   *   The expected CSS class naming the element type.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessInput()
   */
  #[DataProvider('providerInputTextTypes')]
  public function testInputTextTypes(string $type_html, string $type_api, string $expected_type_class, string $expected_api_class): void {
    $variables = [
      'element' => ['#type' => $type_api],
      'attributes' => ['type' => $type_html],
    ];

    $this->hooks()->preprocessInput($variables);

    $this->assertContains('form-element', $variables['attributes']['class']);
    $this->assertContains($expected_type_class, $variables['attributes']['class']);
    $this->assertContains($expected_api_class, $variables['attributes']['class']);
    $this->assertNotContains('form-boolean', $variables['attributes']['class']);
  }

  /**
   * Provides the input types that are styled as text-like form elements.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerInputTextTypes(): array {
    return [
      'text' => [
        'text',
        'textfield',
        'form-element--type-text',
        'form-element--api-textfield',
      ],
      'email' => [
        'email',
        'email',
        'form-element--type-email',
        'form-element--api-email',
      ],
      'tel' => [
        'tel',
        'tel',
        'form-element--type-tel',
        'form-element--api-tel',
      ],
      'number' => [
        'number',
        'number',
        'form-element--type-number',
        'form-element--api-number',
      ],
      'search' => [
        'search',
        'search',
        'form-element--type-search',
        'form-element--api-search',
      ],
      'password' => [
        'password',
        'password',
        'form-element--type-password',
        'form-element--api-password',
      ],
      'date' => [
        'date',
        'date',
        'form-element--type-date',
        'form-element--api-date',
      ],
      'time' => [
        'time',
        'datetime',
        'form-element--type-time',
        'form-element--api-datetime',
      ],
      'file' => [
        'file',
        'file',
        'form-element--type-file',
        'form-element--api-file',
      ],
      'color' => [
        'color',
        'color',
        'form-element--type-color',
        'form-element--api-color',
      ],
      'local date and time' => [
        'datetime-local',
        'datetime',
        'form-element--type-datetime-local',
        'form-element--api-datetime',
      ],
      'url' => [
        'url',
        'url',
        'form-element--type-url',
        'form-element--api-url',
      ],
      'month' => [
        'month',
        'month',
        'form-element--type-month',
        'form-element--api-month',
      ],
      'week' => [
        'week',
        'week',
        'form-element--type-week',
        'form-element--api-week',
      ],
      // Element types are not CSS classes, so they have to be converted.
      'machine name' => [
        'text',
        'machine_name',
        'form-element--type-text',
        'form-element--api-machine-name',
      ],
    ];
  }

  /**
   * Tests that inputs that are not text-like are not styled as such.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessInput()
   */
  public function testInputNonTextType(): void {
    $variables = [
      'element' => ['#type' => 'submit'],
      'attributes' => ['type' => 'submit'],
    ];

    $this->hooks()->preprocessInput($variables);

    $this->assertArrayNotHasKey('class', $variables['attributes']);
  }

  /**
   * Tests the CSS classes of checkboxes and radio buttons.
   *
   * @param string $type_html
   *   The value of the type HTML attribute.
   * @param string $expected_class
   *   The expected CSS class naming the type attribute.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessInput()
   */
  #[DataProvider('providerInputBooleanTypes')]
  public function testInputBooleanTypes(string $type_html, string $expected_class): void {
    $variables = [
      'element' => ['#type' => $type_html],
      'attributes' => ['type' => $type_html],
    ];

    $this->hooks()->preprocessInput($variables);

    $this->assertContains('form-boolean', $variables['attributes']['class']);
    $this->assertContains($expected_class, $variables['attributes']['class']);
    // Boolean inputs are styled on their own, not as text-like elements.
    $this->assertNotContains('form-element', $variables['attributes']['class']);
  }

  /**
   * Provides the input types that are styled as boolean form elements.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerInputBooleanTypes(): array {
    return [
      'checkbox' => ['checkbox', 'form-boolean--type-checkbox'],
      'radio' => ['radio', 'form-boolean--type-radio'],
    ];
  }

  /**
   * Tests the loading message of inputs with autocompletion.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessInput()
   */
  public function testInputAutocompleteMessage(): void {
    $variables = [
      'element' => [
        '#type' => 'textfield',
        '#autocomplete_route_name' => 'system.entity_autocomplete',
      ],
      'attributes' => ['type' => 'text'],
    ];

    $this->hooks()->preprocessInput($variables);

    $this->assertSame('Loading…', (string) $variables['autocomplete_message']);
  }

  /**
   * Tests that inputs without autocompletion have no loading message.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessInput()
   */
  public function testInputWithoutAutocompleteMessage(): void {
    $variables = [
      'element' => ['#type' => 'textfield'],
      'attributes' => ['type' => 'text'],
    ];

    $this->hooks()->preprocessInput($variables);

    $this->assertArrayNotHasKey('autocomplete_message', $variables);
  }

  /**
   * Tests that a visually hidden title becomes the title attribute.
   *
   * @param string $title_display
   *   The value of the #title_display property.
   * @param string|null $expected
   *   The expected title attribute, or NULL if none is expected.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessInput()
   */
  #[DataProvider('providerInputTitleDisplay')]
  public function testInputTitleAttribute(string $title_display, ?string $expected): void {
    $variables = [
      'element' => [
        '#type' => 'search',
        '#title' => 'Search',
        '#title_display' => $title_display,
      ],
      'attributes' => ['type' => 'search'],
    ];

    $this->hooks()->preprocessInput($variables);

    if ($expected === NULL) {
      $this->assertArrayNotHasKey('title', $variables['attributes']);
      return;
    }
    $this->assertSame($expected, $variables['attributes']['title']);
  }

  /**
   * Provides the title displays of inputs.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerInputTitleDisplay(): array {
    return [
      'title as attribute' => ['attribute', 'Search'],
      // A title that is rendered as a label must not be repeated.
      'title before the input' => ['before', NULL],
    ];
  }

  /**
   * Tests that theme operation links are converted to action links.
   *
   * The operations of the appearance page are rendered by the links template,
   * which reads the options of the link element, so the conversion has to end
   * up there.
   *
   * @param string $route_name
   *   The route of the operation link.
   * @param array $route_parameters
   *   The route parameters of the operation link.
   * @param string $expected_icon_class
   *   The expected icon CSS class of the action link.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessLinks()
   * @see \Drupal\default_admin\Helper::convertLinkToActionLink()
   */
  #[DataProvider('providerThemeOperationLinks')]
  public function testLinksOfThemeOperations(string $route_name, array $route_parameters, string $expected_icon_class): void {
    $variables = [
      'links' => [
        [
          'link' => [
            '#type' => 'link',
            '#title' => 'Operation',
            '#options' => ['attributes' => ['title' => 'Operation on Stark']],
            '#url' => Url::fromRoute($route_name, $route_parameters),
          ],
          'text' => 'Operation',
          'attributes' => new Attribute(),
        ],
      ],
    ];

    $this->hooks()->preprocessLinks($variables);

    $attributes = $variables['links'][0]['link']['#options']['attributes'];
    $this->assertContains('action-link', $attributes['class']);
    $this->assertContains('action-link--small', $attributes['class']);
    $this->assertContains($expected_icon_class, $attributes['class']);
    // The attributes the link already had have to survive the conversion.
    $this->assertSame('Operation on Stark', $attributes['title']);
  }

  /**
   * Provides the theme operation links of the appearance page.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerThemeOperationLinks(): array {
    return [
      'theme settings' => [
        'system.theme_settings_theme',
        ['theme' => 'stark'],
        'action-link--icon-cog',
      ],
      'uninstall' => [
        'system.theme_uninstall',
        [],
        'action-link--icon-ex',
      ],
      'set as default' => [
        'system.theme_set_default',
        [],
        'action-link--icon-checkmark',
      ],
      'install' => [
        'system.theme_install',
        [],
        'action-link--icon-plus',
      ],
    ];
  }

  /**
   * Tests that links to other routes and to external pages are untouched.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessLinks()
   */
  public function testLinksOfOtherDestinations(): void {
    $variables = [
      'links' => [
        'external' => [
          'link' => [
            '#type' => 'link',
            '#title' => 'Documentation',
            '#options' => [],
            '#url' => Url::fromUri('https://www.drupal.org'),
          ],
          'attributes' => new Attribute(),
        ],
        'routed' => [
          'link' => [
            '#type' => 'link',
            '#title' => 'Content',
            '#options' => [],
            '#url' => Url::fromRoute('system.admin_content'),
          ],
          'attributes' => new Attribute(),
        ],
      ],
    ];

    $this->hooks()->preprocessLinks($variables);

    $this->assertSame([], $variables['links']['external']['link']['#options']);
    $this->assertSame([], $variables['links']['routed']['link']['#options']);
  }

  /**
   * Tests that the keys of link items become CSS classes.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessLinks()
   */
  public function testLinksKeysAsClasses(): void {
    $variables = [
      'links' => [
        'theme_settings' => ['attributes' => new Attribute()],
        0 => ['attributes' => new Attribute()],
      ],
    ];

    $this->hooks()->preprocessLinks($variables);

    $this->assertTrue($variables['links']['theme_settings']['attributes']->hasClass('theme-settings'));
    // Delta keys are no meaningful classes.
    $this->assertSame([], $variables['links'][0]['attributes']->toArray());
  }

  /**
   * Tests the CSS classes of action link lists.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessLinksActionLinks()
   */
  public function testLinksActionLinks(): void {
    $variables = [
      'attributes' => [],
      'links' => [
        ['attributes' => new Attribute()],
        ['attributes' => new Attribute()],
      ],
    ];

    $this->hooks()->preprocessLinksActionLinks($variables);

    $this->assertContains('action-links', $variables['attributes']['class']);
    $this->assertTrue($variables['links'][0]['attributes']->hasClass('action-links__item'));
    $this->assertTrue($variables['links'][1]['attributes']->hasClass('action-links__item'));
  }

  /**
   * Tests the variant CSS class of dropbuttons.
   *
   * A dropbutton holding a single link is rendered as a plain button, and the
   * class is what keeps it from flashing up as a dropbutton first.
   *
   * @param int $count
   *   The number of links in the dropbutton.
   * @param string $expected_class
   *   The expected variant CSS class of the dropbutton.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessLinksDropbutton()
   */
  #[DataProvider('providerDropbuttonLinkCount')]
  public function testLinksDropbutton(int $count, string $expected_class): void {
    $variables = ['attributes' => [], 'links' => []];
    for ($delta = 0; $delta < $count; $delta++) {
      $variables['links'][$delta] = ['attributes' => new Attribute()];
    }

    $this->hooks()->preprocessLinksDropbutton($variables);

    $this->assertContains($expected_class, $variables['attributes']['class']);
    foreach ($variables['links'] as $link) {
      $this->assertTrue($link['attributes']->hasClass('dropbutton__item'));
    }
  }

  /**
   * Provides the link counts of dropbuttons.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerDropbuttonLinkCount(): array {
    return [
      'single link' => [1, 'dropbutton--single'],
      'multiple links' => [2, 'dropbutton--multiple'],
    ];
  }

  /**
   * Tests the CSS classes of local action links.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessMenuLocalAction()
   */
  public function testMenuLocalAction(): void {
    $variables = [
      'attributes' => [],
      'link' => [
        '#type' => 'link',
        '#title' => 'Add content',
        '#options' => [
          'attributes' => ['class' => ['button', 'button-action']],
        ],
        '#url' => Url::fromRoute('node.add_page'),
      ],
    ];

    $this->hooks()->preprocessMenuLocalAction($variables);

    $classes = $variables['link']['#options']['attributes']['class'];
    $this->assertContains('button--primary', $classes);
    // The legacy modifier class is replaced by the current one.
    $this->assertContains('button--action', $classes);
    $this->assertNotContains('button-action', $classes);
    $this->assertContains('local-actions__item', $variables['attributes']['class']);
  }

  /**
   * Tests that local actions are secondary next to sticky form actions.
   *
   * Content forms render their own primary button in the sticky form actions,
   * and two primary buttons on one page would compete with each other.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessMenuLocalAction()
   */
  public function testMenuLocalActionWithFormActions(): void {
    Helper::formActions(['#type' => 'actions']);

    $variables = [
      'attributes' => [],
      'link' => [
        '#type' => 'link',
        '#title' => 'Add content',
        '#options' => [
          'attributes' => ['class' => ['button', 'button-action']],
        ],
        '#url' => Url::fromRoute('node.add_page'),
      ],
    ];

    $this->hooks()->preprocessMenuLocalAction($variables);

    $classes = $variables['link']['#options']['attributes']['class'];
    $this->assertNotContains('button--primary', $classes);
    $this->assertContains('button--action', $classes);
  }

  /**
   * Tests the CSS classes and the level of local tasks.
   *
   * @param bool $is_active
   *   Whether the local task is the active one.
   * @param bool $expected
   *   Whether the link is expected to be marked as active.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessMenuLocalTask()
   */
  #[DataProvider('providerMenuLocalTaskActiveState')]
  public function testMenuLocalTask(bool $is_active, bool $expected): void {
    $variables = [
      'element' => ['#level' => 'primary'],
      'is_active' => $is_active,
      'link' => [
        '#type' => 'link',
        '#title' => 'View',
        '#options' => [],
        '#url' => Url::fromRoute('system.admin_content'),
      ],
    ];

    $this->hooks()->preprocessMenuLocalTask($variables);

    $classes = $variables['link']['#options']['attributes']['class'];
    $this->assertContains('tabs__link', $classes);
    $this->assertContains('js-tabs-link', $classes);
    $this->assertSame($expected, in_array('is-active', $classes, TRUE));
    // The template needs the level to render the right kind of tab.
    $this->assertSame('primary', $variables['level']);
  }

  /**
   * Provides the active states of local tasks.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerMenuLocalTaskActiveState(): array {
    return [
      'active task' => [TRUE, TRUE],
      'inactive task' => [FALSE, FALSE],
    ];
  }

  /**
   * Tests the library and the levels of primary local tasks.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessMenuLocalTasks()
   */
  public function testMenuLocalTasksPrimary(): void {
    $variables = [
      'primary' => [
        0 => ['#theme' => 'menu_local_task'],
        1 => ['#theme' => 'menu_local_task'],
      ],
      'secondary' => [],
    ];

    $this->hooks()->preprocessMenuLocalTasks($variables);

    $this->assertSame([
      'library' => ['default_admin/drupal.nav-tabs'],
    ], $variables['primary']['#attached']);
    $this->assertSame('primary', $variables['primary'][0]['#level']);
    $this->assertSame('primary', $variables['primary'][1]['#level']);
    $this->assertArrayNotHasKey('#attached', $variables['secondary']);
  }

  /**
   * Tests the library and the levels of secondary local tasks.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessMenuLocalTasks()
   */
  public function testMenuLocalTasksSecondary(): void {
    $variables = [
      'primary' => [],
      'secondary' => [
        0 => ['#theme' => 'menu_local_task'],
      ],
    ];

    $this->hooks()->preprocessMenuLocalTasks($variables);

    $this->assertSame([
      'library' => ['default_admin/drupal.nav-tabs'],
    ], $variables['secondary']['#attached']);
    $this->assertSame('secondary', $variables['secondary'][0]['#level']);
    $this->assertArrayNotHasKey('#attached', $variables['primary']);
  }

  /**
   * Tests the bundle list of the node add page.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessNodeAddList()
   */
  public function testNodeAddList(): void {
    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'Use articles for time-sensitive content.',
    ])->save();
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

    $node_types = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->loadMultiple(['article', 'page']);
    $variables = ['attributes' => [], 'content' => $node_types];

    $this->hooks()->preprocessNodeAddList($variables);

    $article = $variables['bundles']['article'];
    $this->assertSame('Article', $article['label']);
    $this->assertInstanceOf(Link::class, $article['add_link']);
    $this->assertSame('node.add', $article['add_link']->getUrl()->getRouteName());
    $this->assertSame([
      'node_type' => 'article',
    ], $article['add_link']->getUrl()->getRouteParameters());
    $this->assertSame('Use articles for time-sensitive content.', $article['description']['#markup']);

    // A bundle without a description must not render an empty one.
    $this->assertSame([], $variables['bundles']['page']['description']);
    $this->assertContains('node-type-list', $variables['attributes']['class']);
  }

  /**
   * Tests the page title of a node edit form.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessPageTitle()
   */
  public function testPageTitleOfNodeEditForm(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $node = Node::create(['type' => 'article', 'title' => 'Test node']);
    $node->save();

    $variables = [
      'title' => 'Edit Article Test node',
      'title_attributes' => [],
    ];
    $route = new Route('/node/{node}/edit');
    $route_match = new RouteMatch('entity.node.edit_form', $route, [
      'node' => $node,
    ]);
    $this->hooks($route_match)->preprocessPageTitle($variables);

    $this->assertSame('Test node', $variables['title']);
    $this->assertSame([], $variables['title_attributes']);
  }

  /**
   * Tests the page title of the edit form of a translation.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessPageTitle()
   */
  public function testPageTitleOfNodeTranslationEditForm(): void {
    ConfigurableLanguage::createFromLangcode('es')->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $node = Node::create(['type' => 'article', 'title' => 'Test node']);
    $node->addTranslation('es', ['title' => 'Translated node']);
    $node->save();
    $translation = $node->getTranslation('es');

    $variables = [
      'title' => 'Edit Article Test node',
      'title_attributes' => [],
    ];
    $route = new Route('/node/{node}/edit');
    $route_match = new RouteMatch('entity.node.edit_form', $route, [
      'node' => $translation,
    ]);
    $this->hooks($route_match)->preprocessPageTitle($variables);

    $this->assertSame('Translated node <span class="page-title__language">(Spanish translation)</span>', (string) $variables['title']);
    $this->assertContains('page-title--is-translation', $variables['title_attributes']['class']);
  }

  /**
   * Tests the CSS classes of select elements.
   *
   * @param bool $multiple
   *   Whether the select element accepts multiple values.
   * @param string $expected_class
   *   The expected variant CSS class of the select element.
   * @param string $unexpected_class
   *   The variant CSS class the select element must not have.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessSelect()
   */
  #[DataProvider('providerSelectMultiple')]
  public function testSelect(bool $multiple, string $expected_class, string $unexpected_class): void {
    $variables = [
      'element' => ['#type' => 'select', '#multiple' => $multiple],
      'attributes' => [],
    ];

    $this->hooks()->preprocessSelect($variables);

    $this->assertContains('form-element', $variables['attributes']['class']);
    $this->assertContains($expected_class, $variables['attributes']['class']);
    $this->assertNotContains($unexpected_class, $variables['attributes']['class']);
    $this->assertNotContains('form-element--extrasmall', $variables['attributes']['class']);
  }

  /**
   * Provides select elements accepting single and multiple values.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerSelectMultiple(): array {
    return [
      'single value' => [
        FALSE,
        'form-element--type-select',
        'form-element--type-select-multiple',
      ],
      'multiple values' => [
        TRUE,
        'form-element--type-select-multiple',
        'form-element--type-select',
      ],
    ];
  }

  /**
   * Tests that selects of the block layout page are rendered smaller.
   *
   * @param string $class
   *   The CSS class marking the select element of the block layout page.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessSelect()
   */
  #[DataProvider('providerSelectExtraSmall')]
  public function testSelectExtraSmall(string $class): void {
    $variables = [
      'element' => ['#type' => 'select', '#multiple' => FALSE],
      'attributes' => ['class' => [$class]],
    ];

    $this->hooks()->preprocessSelect($variables);

    $this->assertContains('form-element--extrasmall', $variables['attributes']['class']);
  }

  /**
   * Provides the CSS classes of the selects of the block layout page.
   *
   * @return array
   *   Test scenarios.
   */
  public static function providerSelectExtraSmall(): array {
    return [
      'region select' => ['block-region-select'],
      'weight select' => ['block-weight'],
    ];
  }

  /**
   * Tests the title identifiers of status messages.
   *
   * The identifiers are what the template refers to from the aria-labelledby
   * attribute of the message list, so there has to be one per message type.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessStatusMessages()
   */
  public function testStatusMessages(): void {
    $variables = [
      'message_list' => [
        'status' => ['The configuration options have been saved.'],
        'error' => ['The website encountered an unexpected error.'],
      ],
    ];

    $this->hooks()->preprocessStatusMessages($variables);

    $this->assertSame([
      'status' => 'message-status-title',
      'error' => 'message-error-title',
    ], $variables['title_ids']);
  }

  /**
   * Tests the theme cards of the appearance page.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessSystemThemesPage()
   */
  public function testSystemThemesPage(): void {
    $variables = [
      'theme_groups' => [
        'installed' => [
          'themes' => [
            'alpha' => [
              'name' => 'alpha',
              'screenshot' => [
                '#attributes' => new Attribute(['class' => ['no-screenshot']]),
              ],
              'description' => '',
              'operations' => ['#theme' => 'links'],
            ],
            'beta' => [
              'name' => 'beta',
              'screenshot' => [
                '#attributes' => new Attribute(['class' => ['screenshot']]),
              ],
              'description' => 'A theme with a description.',
              'operations' => ['#theme' => 'links__dropbutton'],
            ],
          ],
        ],
      ],
    ];

    $this->hooks()->preprocessSystemThemesPage($variables);

    $themes = $variables['theme_groups']['installed']['themes'];

    // A theme without a screenshot renders no placeholder image.
    $this->assertArrayNotHasKey('screenshot', $themes['alpha']);
    $this->assertArrayHasKey('screenshot', $themes['beta']);

    // The identifiers are referred to by the card itself, and a card only
    // refers to a description that exists.
    $this->assertSame('alpha-label', $themes['alpha']['title_id']);
    $this->assertArrayNotHasKey('description_id', $themes['alpha']);
    $this->assertSame('beta-label', $themes['beta']['title_id']);
    $this->assertSame('beta-description', $themes['beta']['description_id']);

    // The operations are rendered as action links, but only when they are not
    // rendered by a more specific template already.
    $this->assertSame('links__action_links', $themes['alpha']['operations']['#theme']);
    $this->assertSame('links__dropbutton', $themes['beta']['operations']['#theme']);
  }

  /**
   * Tests that sortable table headers are marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessTable()
   */
  public function testTableSortableHeader(): void {
    $sort_url = Url::fromRoute('system.admin_content', [], [
      'query' => ['order' => 'Title', 'sort' => 'asc'],
    ]);
    $variables = [
      'attributes' => [],
      'header' => [
        [
          'content' => Link::fromTextAndUrl('Title', $sort_url),
          'attributes' => new Attribute(),
        ],
        [
          'content' => Link::createFromRoute('Operations', 'system.admin_content'),
          'attributes' => new Attribute(),
        ],
        [
          'content' => 'Status',
          'attributes' => new Attribute(),
        ],
      ],
      'rows' => [],
    ];

    $this->hooks()->preprocessTable($variables);

    $this->assertTrue($variables['header'][0]['attributes']->hasClass('sortable-heading'));
    // A link that does not sort the table is no sort indicator.
    $this->assertFalse($variables['header'][1]['attributes']->hasClass('sortable-heading'));
    $this->assertFalse($variables['header'][2]['attributes']->hasClass('sortable-heading'));
  }

  /**
   * Tests that draggable tables and their drag handle cells are marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessTable()
   */
  public function testTableDraggableRows(): void {
    $variables = [
      'attributes' => [],
      'header' => [],
      'rows' => [
        [
          'attributes' => new Attribute(['class' => ['draggable']]),
          'cells' => [
            ['attributes' => new Attribute(), 'content' => NULL],
            ['attributes' => new Attribute(), 'content' => 'First'],
          ],
        ],
        [
          'attributes' => new Attribute(['class' => ['draggable']]),
          'cells' => [
            ['attributes' => new Attribute(), 'content' => 'Second'],
          ],
        ],
        [
          'attributes' => new Attribute(),
          'cells' => [
            ['attributes' => new Attribute(), 'content' => 'Third'],
          ],
        ],
      ],
    ];

    $this->hooks()->preprocessTable($variables);

    // However many rows are draggable, the table is marked once.
    $this->assertSame(['draggable-table'], $variables['attributes']['class']);

    $first_cells = $variables['rows'][0]['cells'];
    $this->assertTrue($first_cells[0]['attributes']->hasClass('tabledrag-cell'));
    // An empty first cell holds nothing but the drag handle.
    $this->assertTrue($first_cells[0]['attributes']->hasClass('tabledrag-cell--only-drag'));
    $this->assertFalse($first_cells[1]['attributes']->hasClass('tabledrag-cell'));

    $second_cells = $variables['rows'][1]['cells'];
    $this->assertTrue($second_cells[0]['attributes']->hasClass('tabledrag-cell'));
    $this->assertFalse($second_cells[0]['attributes']->hasClass('tabledrag-cell--only-drag'));

    $third_cells = $variables['rows'][2]['cells'];
    $this->assertFalse($third_cells[0]['attributes']->hasClass('tabledrag-cell'));
  }

  /**
   * Tests that the description of a text format element is associated.
   *
   * The wrapper renders the description, so the identifier the form element
   * points at has to move to the description.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessTextFormatWrapper()
   */
  public function testTextFormatWrapper(): void {
    $variables = [
      'attributes' => ['id' => 'edit-body-0-value--description'],
    ];

    $this->hooks()->preprocessTextFormatWrapper($variables);

    $this->assertSame('edit-body-0-value--description', $variables['attributes']['aria-describedby']);
    $this->assertArrayNotHasKey('id', $variables['attributes']);
    $this->assertSame([
      'id' => 'edit-body-0-value--description',
    ], $variables['description_attributes']->toArray());
  }

  /**
   * Tests a text format element that has no description to associate.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessTextFormatWrapper()
   */
  public function testTextFormatWrapperWithoutDescription(): void {
    $variables = ['attributes' => []];

    $this->hooks()->preprocessTextFormatWrapper($variables);

    $this->assertArrayNotHasKey('aria-describedby', $variables['attributes']);
    $this->assertSame([], $variables['description_attributes']->toArray());
  }

  /**
   * Tests that sortable views table headers are marked.
   *
   * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessViewsViewTable()
   */
  public function testViewsViewTable(): void {
    $variables = [
      'header' => [
        'title' => [
          'url' => '/admin/content?order=Title&sort=asc',
          'attributes' => new Attribute(),
        ],
        'status' => [
          'url' => '/admin/content',
          'attributes' => new Attribute(),
        ],
        'operations' => [
          'attributes' => new Attribute(),
        ],
      ],
    ];

    $this->hooks()->preprocessViewsViewTable($variables);

    $this->assertTrue($variables['header']['title']['attributes']->hasClass('sortable-heading'));
    // A column that cannot be sorted is no sort indicator.
    $this->assertFalse($variables['header']['status']['attributes']->hasClass('sortable-heading'));
    $this->assertFalse($variables['header']['operations']['attributes']->hasClass('sortable-heading'));
  }

  /**
   * Builds the preprocess hook implementations of the theme.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface|null $route_match
   *   The route match to report as the current one, or NULL to use the route
   *   match of the current request.
   *
   * @return \Drupal\default_admin\Hook\PreprocessHooks
   *   The preprocess hook implementations.
   */
  protected function hooks(?RouteMatchInterface $route_match = NULL): PreprocessHooks {
    return new PreprocessHooks(
      $this->container->get('theme.manager'),
      $this->container->get('request_stack'),
      $route_match ?? $this->container->get('current_route_match'),
      $this->container->get('config.factory'),
      $this->container->get('current_user'),
      $this->container->get('entity_type.manager'),
      $this->container->get('entity_type.bundle.info'),
      $this->container->get('plugin.manager.block'),
      $this->container->get('renderer'),
      $this->container->get('module_handler'),
    );
  }

}
