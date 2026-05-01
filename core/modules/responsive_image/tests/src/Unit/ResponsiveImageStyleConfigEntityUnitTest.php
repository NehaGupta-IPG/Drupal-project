<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Unit;

use Drupal\breakpoint\BreakpointInterface;
use Drupal\breakpoint\BreakpointManagerInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\MimeType\MimeTypeMapInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\image\Entity\ImageStyle;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;
use Drupal\responsive_image\ResponsiveImageBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\responsive_image\Entity\ResponsiveImageStyle.
 */
#[CoversClass(ResponsiveImageStyle::class)]
#[Group('block')]
class ResponsiveImageStyleConfigEntityUnitTest extends UnitTestCase {

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $entityType;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $entityTypeManager;

  /**
   * The breakpoint manager used for testing.
   *
   * @var \Drupal\breakpoint\BreakpointManagerInterface|\PHPUnit\Framework\MockObject\Stub
   */
  protected $breakpointManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityType = $this->createStub(EntityTypeInterface::class);
    $this->entityType
      ->method('getProvider')
      ->willReturn('responsive_image');

    $this->entityTypeManager = $this->createStub(EntityTypeManagerInterface::class);
    $this->entityTypeManager
      ->method('getDefinition')
      ->willReturn($this->entityType);

