<?php

declare(strict_types=1);

namespace Drupal\Tests\config_translation\Unit;

use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\config_translation\ConfigMapperManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drupal\locale\LocaleConfigManager;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\config_translation\ConfigFieldMapper;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the functionality provided by the configuration field mapper.
 */
#[CoversClass(ConfigFieldMapper::class)]
#[Group('config_translation')]
class ConfigFieldMapperTest extends UnitTestCase {

  /**
   * The configuration field mapper to test.
   *
   * @var \Drupal\config_translation\ConfigFieldMapper
   */
  protected $configFieldMapper;

  /**
   * The field config instance used for testing.
   *
   * @var \Drupal\field\FieldConfigInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entity;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

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
    $this->entity = $this->createMock(FieldConfigInterface::class);

    $definition = [
      'class' => ConfigFieldMapper::class,
      'base_route_name' => 'entity.field_config.node_field_edit_form',
      'title' => '@label field',
      'names' => [],
      'entity_type' => 'field_config',
    ];

    $locale_config_manager = $this->getMockBuilder(LocaleConfigManager::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

    $this->configFieldMapper = new ConfigFieldMapper(
      'node_fields',
      $definition,
      $this->getConfigFactoryStub(),
      $this->createMock(TypedConfigManagerInterface::class),
      $locale_config_manager,
      $this->createMock(ConfigMapperManagerInterface::class),
      $this->createMock(RouteProviderInterface::class),
      $this->getStringTranslationStub(),
      $this->entityTypeManager,
      $this->createMock(LanguageManagerInterface::class),
      $this->eventDispatcher
    );
  }

  /**
   * Tests ConfigFieldMapper::setEntity().
   *
   * @legacy-covers ::setEntity
   */
  public function testSetEntity(): void {
    $entity_type = $this->createMock(ConfigEntityTypeInterface::class);
    $entity_type
      ->expects($this->any())
      ->method('getConfigPrefix')
      ->willReturn('config_prefix');

    $this->entityTypeManager
      ->expects($this->any())
      ->method('getDefinition')
      ->willReturn($entity_type);

    $field_storage = $this->createMock(FieldStorageConfigInterface::class);
    $field_storage
      ->expects($this->any())
      ->method('id')
      ->willReturn('field_storage_id');

    $this->entity
      ->expects($this->any())
      ->method('getFieldStorageDefinition')
      ->willReturn($field_storage);

    $result = $this->configFieldMapper->setEntity($this->entity);
    $this->assertTrue($result);

    // Ensure that the configuration name was added to the mapper.
    $plugin_definition = $this->configFieldMapper->getPluginDefinition();
    $this->assertContains('config_prefix.field_storage_id', $plugin_definition['names']);

    // Make sure setEntity() returns FALSE when called a second time.
    $result = $this->configFieldMapper->setEntity($this->entity);
    $this->assertFalse($result);
  }

}
