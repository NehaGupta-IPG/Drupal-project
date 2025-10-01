<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Theme\Style\Enum\Type;
use Drupal\Core\Theme\Style\Enum\Status;
use Drupal\Core\Theme\Style\Enum\Target;
use Drupal\Core\Theme\Style\StyleDefinition;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
    yield 'getType:string:mode' => ['getType', 'type', 'mode', Type::Mode];
    yield 'getType:string:utility' => ['getType', 'type', 'utility', Type::Utility];
    yield 'getType:enum:mode' => ['getType', 'type', Type::Mode, Type::Mode];
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
    yield 'getOptions' => ['getOptions', 'options',
    [
      'my_class' => [
        'label' => 'My class',
      ],
    ],
    [
      'my_class' => [
        'label' => 'My class',
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
        ],
        'medium' => [
          'label' => new TranslatableMarkup('Medium'),
          'description' => '',
          'value' => 'medium',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
        ],
        'large' => [
          'label' => new TranslatableMarkup('Large'),
          'description' => '',
          'value' => 'font-size: 15px;',
          'attribute' => 'style',
          'target' => Target::Body,
          'status' => Status::Stable,
        ],
        'extra large' => [
          'label' => new TranslatableMarkup('Extra large'),
          'description' => '',
          'value' => 'xxl',
          'attribute' => 'my-attribute',
          'target' => Target::Html,
          'status' => Status::Stable,
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
        ],
        'other_property' => [
          'label' => new TranslatableMarkup('Label'),
          'description' => '',
          'value' => 'other_property',
          'attribute' => 'my-attribute',
          'target' => Target::Body,
          'status' => Status::Stable,
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

  /**
   * Test applyOnAttribute.
   *
   * @param string $type
   *   The type of the style.
   * @param array $option
   *   The option like in the YAML declaration.
   * @param array $expected
   *   The expected attribute storage.
   */
  #[DataProvider('providerApplyOnAttribute')]
  public function testApplyOnAttribute(string $type, array $option, array $expected): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'type' => $type,
      'options' => [
        'test' => $option,
      ],
    ]);
    $attribute = new Attribute([]);
    $definition->applyOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
  }

  /**
   * Data provider for ::testApplyOnAttribute().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerApplyOnAttribute(): iterable {
    yield 'Utility: default' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'my-class',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Utility: on an attribute' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'data',
        'attribute' => 'data-test',
      ],
      [
        'data-test' => 'data',
      ],
    ];

    yield 'Utility: inline style' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'font-size: 15px',
        'attribute' => 'style',
      ],
      [
        'style' => 'font-size: 15px',
      ],
    ];

    yield 'Utility: experimental option' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'status' => 'experimental',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Mode: default' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Mode: on an attribute' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'data',
        'attribute' => 'data-test',
      ],
      [
        'data-test' => 'data',
      ],
    ];

    yield 'Mode: inline style' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'font-size: 15px',
        'attribute' => 'style',
      ],
      [
        'style' => 'font-size: 15px',
      ],
    ];

    yield 'Mode: target body' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'target' => 'body',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Mode: target html' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'target' => 'html',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Mode: target meta' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'target' => 'meta',
      ],
      [],
    ];
  }

  /**
   * Test applyOnAttribute: style with a library.
   */
  public function testApplyOnAttributeWithLibrary(): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
      'library' => 'my_module/my_library',
    ]);
    $expected = [
      'class' => ['my-class'],
    ];
    $attribute = new Attribute([]);
    $definition->applyOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
    $this->assertEquals([], $attribute->getAttachments(), 'The library should not have been attached.');
  }

  /**
   * Test applyOnAttribute: triggering logger.
   */
  public function testApplyOnAttributeUndefinedOption(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Invalid style option %optionId for style %styleId.', [
        '%styleId' => 'test',
        '%optionId' => 'foo',
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
    ]);
    $attribute = new Attribute([]);
    $definition->applyOnAttribute('foo', $attribute);
    $this->assertEquals([], $attribute->toArray(), 'The attribute should be unchanged.');
  }

  /**
   * Test applyOnAttribute: triggering logger.
   */
  public function testApplyOnAttributeWarningStatus(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Usage of style %styleId which is in %status status.', [
        '%styleId' => 'test',
        '%status' => new TranslatableMarkup('Deprecated'),
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'status' => 'deprecated',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
    ]);
    $expected = [
      'class' => ['my-class'],
    ];
    $attribute = new Attribute([]);
    $definition->applyOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
  }

  /**
   * Test applyOnAttribute: triggering logger.
   */
  public function testApplyOnAttributeWarningStatusOption(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Usage of style option %optionId which is in %status status for style %styleId.', [
        '%styleId' => 'test',
        '%optionId' => 'test',
        '%status' => new TranslatableMarkup('Obsolete'),
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
          'status' => 'obsolete',
        ],
      ],
    ]);
    $expected = [
      'class' => ['my-class'],
    ];
    $attribute = new Attribute([]);
    $definition->applyOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
  }

  /**
   * Test applyOrBubbleOnAttribute.
   *
   * @param string $type
   *   The type of the style.
   * @param array $option
   *   The option like in the YAML declaration.
   * @param array $expected
   *   The expected attribute storage.
   * @param array $expectedAttachments
   *   The expected attachments.
   */
  #[DataProvider('providerApplyOrBubbleOnAttribute')]
  public function testApplyOrBubbleOnAttribute(string $type, array $option, array $expected, array $expectedAttachments = []): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'type' => $type,
      'options' => [
        'test' => $option,
      ],
    ]);
    $attribute = new Attribute([]);
    $definition->applyOrBubbleOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
    $this->assertEquals($expectedAttachments, $attribute->getAttachments());
  }

  /**
   * Data provider for ::testApplyOrBubbleOnAttribute().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerApplyOrBubbleOnAttribute(): iterable {
    yield 'Utility: default' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'my-class',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Utility: on an attribute' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'data',
        'attribute' => 'data-test',
      ],
      [
        'data-test' => 'data',
      ],
    ];

    yield 'Utility: inline style' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'font-size: 15px',
        'attribute' => 'style',
      ],
      [
        'style' => 'font-size: 15px',
      ],
    ];

    yield 'Utility: experimental option' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'status' => 'experimental',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Mode: default' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
      ],
      [
        'class' => ['my-class'],
      ],
    ];

    yield 'Mode: on an attribute' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'data',
        'attribute' => 'data-test',
      ],
      [
        'data-test' => 'data',
      ],
    ];

    yield 'Mode: inline style' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'font-size: 15px',
        'attribute' => 'style',
      ],
      [
        'style' => 'font-size: 15px',
      ],
    ];

    yield 'Mode: target body' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'target' => 'body',
      ],
      [],
      [
        'styles' => [
          'test' => 'test',
        ],
      ],
    ];

    yield 'Mode: target html' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'target' => 'html',
      ],
      [],
      [
        'styles' => [
          'test' => 'test',
        ],
      ],
    ];

    yield 'Mode: target meta' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'attribute' => 'my-name',
        'target' => 'meta',
      ],
      [],
      [
        'html_head' => [
          [
            [
              '#tag' => 'meta',
              '#attributes' => [
                'name' => 'my-name',
                'content' => 'my-class',
              ],
            ],
            'style:test:test',
          ],
        ],
      ],
    ];
  }

  /**
   * Test applyOrBubbleOnAttribute: style with a library.
   */
  public function testApplyOrBubbleOnAttributeWithLibrary(): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
      'library' => 'my_module/my_library',
    ]);
    $expected = [
      'class' => ['my-class'],
    ];
    $expectedAttachments = [
      'library' => [
        'my_module/my_library',
      ],
    ];
    $attribute = new Attribute([]);
    $definition->applyOrBubbleOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
    $this->assertEquals($expectedAttachments, $attribute->getAttachments());
  }

  /**
   * Test applyOrBubbleOnAttribute: triggering logger.
   */
  public function testApplyOrBubbleOnAttributeUndefinedOption(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Invalid style option %optionId for style %styleId.', [
        '%styleId' => 'test',
        '%optionId' => 'foo',
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
    ]);
    $attribute = new Attribute([]);
    $definition->applyOrBubbleOnAttribute('foo', $attribute);
    $this->assertEquals([], $attribute->toArray(), 'The attribute should be unchanged.');
  }

  /**
   * Test applyOrBubbleOnAttribute: triggering logger.
   */
  public function testApplyOrBubbleOnAttributeWarningStatus(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Usage of style %styleId which is in %status status.', [
        '%styleId' => 'test',
        '%status' => new TranslatableMarkup('Deprecated'),
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'status' => 'deprecated',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
    ]);
    $expected = [
      'class' => ['my-class'],
    ];
    $attribute = new Attribute([]);
    $definition->applyOrBubbleOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
  }

  /**
   * Test applyOrBubbleOnAttribute: triggering logger.
   */
  public function testApplyOrBubbleOnAttributeWarningStatusOption(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Usage of style option %optionId which is in %status status for style %styleId.', [
        '%styleId' => 'test',
        '%optionId' => 'test',
        '%status' => new TranslatableMarkup('Obsolete'),
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
          'status' => 'obsolete',
        ],
      ],
    ]);
    $expected = [
      'class' => ['my-class'],
    ];
    $attribute = new Attribute([]);
    $definition->applyOrBubbleOnAttribute('test', $attribute);
    $this->assertEquals($expected, $attribute->toArray());
  }

  /**
   * Test applyOrBubbleOnArray.
   *
   * @param string $type
   *   The type of the style.
   * @param array $option
   *   The option like in the YAML declaration.
   * @param array $expected
   *   The expected element structure.
   */
  #[DataProvider('providerApplyOrBubbleOnArray')]
  public function testApplyOrBubbleOnArray(string $type, array $option, array $expected): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'type' => $type,
      'options' => [
        'test' => $option,
      ],
    ]);
    $element = [];
    $definition->applyOrBubbleOnArray('test', $element);
    $this->assertEquals($expected, $element);
  }

  /**
   * Data provider for ::testApplyOrBubbleOnArray().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerApplyOrBubbleOnArray(): iterable {
    yield 'Utility: default' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'my-class',
      ],
      [
        '#attributes' => [
          'class' => ['my-class'],
        ],
        '#attached' => [],
      ],
    ];

    yield 'Utility: on an attribute' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'data',
        'attribute' => 'data-test',
      ],
      [
        '#attributes' => [
          'data-test' => 'data',
        ],
        '#attached' => [],
      ],
    ];

    yield 'Utility: inline style' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'font-size: 15px',
        'attribute' => 'style',
      ],
      [
        '#attributes' => [
          'style' => 'font-size: 15px',
        ],
        '#attached' => [],
      ],
    ];

    yield 'Utility: experimental option' => [
      'utility',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'status' => 'experimental',
      ],
      [
        '#attributes' => [
          'class' => ['my-class'],
        ],
        '#attached' => [],
      ],
    ];

    yield 'Mode: default' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
      ],
      [
        '#attributes' => [
          'class' => ['my-class'],
        ],
        '#attached' => [],
      ],
    ];

    yield 'Mode: on an attribute' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'data',
        'attribute' => 'data-test',
      ],
      [
        '#attributes' => [
          'data-test' => 'data',
        ],
        '#attached' => [],
      ],
    ];

    yield 'Mode: inline style' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'font-size: 15px',
        'attribute' => 'style',
      ],
      [
        '#attributes' => [
          'style' => 'font-size: 15px',
        ],
        '#attached' => [],
      ],
    ];

    yield 'Mode: target body' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'target' => 'body',
      ],
      [
        '#attached' => [
          'styles' => [
            'test' => 'test',
          ],
        ],
      ],
    ];

    yield 'Mode: target html' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'target' => 'html',
      ],
      [
        '#attached' => [
          'styles' => [
            'test' => 'test',
          ],
        ],
      ],
    ];

    yield 'Mode: target meta' => [
      'mode',
      [
        'label' => 'Label',
        'value' => 'my-class',
        'attribute' => 'my-name',
        'target' => 'meta',
      ],
      [
        '#attached' => [
          'html_head' => [
            [
              [
                '#tag' => 'meta',
                '#attributes' => [
                  'name' => 'my-name',
                  'content' => 'my-class',
                ],
              ],
              'style:test:test',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Test applyOrBubbleOnArray: style with a library.
   */
  public function testApplyOrBubbleOnArrayWithLibrary(): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
      'library' => 'my_module/my_library',
    ]);
    $element = [];
    $expected = [
      '#attributes' => [
        'class' => ['my-class'],
      ],
      '#attached' => [
        'library' => [
          'my_module/my_library',
        ],
      ],
    ];
    $definition->applyOrBubbleOnArray('test', $element);
    $this->assertEquals($expected, $element);
  }

  /**
   * Test applyOrBubbleOnArray: apply on another attributes key.
   */
  public function testApplyOrBubbleOnArrayWithOtherKey(): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
    ]);
    $element = [];
    $expected = [
      '#content_attributes' => [
        'class' => ['my-class'],
      ],
      '#attached' => [],
    ];
    $definition->applyOrBubbleOnArray('test', $element, '#content_attributes');
    $this->assertEquals($expected, $element);
  }

  /**
   * Test applyOrBubbleOnArray: apply on non accepting attributes element.
   */
  public function testApplyOrBubbleOnArrayNonAccepting(): void {
    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
      'library' => 'my_module/my_library',
    ]);
    $element = [
      '#accept_attributes' => FALSE,
    ];
    $expected = [
      '#accept_attributes' => FALSE,
      '#attached' => [
        'library' => [
          'my_module/my_library',
        ],
      ],
    ];
    $definition->applyOrBubbleOnArray('test', $element);
    $this->assertEquals($expected, $element);
  }

  /**
   * Test applyOrBubbleOnArray: triggering logger.
   */
  public function testApplyOrBubbleOnArrayUndefinedOption(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with('Invalid style option %optionId for style %styleId.', [
        '%styleId' => 'test',
        '%optionId' => 'foo',
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
    ]);
    $element = [];
    $definition->applyOrBubbleOnArray('foo', $element);
    $this->assertEquals([], $element, 'The element should be unchanged.');
  }

  /**
   * Test applyOrBubbleOnArray: triggering logger.
   */
  public function testApplyOrBubbleOnArrayWarningStatus(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Usage of style %styleId which is in %status status.', [
        '%styleId' => 'test',
        '%status' => new TranslatableMarkup('Deprecated'),
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'status' => 'deprecated',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
        ],
      ],
    ]);
    $expected = [
      '#attributes' => [
        'class' => ['my-class'],
      ],
      '#attached' => [],
    ];
    $element = [];
    $definition->applyOrBubbleOnArray('test', $element);
    $this->assertEquals($expected, $element);
  }

  /**
   * Test applyOrBubbleOnArray: triggering logger.
   */
  public function testApplyOrBubbleOnArrayWarningStatusOption(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with('Usage of style option %optionId which is in %status status for style %styleId.', [
        '%styleId' => 'test',
        '%optionId' => 'test',
        '%status' => new TranslatableMarkup('Obsolete'),
      ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->expects($this->once())
      ->method('get')
      ->with('style')
      ->willReturn($logger);

    $container = new ContainerBuilder();
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $definition = new StyleDefinition([
      'id' => 'test',
      'options' => [
        'test' => [
          'label' => 'Label',
          'value' => 'my-class',
          'status' => 'obsolete',
        ],
      ],
    ]);
    $expected = [
      '#attributes' => [
        'class' => ['my-class'],
      ],
      '#attached' => [],
    ];
    $element = [];
    $definition->applyOrBubbleOnArray('test', $element);
    $this->assertEquals($expected, $element);
  }

}