    $this->breakpointManager = $this->createStub(BreakpointManagerInterface::class);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('breakpoint.manager', $this->breakpointManager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests calculate dependencies.
   */
  public function testCalculateDependencies(): void {
    // Set up image style loading mock.
    $styles = [];
    foreach (['fallback', 'small', 'medium', 'large'] as $style) {
      $mock = $this->createStub(ConfigEntityInterface::class);
      $mock
        ->method('getConfigDependencyName')
        ->willReturn('image.style.' . $style);
      $styles[$style] = $mock;
    }
    $storage = $this->createMock(ConfigEntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadMultiple')
      ->with(array_keys($styles))
      ->willReturn($styles);

    $this->entityTypeManager
      ->method('getStorage')
      ->willReturn($storage);

    $entity_type_repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $entity_type_repository->expects($this->once())
      ->method('getEntityTypeFromClass')
      ->with('Drupal\image\Entity\ImageStyle')
      ->willReturn('image_style');

    $entity = new ResponsiveImageStyle(['breakpoint_group' => 'test_group']);
    $entity->setBreakpointGroup('test_group');
    $entity->setFallbackImageStyle('fallback');
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'small',
    ]);
    $entity->addImageStyleMapping('test_breakpoint', '2x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width:700px) 700px, 100vw',
        'sizes_image_styles' => [
          'medium' => 'medium',
          'large' => 'large',
        ],
      ],
    ]);

    $this->breakpointManager
      ->method('getGroupProviders')
      ->willReturn(['olivero' => 'theme', 'toolbar' => 'module']);

    \Drupal::getContainer()->set('entity_type.repository', $entity_type_repository);

    $dependencies = $entity->calculateDependencies()->getDependencies();
    $this->assertEquals(['toolbar'], $dependencies['module']);
    $this->assertEquals(['olivero'], $dependencies['theme']);
    $this->assertEquals(['image.style.fallback', 'image.style.large', 'image.style.medium', 'image.style.small'], $dependencies['config']);
  }

  /**
   * Tests has image style mappings.
   *
   * @legacy-covers ::addImageStyleMapping
   * @legacy-covers ::hasImageStyleMappings
   */
  public function testHasImageStyleMappings(): void {
    $entity = new ResponsiveImageStyle([]);
    $this->assertFalse($entity->hasImageStyleMappings());
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => '',
    ]);
    $this->assertFalse($entity->hasImageStyleMappings());
    $entity->removeImageStyleMappings();
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width:700px) 700px, 100vw',
        'sizes_image_styles' => [],
      ],
    ]);
    $this->assertFalse($entity->hasImageStyleMappings());
    $entity->removeImageStyleMappings();
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '',
        'sizes_image_styles' => [
          'large' => 'large',
        ],
      ],
    ]);
    $this->assertFalse($entity->hasImageStyleMappings());
    $entity->removeImageStyleMappings();
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ]);
    $this->assertTrue($entity->hasImageStyleMappings());
    $entity->removeImageStyleMappings();
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width:700px) 700px, 100vw',
        'sizes_image_styles' => [
          'large' => 'large',
        ],
      ],
    ]);
    $this->assertTrue($entity->hasImageStyleMappings());
  }

  /**
   * Tests get image style mapping.
   *
   * @legacy-covers ::addImageStyleMapping
   * @legacy-covers ::getImageStyleMapping
   */
  public function testGetImageStyleMapping(): void {
    $entity = new ResponsiveImageStyle(['']);
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ]);
    $expected = [
      'breakpoint_id' => 'test_breakpoint',
      'multiplier' => '1x',
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ];
    $this->assertEquals($expected, $entity->getImageStyleMapping('test_breakpoint', '1x'));
    $this->assertNull($entity->getImageStyleMapping('test_unknown_breakpoint', '1x'));
  }

  /**
   * Tests get keyed image style mappings.
   *
   * @legacy-covers ::addImageStyleMapping
   * @legacy-covers ::getKeyedImageStyleMappings
   */
  public function testGetKeyedImageStyleMappings(): void {
    $entity = new ResponsiveImageStyle(['']);
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ]);
    $entity->addImageStyleMapping('test_breakpoint', '2x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width:700px) 700px, 100vw',
        'sizes_image_styles' => [
          'large' => 'large',
        ],
      ],
    ]);
    $entity->addImageStyleMapping('test_breakpoint2', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'thumbnail',
    ]);
    $entity->addImageStyleMapping('test_breakpoint2', '2x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => '_original image_',
    ]);

    $expected = [
      'test_breakpoint' => [
        '1x' => [
          'breakpoint_id' => 'test_breakpoint',
          'multiplier' => '1x',
          'image_mapping_type' => 'image_style',
          'image_mapping' => 'large',
        ],
        '2x' => [
          'breakpoint_id' => 'test_breakpoint',
          'multiplier' => '2x',
          'image_mapping_type' => 'sizes',
          'image_mapping' => [
            'sizes' => '(min-width:700px) 700px, 100vw',
            'sizes_image_styles' => [
              'large' => 'large',
            ],
          ],
        ],
      ],
      'test_breakpoint2' => [
        '1x' => [
          'breakpoint_id' => 'test_breakpoint2',
          'multiplier' => '1x',
          'image_mapping_type' => 'image_style',
          'image_mapping' => 'thumbnail',
        ],
        '2x' => [
          'breakpoint_id' => 'test_breakpoint2',
          'multiplier' => '2x',
          'image_mapping_type' => 'image_style',
          'image_mapping' => '_original image_',
        ],
      ],
    ];
    $this->assertEquals($expected, $entity->getKeyedImageStyleMappings());

    // Add another mapping to ensure keyed mapping static cache is rebuilt.
    $entity->addImageStyleMapping('test_breakpoint2', '2x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'medium',
    ]);
    $expected['test_breakpoint2']['2x'] = [
      'breakpoint_id' => 'test_breakpoint2',
      'multiplier' => '2x',
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'medium',
    ];
    $this->assertEquals($expected, $entity->getKeyedImageStyleMappings());

    // Overwrite a mapping to ensure keyed mapping static cache is rebuilt.
    $entity->addImageStyleMapping('test_breakpoint2', '2x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ]);
    $expected['test_breakpoint2']['2x'] = [
      'breakpoint_id' => 'test_breakpoint2',
      'multiplier' => '2x',
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ];
    $this->assertEquals($expected, $entity->getKeyedImageStyleMappings());
  }

  /**
   * Tests get image style mappings.
   *
   * @legacy-covers ::addImageStyleMapping
   * @legacy-covers ::getImageStyleMappings
   */
  public function testGetImageStyleMappings(): void {
    $entity = new ResponsiveImageStyle(['']);
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ]);
    $entity->addImageStyleMapping('test_breakpoint', '2x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width:700px) 700px, 100vw',
        'sizes_image_styles' => [
          'large' => 'large',
        ],
      ],
    ]);
    $entity->addImageStyleMapping('test_breakpoint2', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'thumbnail',
    ]);

    $expected = [
      [
        'breakpoint_id' => 'test_breakpoint',
        'multiplier' => '1x',
        'image_mapping_type' => 'image_style',
        'image_mapping' => 'large',
      ],
      [
        'breakpoint_id' => 'test_breakpoint',
        'multiplier' => '2x',
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width:700px) 700px, 100vw',
          'sizes_image_styles' => [
            'large' => 'large',
          ],
        ],
      ],
      [
        'breakpoint_id' => 'test_breakpoint2',
        'multiplier' => '1x',
        'image_mapping_type' => 'image_style',
        'image_mapping' => 'thumbnail',
      ],
    ];
    $this->assertEquals($expected, $entity->getImageStyleMappings());
  }

  /**
   * Tests remove image style mappings.
   *
   * @legacy-covers ::addImageStyleMapping
   * @legacy-covers ::removeImageStyleMappings
   */
  public function testRemoveImageStyleMappings(): void {
    $entity = new ResponsiveImageStyle(['']);
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ]);
    $entity->addImageStyleMapping('test_breakpoint', '2x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width:700px) 700px, 100vw',
        'sizes_image_styles' => [
          'large' => 'large',
        ],
      ],
    ]);
    $entity->addImageStyleMapping('test_breakpoint2', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'thumbnail',
    ]);

    $this->assertTrue($entity->hasImageStyleMappings());
    $entity->removeImageStyleMappings();
    $this->assertEmpty($entity->getImageStyleMappings());
    $this->assertEmpty($entity->getKeyedImageStyleMappings());
    $this->assertFalse($entity->hasImageStyleMappings());
  }

  /**
   * Tests set breakpoint group.
   *
   * @legacy-covers ::setBreakpointGroup
   * @legacy-covers ::getBreakpointGroup
   */
  public function testSetBreakpointGroup(): void {
    $entity = new ResponsiveImageStyle(['breakpoint_group' => 'test_group']);
    $entity->addImageStyleMapping('test_breakpoint', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ]);
    $entity->addImageStyleMapping('test_breakpoint', '2x', [
      'image_mapping_type' => 'sizes',
      'image_mapping' => [
        'sizes' => '(min-width:700px) 700px, 100vw',
        'sizes_image_styles' => [
          'large' => 'large',
        ],
      ],
    ]);
    $entity->addImageStyleMapping('test_breakpoint2', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'thumbnail',
    ]);

    // Ensure that setting to same group does not remove mappings.
    $entity->setBreakpointGroup('test_group');
    $this->assertTrue($entity->hasImageStyleMappings());
    $this->assertEquals('test_group', $entity->getBreakpointGroup());

    // Ensure that changing the group removes mappings.
    $entity->setBreakpointGroup('test_group2');
    $this->assertEquals('test_group2', $entity->getBreakpointGroup());
    $this->assertFalse($entity->hasImageStyleMappings());
  }

  /**
   * Tests that 'auto' is prepended to sizes when loading is lazy.
   */
  public function testAutoSizesWithLazyLoading(): void {
    $builder = $this->createResponsiveImageBuilder();
    $breakpoint = $this->createStub(BreakpointInterface::class);
    $breakpoint->method('getMediaQuery')
      ->willReturn('(min-width: 560px)');

    $variables = [
      'uri' => 'public://test.jpeg',
      'width' => 360,
      'height' => 240,
      'attributes' => [
        'loading' => 'lazy',
      ],
    ];
    $multipliers = [
      '1x' => [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 700px) 700px, 100vw',
          'sizes_image_styles' => ['medium'],
        ],
      ],
    ];

    $result = $builder->buildSourceAttributes($variables, $breakpoint, $multipliers);
    $sizes_value = (string) $result->offsetGet('sizes');
    $this->assertStringStartsWith('auto', $sizes_value, "Sizes attribute should start with 'auto' when loading is lazy.");
  }

  /**
   * Tests that 'auto' is not prepended to sizes when loading is eager.
   */
  public function testNoAutoSizesWithEagerLoading(): void {
    $builder = $this->createResponsiveImageBuilder();
    $breakpoint = $this->createStub(BreakpointInterface::class);
    $breakpoint->method('getMediaQuery')
      ->willReturn('(min-width: 560px)');

    $variables = [
      'uri' => 'public://test.jpeg',
      'width' => 360,
      'height' => 240,
      'attributes' => [
        'loading' => 'eager',
      ],
    ];
    $multipliers = [
      '1x' => [
        'image_mapping_type' => 'sizes',
        'image_mapping' => [
          'sizes' => '(min-width: 700px) 700px, 100vw',
          'sizes_image_styles' => ['medium'],
        ],
      ],
    ];

    $result = $builder->buildSourceAttributes($variables, $breakpoint, $multipliers);
    $sizes_value = (string) $result->offsetGet('sizes');
    $this->assertStringStartsNotWith('auto', $sizes_value, "Sizes attribute should not start with 'auto' when loading is eager.");
  }

  /**
   * Creates a ResponsiveImageBuilder with stubbed dependencies.
   *
   * @return \Drupal\responsive_image\ResponsiveImageBuilder
   *   A responsive image builder instance.
   */
  protected function createResponsiveImageBuilder(): ResponsiveImageBuilder {
    $image_style = $this->createStub(ImageStyle::class);
    $image_style->method('transformDimensions')
      ->willReturnCallback(function (&$dimensions) {
        $dimensions['width'] = 220;
        $dimensions['height'] = 220;
      });
    $image_style->method('getDerivativeExtension')
      ->willReturn('jpeg');
    $image_style->method('buildUrl')
      ->willReturnCallback(function ($uri) {
        return 'http://example.com/styles/medium/' . basename($uri);
      });

    $storage = $this->createStub(EntityStorageInterface::class);
    $storage->method('load')
      ->willReturn($image_style);

    $entity_type_manager = $this->createStub(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->willReturn($storage);

    $entity_type_repository = $this->createStub(EntityTypeRepositoryInterface::class);
    $entity_type_repository->method('getEntityTypeFromClass')
      ->willReturn('image_style');

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('entity_type.repository', $entity_type_repository);
    \Drupal::setContainer($container);

    $mime_type_map = $this->createStub(MimeTypeMapInterface::class);
    $mime_type_map->method('getMimeTypeForExtension')
      ->willReturn('image/jpeg');

    $file_url_generator = $this->createStub(FileUrlGeneratorInterface::class);
    $file_url_generator->method('transformRelative')
      ->willReturnCallback(function ($url) {
        return $url;
      });

    return new ResponsiveImageBuilder(
      $entity_type_manager,
      $mime_type_map,
      $this->createStub(ImageFactory::class),
      $file_url_generator,
    );
  }

}
