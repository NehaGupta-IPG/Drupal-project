<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element\Styles;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\StyleDefinition;
use Drupal\Core\Theme\Style\StylePluginManagerInterface;
use Drupal\style_test\TestStylePluginManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test the styles form element.
 */
#[CoversClass(Styles::class)]
#[Group('style')]
class StylesFormElementTest extends UnitTestCase {

  public const string APPLIED_SUFFIX = ' <sup>(<mark>applied</mark>)</sup>';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();

    $transliteration = $this->createStub(TransliterationInterface::class);
    $transliteration
      ->method('transliterate')
      ->willReturnCallback(static function (string $string) {
        switch ($string) {
          case 'Main':
            return 'Main';

          case 'Other':
            return 'Other';
        }
        return '';
      });
    $container->set('transliteration', $transliteration);

    $stringTranslation = $this->getStringTranslationStub();
    $container->set('string_translation', $stringTranslation);

    // Mock all dependencies for StylePluginManager.
    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $themeHandler = $this->createStub(ThemeHandlerInterface::class);
    $cacheBackend = $this->createStub(CacheBackendInterface::class);

    $stylePluginManager = new TestStylePluginManager(
      $moduleHandler,
      $themeHandler,
      $cacheBackend,
      $this->root,
    );
    $container->set(StylePluginManagerInterface::class, $stylePluginManager);

