<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Style\Enum\ModeColorScheme;
use Drupal\Core\Theme\Style\Enum\ModeContrast;
use Drupal\Core\Theme\Style\Enum\ModeReducedMotion;
use Drupal\Core\Theme\Style\Enum\Type;
use Drupal\Core\Theme\Style\Enum\Status;
use Drupal\Core\Theme\Style\Enum\Target;
use Drupal\Core\Theme\Style\StyleDefinition;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Theme\Style\StyleDefinition.
 */
#[CoversClass(StyleDefinition::class)]
#[Group('style')]
class StyleDefinitionTest extends UnitTestCase {

  /**
   * Test getters.
   *
   * @param string $getter
   *   The getter callback.
   * @param string $name
   *   The name of the plugin attributes.
   * @param mixed $value
   *   The attribute's value.
   * @param mixed $expected
   *   The expected result.
   */
  #[DataProvider('providerGetters')]
  public function testGetters(string $getter, string $name, $value, $expected): void {
    $definition = new StyleDefinition([$name => $value]);
    $this->assertEquals($expected, \call_user_func([$definition, $getter]));
  }

  /**
   * Data provider for ::testGetters().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerGetters(): iterable {
    yield 'id' => ['id', 'id', 'plugin_id', 'plugin_id'];
    yield 'Disable' => ['isEnabled', 'enabled', FALSE, FALSE];
    yield 'Enabled' => ['isEnabled', 'enabled', TRUE, TRUE];
    yield 'getLabel' => ['getLabel', 'label', 'Plugin label', 'Plugin label'];
    yield 'getDescription' => ['getDescription', 'description', 'Plugin description.', 'Plugin description.'];
    yield 'getCategory' => ['getCategory', 'category', 'Plugin category', 'Plugin category'];
    yield 'getProvider' => ['getProvider', 'provider', 'my_module', 'my_module'];
    yield 'getStatus:string:experimental' => ['getStatus', 'status', 'experimental', Status::Experimental];
    yield 'getStatus:string:stable' => ['getStatus', 'status', 'stable', Status::Stable];
    yield 'getStatus:string:deprecated' => ['getStatus', 'status', 'deprecated', Status::Deprecated];
    yield 'getStatus:string:obsolete' => ['getStatus', 'status', 'obsolete', Status::Obsolete];
    yield 'getStatus:enum:experimental' => ['getStatus', 'status', Status::Experimental, Status::Experimental];
    yield 'getStatus:enum:stable' => ['getStatus', 'status', Status::Stable, Status::Stable];
    yield 'getStatus:enum:deprecated' => ['getStatus', 'status', Status::Deprecated, Status::Deprecated];
    yield 'getStatus:enum:obsolete' => ['getStatus', 'status', Status::Obsolete, Status::Obsolete];
    yield 'getWeight' => ['getWeight', 'weight', 10, 10];
    yield 'getType:string:theme' => ['getType', 'type', 'theme', Type::Theme];
    yield 'getType:string:utility' => ['getType', 'type', 'utility', Type::Utility];
    yield 'getType:enum:theme' => ['getType', 'type', Type::Theme, Type::Theme];
    yield 'getType:enum:utility' => ['getType', 'type', Type::Utility, Type::Utility];
    yield 'getAttribute' => ['getAttribute', 'attribute', 'my-attribute', 'my-attribute'];
    yield 'getTarget:string:empty' => ['getTarget', 'target', '', Target::Any];
    yield 'getTarget:string:any' => ['getTarget', 'target', 'any', Target::Any];
    yield 'getTarget:string:html' => ['getTarget', 'target', 'html', Target::Html];
    yield 'getTarget:string:body' => ['getTarget', 'target', 'body', Target::Body];
    yield 'getTarget:string:meta' => ['getTarget', 'target', 'meta', Target::Meta];
    yield 'getTarget:enum:any' => ['getTarget', 'target', Target::Any, Target::Any];
    yield 'getTarget:enum:html' => ['getTarget', 'target', Target::Html, Target::Html];
    yield 'getTarget:enum:body' => ['getTarget', 'target', Target::Body, Target::Body];
    yield 'getTarget:enum:meta' => ['getTarget', 'target', Target::Meta, Target::Meta];
    yield 'getOptions' => ['getOptions', 'options', [
      'my_class' => [
        'label' => 'My class',
      ],
    ], [
      'my_class' => [
        'label' => 'My class',
      ],
    ],
    ];
    yield 'getOptions:modes:string' => ['getOptions', 'options', [
      'my_class' => [
        'label' => 'My class',
        'modes' => [
          'color_scheme' => 'dark',
          'contrast' => 'more',
          'reduced_motion' => 'reduce',
        ],
      ],
    ], [
      'my_class' => [
        'label' => 'My class',
        'modes' => [
          'color_scheme' => ModeColorScheme::Dark,
          'contrast' => ModeContrast::More,
          'reduced_motion' => ModeReducedMotion::Reduce,
        ],
      ],
    ],
    ];
    yield 'getOptions:modes:enum' => ['getOptions', 'options', [
      'my_class' => [
        'label' => 'My class',
        'modes' => [
          'color_scheme' => ModeColorScheme::Light,
          'contrast' => ModeContrast::Less,
          'reduced_motion' => ModeReducedMotion::Any,
        ],
      ],
    ], [
      'my_class' => [
        'label' => 'My class',
        'modes' => [
          'color_scheme' => ModeColorScheme::Light,
          'contrast' => ModeContrast::Less,
          'reduced_motion' => ModeReducedMotion::Any,
        ],
      ],
    ],
    ];
    yield 'getLibrary' => ['getLibrary', 'library', 'my_module/my_library', 'my_module/my_library'];
  }

  /**
   * Test getProcessedOptions.
   *
   * @param array $definition
   *   The YAML declaration.
   * @param array $expected
   *   The expected result.
   */
  #[DataProvider('providerGetProcessedOptions')]
  public function testGetProcessedOptions(array $definition, array $expected): void {
    $definition = new StyleDefinition($definition);
    $this->assertEquals($expected, $definition->getProcessedOptions());
  }

