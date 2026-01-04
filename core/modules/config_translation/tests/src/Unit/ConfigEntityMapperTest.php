<?php

declare(strict_types=1);

namespace Drupal\Tests\config_translation\Unit;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\locale\LocaleConfigManager;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\config_translation\ConfigEntityMapper;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests the functionality provided by the configuration entity mapper.
 */
#[Group('config_translation')]
class ConfigEntityMapperTest extends UnitTestCase {

  /**
   * The configuration entity mapper to test.
   *
   * @var \Drupal\config_translation\ConfigEntityMapper
   */
  protected $configEntityMapper;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The entity instance used for testing.
   *
   * @var \Drupal\Core\Entity\EntityInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entity;

  /**
   * The route provider used for testing.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $routeProvider;

  /**
   * The mocked language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The mocked event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $this->entity = $this->createMock(ConfigEntityInterface::class);

    $this->routeProvider = $this->createMock(RouteProviderInterface::class);

    $this->routeProvider
      ->expects($this->any())
      ->method('getRouteByName')
      ->with('entity.configurable_language.edit_form')
      ->willReturn(new Route('/admin/config/regional/language/edit/{configurable_language}'));

    $definition = [
      'class' => ConfigEntityMapper::class,
      'base_route_name' => 'entity.configurable_language.edit_form',
      'title' => '@label language',
      'names' => [],
      'entity_type' => 'configurable_language',
      'route_name' => 'config_translation.item.overview.entity.configurable_language.edit_form',
    ];

    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);

    $locale_config_manager = $this->getMockBuilder(LocaleConfigManager::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->languageManager = $this->createMock(LanguageManagerInterface::class);

    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    $this->configEntityMapper = new ConfigEntityMapper(
      'configurable_language',
      $definition,
      $this->getConfigFactoryStub(),
      $typed_config_manager,
      $locale_config_manager,
      $this->createMock(ConfigMapperManagerInterface::class),
      $this->routeProvider,
      $this->getStringTranslationStub(),
      $this->entityTypeManager,
      $this->languageManager,
      $this->eventDispatcher
    );
  }

  /**
   * Tests ConfigEntityMapper::setEntity() and ConfigEntityMapper::getEntity().
   */
  public function testEntityGetterAndSetter(): void {
    $this->entity
      ->expects($this->once())
      ->method('id')
      ->with()
      ->willReturn('entity_id');

    $entity_type = $this->createMock(ConfigEntityTypeInterface::class);
    $entity_type
      ->expects($this->any())
      ->method('getConfigPrefix')
      ->willReturn('config_prefix');
    $this->entityTypeManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('configurable_language')
      ->willReturn($entity_type);

    // No entity is set.
    $this->assertNull($this->configEntityMapper->getEntity());

    $result = $this->configEntityMapper->setEntity($this->entity);
    $this->assertTrue($result);

    // Ensure that the getter provides the entity.
    $this->assertEquals($this->entity, $this->configEntityMapper->getEntity());

    // Ensure that the configuration name was added to the mapper.
    $plugin_definition = $this->configEntityMapper->getPluginDefinition();
    $this->assertContains('config_prefix.entity_id', $plugin_definition['names']);

    // Make sure setEntity() returns FALSE when called a second time.
    $result = $this->configEntityMapper->setEntity($this->entity);
    $this->assertFalse($result);
  }

  /**
   * Tests ConfigEntityMapper::getOverviewRouteParameters().
   */
  public function testGetOverviewRouteParameters(): void {
    $entity_type = $this->createMock(ConfigEntityTypeInterface::class);
    $this->entityTypeManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('configurable_language')
      ->willReturn($entity_type);
    $this->configEntityMapper->setEntity($this->entity);

    $this->entity
      ->expects($this->once())
      ->method('id')
      ->with()
      ->willReturn('entity_id');

    $result = $this->configEntityMapper->getOverviewRouteParameters();

    $this->assertSame(['configurable_language' => 'entity_id'], $result);
  }

  /**
   * Tests ConfigEntityMapper::getType().
   */
  public function testGetType(): void {
    $result = $this->configEntityMapper->getType();
    $this->assertSame('configurable_language', $result);
  }

  /**
   * Tests ConfigEntityMapper::getTypeName().
   */
  public function testGetTypeName(): void {
    $entity_type = $this->createMock(ConfigEntityTypeInterface::class);
    $entity_type->expects($this->once())
      ->method('getLabel')
      ->willReturn('test');
    $this->entityTypeManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('configurable_language')
      ->willReturn($entity_type);

    $result = $this->configEntityMapper->getTypeName();
    $this->assertSame('test', $result);
  }

  /**
   * Tests ConfigEntityMapper::getTypeLabel().
   */
  public function testGetTypeLabel(): void {
    $entity_type = $this->createMock(ConfigEntityTypeInterface::class);
    $entity_type->expects($this->once())
      ->method('getLabel')
      ->willReturn('test');
    $this->entityTypeManager
      ->expects($this->once())
      ->method('getDefinition')
      ->with('configurable_language')
      ->willReturn($entity_type);

    $result = $this->configEntityMapper->getTypeLabel();
    $this->assertSame('test', $result);
  }

  /**
   * Tests ConfigEntityMapper::getOperations().
   */
  public function testGetOperations(): void {
    $result = $this->configEntityMapper->getOperations();

    $expected = [
      'list' => [
        'title' => 'List',
        'url' => Url::fromRoute('config_translation.entity_list', ['mapper_id' => 'configurable_language']),
      ],
    ];

    $this->assertEquals($expected, $result);
  }

}
