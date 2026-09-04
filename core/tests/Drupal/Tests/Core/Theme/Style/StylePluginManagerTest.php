<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Style;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException;
use Drupal\Core\Theme\Style\StyleDefinition;
use Drupal\Core\Theme\Style\StylePluginManager;
use Drupal\style_test\TestStylePluginManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the style plugin manager.
 */
#[CoversClass(StylePluginManager::class)]
#[Group('style')]
class StylePluginManagerTest extends UnitTestCase {

  /**
   * The Style plugin manager.
   */
  protected TestStylePluginManager $stylePluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock all dependencies for StylePluginManager.
    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $themeHandler = $this->createStub(ThemeHandlerInterface::class);
    $cacheBackend = $this->createStub(CacheBackendInterface::class);

    $this->stylePluginManager = new TestStylePluginManager(
      $moduleHandler,
      $themeHandler,
      $cacheBackend,
      $this->root,
    );
  }

  /**
   * Test processDefinition.
   */
  public function testProcessDefinitionWillReturnException(): void {
    $plugin_id = 'test';
    $definition = ['no_id' => $plugin_id];
    $this->expectException(StyleDefinitionErrorException::class);
    $this->stylePluginManager->processDefinition($definition, $plugin_id);
  }

  /**
   * Test processDefinition.
   */
  public function testProcessDefinitionReturnObject(): void {
    $plugin_id = 'test';
    $definition = ['id' => $plugin_id];
    $expected = new StyleDefinition($definition);

    $this->stylePluginManager->processDefinition($definition, $plugin_id);

    $this->assertInstanceOf(StyleDefinition::class, $definition);
    $this->assertEquals($definition, $expected);
  }

  /**
   * Test getDefinitions.
   */
  public function testGetDefinitions(): void {
    $this->stylePluginManager->setStyles([
      'id_1' => [
        'id' => 'id_1',
      ],
      'id_2' => [
        'id' => 'id_2',
      ],
    ]);
    $expected = [
      'id_1',
      'id_2',
    ];
    $definitions = $this->stylePluginManager->getDefinitions();
    $this->assertEquals($expected, array_keys($definitions));
  }

  /**
   * Test alterDefinitions.
   */
  public function testAlterDefinitions(): void {
    $this->stylePluginManager->setStyles([
      'id_1' => [
        'id' => 'id_1',
        'enabled' => FALSE,
      ],
      'id_2' => [
        'id' => 'id_2',
      ],
    ]);
    $expected = [
      'id_2',
    ];
    $definitions = $this->stylePluginManager->getDefinitions();
    $this->assertEquals($expected, array_keys($definitions));
  }

  /**
   * Tests that definitions are altered before disabled styles are removed.
   */
  public function testAlterDefinitionsOrder(): void {
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->expects($this->once())
      ->method('alter')
      ->willReturnCallback(function ($type, &$definitions): void {
        $this->assertSame('style', $type);
        // Enable the disabled style.
        $definitions['disabled'] = new StyleDefinition([
          'id' => 'disabled',
        ]);
        // Disable the enabled style.
        $definitions['enabled'] = new StyleDefinition([
          'id' => 'enabled',
          'enabled' => FALSE,
        ]);
      });
    $stylePluginManager = new TestStylePluginManager(
      $moduleHandler,
      $this->createStub(ThemeHandlerInterface::class),
      $this->createStub(CacheBackendInterface::class),
      $this->root,
    );
    $stylePluginManager->setStyles([
      'disabled' => [
        'id' => 'disabled',
        'enabled' => FALSE,
      ],
      'enabled' => [
        'id' => 'enabled',
      ],
    ]);

    $definitions = $stylePluginManager->getDefinitions();

    $this->assertSame(['disabled'], array_keys($definitions));
  }

  /**
   * Test getCategories.
   */
  public function testGetCategories(): void {
    $this->stylePluginManager->setStyles([
      'id_1' => [
        'id' => 'id_1',
        'category' => 'Cat 1',
      ],
      'id_2' => [
        'id' => 'id_2',
        'category' => 'Cat 2',
      ],
      'id_3' => [
        'id' => 'id_3',
      ],
    ]);
    $expected = [
      'Cat 1',
      'Cat 2',
      'Other',
    ];
    $categories = $this->stylePluginManager->getCategories();
    $this->assertEquals($expected, $categories);
  }

  /**
   * Test getSortedDefinitions.
   */
  public function testGetSortedDefinitions(): void {
    $this->stylePluginManager->setStyles([
      'id_1zz2' => [
        'weight' => 1,
        'category' => 'Z',
        'label' => '(Z)',
        'id' => 'id_1zz2',
      ],
      'id_1zz1' => [
        'weight' => 1,
        'category' => 'Z',
        'label' => 'Z',
        'id' => 'id_1zz1',
      ],
      'id_1za2' => [
        'weight' => 1,
        'category' => 'Z',
        'label' => '(A)',
        'id' => 'id_1za2',
      ],
      'id_1za1' => [
        'weight' => 1,
        'category' => 'Z',
        'label' => 'A',
        'id' => 'id_1za1',
      ],
      'id_1az2' => [
        'weight' => 1,
        'category' => 'A',
        'label' => '(Z)',
        'id' => 'id_1az2',
      ],
      'id_1az1' => [
        'weight' => 1,
        'category' => 'A',
        'label' => 'Z',
        'id' => 'id_1az1',
      ],
      'id_1aa2' => [
        'weight' => 1,
        'category' => 'A',
        'label' => '(A)',
        'id' => 'id_1aa2',
      ],
      'id_1aa1' => [
        'weight' => 1,
        'category' => 'A',
        'label' => 'A',
        'id' => 'id_1aa1',
      ],
      'id_0zz2' => [
        'weight' => 0,
        'category' => 'Z',
        'label' => '(Z)',
        'id' => 'id_0zz2',
      ],
      'id_0zz1' => [
        'weight' => 0,
        'category' => 'Z',
        'label' => 'Z',
        'id' => 'id_0zz1',
      ],
      'id_0za2' => [
        'weight' => 0,
        'category' => 'Z',
        'label' => '(A)',
        'id' => 'id_0za2',
      ],
      'id_0za1' => [
        'weight' => 0,
        'category' => 'Z',
        'label' => 'A',
        'id' => 'id_0za1',
      ],
      'id_0az2' => [
        'weight' => 0,
        'category' => 'A',
        'label' => '(Z)',
        'id' => 'id_0az2',
      ],
      'id_0az1' => [
        'weight' => 0,
        'category' => 'A',
        'label' => 'Z',
        'id' => 'id_0az1',
      ],
      'id_0aa2' => [
        'weight' => 0,
        'category' => 'A',
        'label' => '(A)',
        'id' => 'id_0aa2',
      ],
      'id_0aa1' => [
        'weight' => 0,
        'category' => 'A',
        'label' => 'A',
        'id' => 'id_0aa1',
      ],
    ]);

    $expected = [
      'id_0aa1',
      'id_0aa2',
      'id_0az1',
      'id_0az2',
      'id_0za1',
      'id_0za2',
      'id_0zz1',
      'id_0zz2',
      'id_1aa1',
      'id_1aa2',
      'id_1az1',
      'id_1az2',
      'id_1za1',
      'id_1za2',
      'id_1zz1',
      'id_1zz2',
    ];

    $sorted_definitions = $this->stylePluginManager->getSortedDefinitions();
    $this->assertEquals($expected, \array_keys($sorted_definitions));
    $this->assertContainsOnlyInstancesOf(StyleDefinition::class, $sorted_definitions);
  }

  /**
   * Test getGroupedDefinitions.
   */
  public function testGetGroupedDefinitions(): void {
    $this->stylePluginManager->setStyles([
      'cat_1_1_b' => [
        'id' => 'cat_1_1_b',
        'category' => 'Cat 1',
        'label' => 'B',
        'weight' => 1,
      ],
      'cat_1_1_a' => [
        'id' => 'cat_1_1_a',
        'category' => 'Cat 1',
        'label' => 'A',
        'weight' => 1,
      ],
      'cat_1_0_a' => [
        'id' => 'cat_1_0_a',
        'category' => 'Cat 1',
        'label' => 'A',
        'weight' => 0,
      ],
      'cat_2_0_a' => [
        'id' => 'cat_2_0_a',
        'category' => 'Cat 2',
        'label' => 'A',
        'weight' => 0,
      ],
      'no_category' => [
        'id' => 'no_category',
        'label' => 'B',
        'weight' => 0,
      ],
    ]);

    $category_expected = [
      'Cat 1' => [
        'cat_1_0_a',
        'cat_1_1_a',
        'cat_1_1_b',
      ],
      'Cat 2' => [
        'cat_2_0_a',
      ],
      'Other' => [
        'no_category',
      ],
    ];

    $definitions = $this->stylePluginManager->getGroupedDefinitions();
    $this->assertEquals(\array_keys($category_expected), \array_keys($definitions));
    foreach ($category_expected as $category => $expected) {
      $this->assertArrayHasKey($category, $definitions);
      $this->assertEquals($expected, \array_keys($definitions[$category]));
      $this->assertContainsOnlyInstancesOf(StyleDefinition::class, $definitions[$category]);
    }
  }

  /**
   * Test definitions against JSON schema.
   *
   * @param array $definition
   *   The definition data like in the YAML declaration.
   * @param bool $expectValid
   *   The result expected is valid or not.
   * @param bool $setSchemaRequired
   *   Whether to set the default required definition data.
   */
  #[DataProvider('providerValidateDefinition')]
  public function testValidateDefinition(array $definition, bool $expectValid, bool $setSchemaRequired = TRUE): void {
    // Minimal required values.
    $definition += [
      'id' => 'foo',
      'provider' => 'style_test',
    ];

    if ($setSchemaRequired) {
      $definition += [
        'label' => 'My label',
        'options' => [
          'foo' => [
            'label' => 'bar',
          ],
        ],
      ];
    }

    $this->stylePluginManager->setValidator();

    $reflection = new \ReflectionClass($this->stylePluginManager);
    $method = $reflection->getMethod('validateDefinition');

    if ($expectValid) {
      $this->assertTrue($method->invoke($this->stylePluginManager, $definition));
    }
    else {
      $this->expectException(StyleDefinitionErrorException::class);
      $method->invoke($this->stylePluginManager, $definition);
    }
  }

  /**
   * Data provider for ::testValidateDefinition().
   *
   * @return \Generator
   *   The test cases.
   */
  public static function providerValidateDefinition(): iterable {
    yield 'No label' => [
      [
        'options' => [],
      ],
      FALSE,
      FALSE,
    ];
    yield 'No options' => [
      [
        'label' => 'My label',
      ],
      FALSE,
      FALSE,
    ];

    yield 'Category: valid' => [
      [
        'category' => 'My category',
      ],
      TRUE,
    ];
    yield 'Category: not string' => [
      [
        'category' => [],
      ],
      FALSE,
    ];

    yield 'Enabled: TRUE' => [
      [
        'enabled' => TRUE,
      ],
      TRUE,
    ];
    yield 'Enabled: FALSE' => [
      [
        'enabled' => FALSE,
      ],
      TRUE,
    ];
    yield 'Enabled: not boolean' => [
      [
        'enabled' => 'foo',
      ],
      FALSE,
    ];

    yield 'Status: experimental' => [
      [
        'status' => 'experimental',
      ],
      TRUE,
    ];

    yield 'Status: stable' => [
      [
        'status' => 'stable',
      ],
      TRUE,
    ];

    yield 'Status: deprecated' => [
      [
        'status' => 'deprecated',
      ],
      TRUE,
    ];

    yield 'Status: obsolete' => [
      [
        'status' => 'obsolete',
      ],
      TRUE,
    ];

    yield 'Status: other' => [
      [
        'status' => 'foo',
      ],
      FALSE,
    ];

    yield 'Links: valid syntaxes' => [
      [
        'links' => [
          'https://example.com',
          [
            'url' => 'https://example.com',
          ],
          [
            'title' => 'Example',
            'url' => 'https://example.com',
            'options' => [
              'target' => '_blank',
            ],
            'attributes' => [
              'class' => [
                'foo',
              ],
            ],
          ],
        ],
      ],
      TRUE,
    ];
    yield 'Links: missing url' => [
      [
        'links' => [
          [
            'title' => 'Example',
          ],
        ],
      ],
      FALSE,
    ];

    yield 'Weight: integer' => [
      [
        'weight' => 5,
      ],
      TRUE,
    ];
    yield 'Weight: not integer' => [
      [
        'weight' => 'foo',
      ],
      FALSE,
    ];

    yield 'Type: utility' => [
      [
        'type' => 'utility',
      ],
      TRUE,
    ];
    yield 'Type: mode' => [
      [
        'type' => 'mode',
      ],
      TRUE,
    ];
    yield 'Type: not allowed value' => [
      [
        'type' => 'foo',
      ],
      FALSE,
    ];

    yield 'Attribute: valid' => [
      [
        'attribute' => 'data-attribute',
      ],
      TRUE,
    ];
    yield 'Attribute: not string' => [
      [
        'attribute' => [],
      ],
      FALSE,
    ];

    yield 'Target: html' => [
      [
        'target' => 'html',
      ],
      TRUE,
    ];
    yield 'Target: body' => [
      [
        'target' => 'body',
      ],
      TRUE,
    ];
    yield 'Target: meta' => [
      [
        'target' => 'meta',
      ],
      TRUE,
    ];
    yield 'Target: not allowed value' => [
      [
        'target' => 'foo',
      ],
      FALSE,
    ];

    yield 'Options: valid syntaxes' => [
      [
        'options' => [
          // Normal syntax: minimum required.
          'long' => [
            'label' => 'Long',
          ],
          // Long syntax: all properties and space in option ID.
          'long with space in key' => [
            'label' => 'Large',
            'description' => 'Description',
            'value' => 'font-size: 15px;',
            'attribute' => 'style',
            'target' => 'html',
            'status' => 'stable',
          ],
          'target_body' => [
            'label' => 'Body',
            'target' => 'body',
          ],
          'target_meta' => [
            'label' => 'Meta',
            'target' => 'meta',
          ],
          'status_experimental' => [
            'label' => 'Experimental',
            'status' => 'experimental',
          ],
          'status_deprecated' => [
            'label' => 'Deprecated',
            'status' => 'deprecated',
          ],
          'status_obsolete' => [
            'label' => 'Obsolete',
            'status' => 'obsolete',
          ],
          'additional_property' => [
            'label' => 'bar',
            'baz' => 'bar',
          ],
        ],
      ],
      TRUE,
    ];
    yield 'Options: missing label' => [
      [
        'options' => [
          'foo' => [
            'value' => 'bar',
          ],
        ],
      ],
      FALSE,
    ];
    yield 'Options: empty description' => [
      [
        'options' => [
          'foo' => [
            'label' => 'bar',
            'description' => '',
          ],
        ],
      ],
      FALSE,
    ];
    yield 'Options: invalid target' => [
      [
        'options' => [
          'foo' => [
            'label' => 'bar',
            'target' => 'bar',
          ],
        ],
      ],
      FALSE,
    ];
    yield 'Options: invalid status' => [
      [
        'options' => [
          'foo' => [
            'label' => 'bar',
            'status' => 'bar',
          ],
        ],
      ],
      FALSE,
    ];

    yield 'Library: valid name with underscore' => [
      [
        'library' => 'my_theme/my_library',
      ],
      TRUE,
    ];
    yield 'Library: valid name with hyphen' => [
      [
        'library' => 'my_theme/my-library',
      ],
      TRUE,
    ];
    yield 'Library: valid name with dot' => [
      [
        'library' => 'my_theme/my.library',
      ],
      TRUE,
    ];
    yield 'Library: valid name with hyphen and numbers' => [
      [
        'library' => 'my_theme/my-long-library_with_mixed-hyphens123',
      ],
      TRUE,
    ];
    yield 'Library: invalid theme name case 1' => [
      [
        'library' => 'my-theme/my-library',
      ],
      FALSE,
    ];
    yield 'Library: invalid theme name case 2' => [
      [
        'library' => 'my-theme/my_library',
      ],
      FALSE,
    ];
    yield 'Library: invalid library name case 1' => [
      [
        'library' => 'my_theme/my library',
      ],
      FALSE,
    ];
    yield 'Library: invalid library name special characters' => [
      [
        'library' => 'my-theme/$#random!',
      ],
      FALSE,
    ];
  }

}