  /**
   * Data provider for ::testGetProcessedOptions().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerGetProcessedOptions(): iterable {
    yield 'All cases' => [
      [
        'type' => 'theme',
        'attribute' => 'my-attribute',
        'target' => 'body',
        'options' => [
          // Normal syntax.
          'small' => [
            'label' => 'Small',
            'value' => 'text-sm',
          ],
          // Optional value.
          'medium' => [
            'label' => 'Medium',
          ],
          // Overridden attribute.
          'large' => [
            'label' => 'Large',
            'attribute' => 'style',
            'value' => 'font-size: 15px;',
          ],
          // Overridden target.
          'extra large' => [
            'label' => 'Extra large',
            'value' => 'xxl',
            'target' => 'html',
          ],
          // With description and overridden status.
          'description_status' => [
            'label' => 'Label',
            'description' => 'Description',
            'status' => 'experimental',
          ],
          // With a single mode.
          'single_mode' => [
            'label' => 'Single mode',
            'modes' => [
              'color_scheme' => 'dark',
            ],
          ],
          // With all the modes.
          'all_modes' => [
            'label' => 'All modes',
            'modes' => [
              'color_scheme' => 'light',
              'contrast' => 'more',
              'reduced_motion' => 'reduce',
            ],
          ],
          // With modes already set as enum.
          'modes_as_enum' => [
            'label' => 'Modes as enum',
            'modes' => [
              'contrast' => ModeContrast::Less,
            ],
          ],
          // With other property.
          'other_property' => [
            'label' => 'Label',
            'foo' => 'bar',
          ],
        ],
      ],
      [
        'small' => [
          'label' => new TranslatableMarkup('Small'),
          'description' => '',
          'value' => 'text-sm',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Any,
            'contrast' => ModeContrast::Any,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
        ],
        'medium' => [
          'label' => new TranslatableMarkup('Medium'),
          'description' => '',
          'value' => 'medium',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Any,
            'contrast' => ModeContrast::Any,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
        ],
        'large' => [
          'label' => new TranslatableMarkup('Large'),
          'description' => '',
          'value' => 'font-size: 15px;',
          'attribute' => 'style',
          'target' => Target::Body,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Any,
            'contrast' => ModeContrast::Any,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
        ],
        'extra large' => [
          'label' => new TranslatableMarkup('Extra large'),
          'description' => '',
          'value' => 'xxl',
          'attribute' => 'my-attribute',
          'target' => Target::Html,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Any,
            'contrast' => ModeContrast::Any,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
        ],
        'description_status' => [
          'label' => new TranslatableMarkup('@label (@status)', [
            '@label' => new TranslatableMarkup('Label'),
            '@status' => Status::Experimental->label(),
          ]),
          'description' => new TranslatableMarkup('Description'),
          'value' => 'description_status',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Experimental,
          'modes' => [
            'color_scheme' => ModeColorScheme::Any,
            'contrast' => ModeContrast::Any,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
        ],
        'single_mode' => [
          'label' => new TranslatableMarkup('Single mode'),
          'description' => '',
          'value' => 'single_mode',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Dark,
            'contrast' => ModeContrast::Any,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
        ],
        'all_modes' => [
          'label' => new TranslatableMarkup('All modes'),
          'description' => '',
          'value' => 'all_modes',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Light,
            'contrast' => ModeContrast::More,
            'reduced_motion' => ModeReducedMotion::Reduce,
          ],
        ],
        'modes_as_enum' => [
          'label' => new TranslatableMarkup('Modes as enum'),
          'description' => '',
          'value' => 'modes_as_enum',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Any,
            'contrast' => ModeContrast::Less,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
        ],
        'other_property' => [
          'label' => new TranslatableMarkup('Label'),
          'description' => '',
          'value' => 'other_property',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
          'modes' => [
            'color_scheme' => ModeColorScheme::Any,
            'contrast' => ModeContrast::Any,
            'reduced_motion' => ModeReducedMotion::Any,
          ],
          'foo' => 'bar',
        ],
      ],
    ];
  }

  /**
   * Test getOptionsAsOptions.
   *
   * @param array $options
   *   The options like in the YAML declaration.
   * @param array $expected
   *   The expected result.
   */
  #[DataProvider('providerGetOptionsAsOptions')]
  public function testGetOptionsAsOptions(array $options, array $expected): void {
    $definition = new StyleDefinition(['options' => $options]);
    $this->assertEquals($expected, $definition->getOptionsAsOptions());
  }