    \Drupal::setContainer($container);
  }

  /**
   * Test buildForm.
   *
   * @param array $expected
   *   The expected built form element structure.
   * @param array $styles
   *   The style plugins.
   * @param array $formElement
   *   The provided styles form element.
   */
  #[DataProvider('providerBuildForm')]
  public function testBuildForm(array $expected, array $styles, array $formElement): void {
    /** @var \Drupal\style_test\TestStylePluginManager $stylePluginManager */
    $stylePluginManager = \Drupal::getContainer()->get(StylePluginManagerInterface::class);
    $stylePluginManager->setStyles($styles);

    $formState = new FormState();
    $completeForm = [];
    $this->assertEquals($expected, Styles::buildForm($formElement, $formState, $completeForm));
  }

  /**
   * Data provider for ::testBuildForm().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerBuildForm(): iterable {
    $commonDefaultStyles = [
      'test1' => [
        'id' => 'test1',
        'label' => 'Test 1',
        'options' => [
          'opt1' => [
            'label' => 'Option 1',
          ],
          'opt2' => [
            'label' => 'Option 2',
          ],
        ],
      ],
      'test2' => [
        'id' => 'test2',
        'label' => 'Test 2',
        'options' => [
          'opt1' => [
            'label' => 'Option 1',
          ],
          'opt2' => [
            'label' => 'Option 2',
          ],
        ],
      ],
    ];

    $commonExpectedDefaultStyleElements = [
      '#type' => 'details',
      '#title' => 'My styles',
      '#description' => '',
      '#open' => FALSE,
      '#states' => [],
      'test1' => [
        '#type' => 'select',
        '#title' => 'Test 1',
        '#options' => [
          'opt1' => new TranslatableMarkup('Option 1'),
          'opt2' => new TranslatableMarkup('Option 2'),
        ],
        '#default_value' => '',
        '#empty_option' => new TranslatableMarkup('- None -'),
        '#weight' => 0,
      ],
      'test2' => [
        '#type' => 'select',
        '#title' => 'Test 2',
        '#options' => [
          'opt1' => new TranslatableMarkup('Option 1'),
          'opt2' => new TranslatableMarkup('Option 2'),
        ],
        '#default_value' => '',
        '#empty_option' => new TranslatableMarkup('- None -'),
        '#weight' => 0,
      ],
    ];

    $twoGroupsExpectedDefaultStyleElements = [
      '#type' => 'details',
      '#title' => 'My styles',
      '#description' => '',
      '#open' => FALSE,
      '#states' => [],
      'main' => [
        '#type' => 'details',
        '#title' => 'Main',
        'test1' => [
          '#type' => 'select',
          '#title' => 'Test 1',
          '#options' => [
            'opt1' => new TranslatableMarkup('Option 1'),
            'opt2' => new TranslatableMarkup('Option 2'),
          ],
          '#default_value' => '',
          '#empty_option' => new TranslatableMarkup('- None -'),
          '#weight' => 0,
        ],
      ],
      'other' => [
        '#type' => 'details',
        '#title' => 'Other',
        'test2' => [
          '#type' => 'select',
          '#title' => 'Test 2',
          '#options' => [
            'opt1' => new TranslatableMarkup('Option 1'),
            'opt2' => new TranslatableMarkup('Option 2'),
          ],
          '#default_value' => '',
          '#empty_option' => new TranslatableMarkup('- None -'),
          '#weight' => 0,
        ],
      ],
    ];

    yield 'Default behavior: one group' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      'wrapper' => $commonExpectedDefaultStyleElements,
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
    ],
    ];

    yield 'Default behavior: no styles' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
    ], [], [
      '#type' => 'styles',
      '#title' => 'My styles',
    ],
    ];

    yield 'Element with: description' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#description' => 'My description',
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#description' => 'My description',
      ]),
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#description' => 'My description',
    ],
    ];

    yield 'Element with: states' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#states' => [
          'invisible' => [
            ':input[name="my_selector"]' => ['checked' => FALSE],
          ],
        ],
      ]),
      '#states' => [
        'invisible' => [
          ':input[name="my_selector"]' => ['checked' => FALSE],
        ],
      ],
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#states' => [
        'invisible' => [
          ':input[name="my_selector"]' => ['checked' => FALSE],
        ],
      ],
    ],
    ];

    yield 'Element with: container wrapper' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#wrapper_type' => 'container',
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#type' => 'container',
      ]),
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#wrapper_type' => 'container',
    ],
    ];

    yield 'Element with: forced open' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#open' => TRUE,
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#open' => TRUE,
      ]),
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#open' => TRUE,
    ],
    ];

    yield 'Element with: opened with errors' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#children_errors' => [
        'error',
      ],
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#open' => TRUE,
      ]),
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#children_errors' => [
        'error',
      ],
    ],
    ];

    yield 'One group: one default value' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test2' => 'opt2',
      ],
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#title' => 'My styles' . static::APPLIED_SUFFIX,
        'test2' => [
          '#title' => 'Test 2' . static::APPLIED_SUFFIX,
          '#default_value' => 'opt2',
        ],
      ],
      ),
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test2' => 'opt2',
      ],
    ],
    ];

    yield 'One group: checkbox one default value' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test3' => 'opt1',
      ],
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#title' => 'My styles' . static::APPLIED_SUFFIX,
        'test3' => [
          '#title' => 'Test 3: Option 1' . static::APPLIED_SUFFIX,
          '#default_value' => 'opt1',
          '#type' => 'checkbox',
          '#return_value' => 'opt1',
          '#weight' => 0,
        ],
      ]),
    ], NestedArray::mergeDeep($commonDefaultStyles, [
      'test3' => [
        'id' => 'test3',
        'label' => 'Test 3',
        'options' => [
          'opt1' => [
            'label' => 'Option 1',
          ],
        ],
      ],
    ]), [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test3' => 'opt1',
      ],
    ],
    ];

    yield 'One group: two default values' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test1' => 'opt1',
        'test2' => 'opt2',
      ],
      'wrapper' => NestedArray::mergeDeep($commonExpectedDefaultStyleElements, [
        '#title' => 'My styles' . static::APPLIED_SUFFIX,
        'test1' => [
          '#title' => 'Test 1' . static::APPLIED_SUFFIX,
          '#default_value' => 'opt1',
        ],
        'test2' => [
          '#title' => 'Test 2' . static::APPLIED_SUFFIX,
          '#default_value' => 'opt2',
        ],
      ]),
    ], $commonDefaultStyles, [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test1' => 'opt1',
        'test2' => 'opt2',
      ],
    ],
    ];

    yield 'Multiple groups' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      'wrapper' => $twoGroupsExpectedDefaultStyleElements,
    ], NestedArray::mergeDeep($commonDefaultStyles, [
      'test1' => [
        'category' => 'Main',
      ],
    ]), [
      '#type' => 'styles',
      '#title' => 'My styles',
    ],
    ];

    yield 'Multiple groups: one default value' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test1' => 'opt1',
      ],
      'wrapper' => NestedArray::mergeDeep($twoGroupsExpectedDefaultStyleElements, [
        '#title' => 'My styles' . static::APPLIED_SUFFIX,
        'main' => [
          '#title' => 'Main' . static::APPLIED_SUFFIX,
          'test1' => [
            '#title' => 'Test 1' . static::APPLIED_SUFFIX,
            '#default_value' => 'opt1',
          ],
        ],
      ]),
    ], NestedArray::mergeDeep($commonDefaultStyles, [
      'test1' => [
        'category' => 'Main',
      ],
    ]), [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test1' => 'opt1',
      ],
    ],
    ];

    yield 'Multiple groups: two default values' => [[
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test1' => 'opt1',
        'test2' => 'opt2',
      ],
      'wrapper' => NestedArray::mergeDeep($twoGroupsExpectedDefaultStyleElements, [
        '#title' => 'My styles' . static::APPLIED_SUFFIX,
        'main' => [
          '#title' => 'Main' . static::APPLIED_SUFFIX,
          'test1' => [
            '#title' => 'Test 1' . static::APPLIED_SUFFIX,
            '#default_value' => 'opt1',
          ],
        ],
        'other' => [
          '#title' => 'Other' . static::APPLIED_SUFFIX,
          'test2' => [
            '#title' => 'Test 2' . static::APPLIED_SUFFIX,
            '#default_value' => 'opt2',
          ],
        ],
      ]),
    ], NestedArray::mergeDeep($commonDefaultStyles, [
      'test1' => [
        'category' => 'Main',
      ],
    ]), [
      '#type' => 'styles',
      '#title' => 'My styles',
      '#default_value' => [
        'test1' => 'opt1',
        'test2' => 'opt2',
      ],
    ],
    ];
  }

  /**
   * Test getFormElement.
   *
   * @param array $expected
   *   The expected form element.
   * @param \Drupal\Core\Theme\Style\StyleDefinition $definition
   *   The style definition.
   * @param string $defaultValue
   *   The default value.
   */
  #[DataProvider('providerGetFormElement')]
  public function testGetFormElement(array $expected, StyleDefinition $definition, string $defaultValue): void {
    $reflection = new \ReflectionClass(Styles::class);
    $method = $reflection->getMethod('getFormElement');
    $actual = $method->invoke(NULL, $definition, $defaultValue);

    // Check options separately, as we obtain wrong order results with
    // assertEqualsCanonicalizing since PHPUnit 12.
    if (isset($actual['#options']) || isset($expected['#options'])) {
      // Check that both have the same keys.
      $this->assertSame(array_keys($expected['#options']), array_keys($actual['#options']));

      // Check values.
      foreach ($expected['#options'] as $key => $value) {
        $this->assertEqualsCanonicalizing($expected['#options'][$key], $actual['#options'][$key]);
      }
      unset($actual['#options'], $expected['#options']);
    }

    $this->assertEqualsCanonicalizing($expected, $actual);
  }

  /**
   * Data provider for ::testGetFormElement().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerGetFormElement(): iterable {
    yield 'Select' => [[
      '#type' => 'select',
      '#title' => 'My label',
      '#options' => [
        'opt1' => new TranslatableMarkup('Option 1'),
        'opt2' => new TranslatableMarkup('Option 2'),
      ],
      '#default_value' => '',
      '#empty_option' => new TranslatableMarkup('- None -'),
      '#weight' => 0,
    ], new StyleDefinition([
      'label' => 'My label',
      'options' => [
        'opt1' => [
          'label' => 'Option 1',
        ],
        'opt2' => [
          'label' => 'Option 2',
        ],
      ],
    ]),
      '',
    ];
    yield 'Select: with default value' => [[
      '#type' => 'select',
      '#title' => 'My label',
      '#options' => [
        'opt1' => new TranslatableMarkup('Option 1'),
        'opt2' => new TranslatableMarkup('Option 2'),
      ],
      '#default_value' => 'opt2',
      '#empty_option' => new TranslatableMarkup('- None -'),
      '#weight' => 0,
    ], new StyleDefinition([
      'label' => 'My label',
      'options' => [
        'opt1' => [
          'label' => 'Option 1',
        ],
        'opt2' => [
          'label' => 'Option 2',
        ],
      ],
    ]),
      'opt2',
    ];
    yield 'Select: with other weight' => [[
      '#type' => 'select',
      '#title' => 'My label',
      '#options' => [
        'opt1' => new TranslatableMarkup('Option 1'),
        'opt2' => new TranslatableMarkup('Option 2'),
      ],
      '#default_value' => '',
      '#empty_option' => new TranslatableMarkup('- None -'),
      '#weight' => 20,
    ], new StyleDefinition([
      'label' => 'My label',
      'options' => [
        'opt1' => [
          'label' => 'Option 1',
        ],
        'opt2' => [
          'label' => 'Option 2',
        ],
      ],
      'weight' => 20,
    ]),
      '',
    ];

    yield 'Checkbox' => [[
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('@style_label: @style_option', [
        '@style_label' => 'My label',
        '@style_option' => new TranslatableMarkup('Option 1'),
      ]),
      '#default_value' => '',
      '#return_value' => 'opt1',
      '#weight' => 0,
    ], new StyleDefinition([
      'label' => 'My label',
      'options' => [
        'opt1' => [
          'label' => 'Option 1',
        ],
      ],
    ]),
      '',
    ];
    yield 'Checkbox: with default value' => [[
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('@style_label: @style_option', [
        '@style_label' => 'My label',
        '@style_option' => new TranslatableMarkup('Option 1'),
      ]),
      '#default_value' => 'opt1',
      '#return_value' => 'opt1',
      '#weight' => 0,
    ], new StyleDefinition([
      'label' => 'My label',
      'options' => [
        'opt1' => [
          'label' => 'Option 1',
        ],
      ],
    ]),
      'opt1',
    ];
    yield 'Checkbox: with other weight' => [[
      '#type' => 'checkbox',
      '#title' => new TranslatableMarkup('@style_label: @style_option', [
        '@style_label' => 'My label',
        '@style_option' => new TranslatableMarkup('Option 1'),
      ]),
      '#default_value' => '',
      '#return_value' => 'opt1',
      '#weight' => 20,
    ], new StyleDefinition([
      'label' => 'My label',
      'options' => [
        'opt1' => [
          'label' => 'Option 1',
        ],
      ],
      'weight' => 20,
    ]),
      '',
    ];
  }

  /**
   * Test valueCallback.
   *
   * @param array $expected
   *   The expected return depending on the input.
   * @param array $input
   *   The input.
   */
  #[DataProvider('providerValueCallback')]
  public function testValueCallback(array $expected, array $input): void {
    $element = [];
    $form_state = new FormState();
    $this->assertSame($expected, Styles::valueCallback($element, ['wrapper' => $input], $form_state));
  }

  /**
   * Data provider for ::testValueCallback().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerValueCallback(): iterable {
    yield 'nominal_no_group' => [[
      'test' => 'test',
    ], [
      'test' => 'test',
    ],
    ];

    yield 'nominal_with_group' => [[
      'test' => 'test',
    ], [
      'group' => [
        'test' => 'test',
      ],
    ],
    ];

    yield 'empty' => [[], [
      'group' => [
        'test' => '',
      ],
    ],
    ];
  }

}