  /**
   * Data provider for ::testGetOptionsAsOptions().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerGetOptionsAsOptions(): iterable {
    yield 'All cases' => [
      [
        // Normal syntax.
        'small' => [
          'label' => 'Small',
          'value' => 'text-sm',
        ],
        // Optional value.
        'medium' => [
          'label' => 'Medium',
        ],
        // Non-stable option.
        'experimental' => [
          'label' => 'Label',
          'status' => 'experimental',
        ],
      ],
      [
        'small' => new TranslatableMarkup('Small'),
        'medium' => new TranslatableMarkup('Medium'),
        'experimental' => new TranslatableMarkup('@label (@status)', [
          '@label' => new TranslatableMarkup('Label'),
          '@status' => Status::Experimental->label(),
        ]),
      ],
    ];
  }

  /**
   * Test getLinks.
   *
   * @param array $links
   *   The links like in the YAML declaration.
   * @param array $expected
   *   The expected result.
   */
  #[DataProvider('providerGetLinks')]
  public function testGetLinks(array $links, array $expected): void {
    $definition = new StyleDefinition([
      'links' => $links,
    ]);
    $this->assertEquals($expected, $definition->getLinks());
  }

  /**
   * Data provider for ::testGetLinks().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerGetLinks(): iterable {
    yield 'Short syntax' => [
      [
        'https://example.com',
      ],
      [
        [
          'url' => 'https://example.com',
          'title' => new TranslatableMarkup('External documentation'),
        ],
      ],
    ];

    yield 'Long syntax' => [
      [
        [
          'url' => 'https://example.com',
          'title' => 'Example',
        ],
      ],
      [
        [
          'url' => 'https://example.com',
          'title' => new TranslatableMarkup('Example'),
        ],
      ],
    ];

    yield 'Long syntax default title' => [
      [
        [
          'url' => 'https://example.com',
        ],
      ],
      [
        [
          'url' => 'https://example.com',
          'title' => new TranslatableMarkup('External documentation'),
        ],
      ],
    ];
  }

}
