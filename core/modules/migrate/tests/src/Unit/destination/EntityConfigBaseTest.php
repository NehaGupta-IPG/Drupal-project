<?php

namespace Drupal\Tests\migrate\Unit\destination;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;
use Drupal\migrate\Row;

/**
 * Tests configuration entity destinations.
 *
 * @group migrate
 * @coversDefaultClass \Drupal\migrate\Plugin\migrate\destination\EntityConfigBase
 */
class EntityConfigBaseTest extends UnitTestCase {

  /**
   * A test entity type ID of the destination config entity used in this test.
   *
   * @const string
   */
  const ENTITY_TYPE_ID = 'test_entity_id';

  /**
   * The plugin being tested.
   *
   * @var \Drupal\migrate\Plugin\migrate\destination\EntityConfigBase
   */
  protected $plugin;

  /**
   * The storage of the test configuration entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityStorage;

  /**
   * The configurable language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The configuration factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   *
   * @see \Drupal\Tests\UnitTestCase::getConfigFactoryStub
   */
  protected $configFactory;

  /**
   * A fully initialized migration Row object.
   *
   * @var \Drupal\migrate\Row
   */
  protected $row;

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    $entity_type = $this->prophesize(ConfigEntityTypeInterface::class);
    $entity_type->getPluralLabel()->willReturn('plural label');
    $entity_type->getKey('id')->willReturn('id');
    $entity_type->getKey('bundle')->willReturn('');

    $this->entityStorage = $this->getMockBuilder(EntityStorageInterface::class)->getMock();
    $this->entityStorage->method('getEntityType')->willReturn($entity_type->reveal());
    $this->entityStorage->method('create')
      ->will($this->returnCallback(function ($values) {
        return $this->getMockForAbstractClass(ConfigEntityBase::class, [
          $values,
          self::ENTITY_TYPE_ID,
        ]);
      }));

    $this->languageManager = $this->getMockBuilder(ConfigurableLanguageManagerInterface::class)
      ->getMock();
    $this->configFactory = $this->getConfigFactoryStub();

    $entity_type_manager = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entity_type_manager->method('getStorage')->with(self::ENTITY_TYPE_ID)
      ->willReturn($this->entityStorage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $this->plugin = $this->getPlugin();

    $this->row = new Row(
      [
        'id1' => 'id1',
        'id2' => 'id2',
        'test_property' => 'initial value',
      ],
      [
        'id1' => 'id1',
        'id2' => 'id2',
      ]
    );
    foreach ($this->row->getSource() as $prop => $value) {
      $this->row->setDestinationProperty($prop, $value);
    }
  }

  /**
   * Tests the initial and update imports of a config entity.
   *
   * @covers ::import
   */
  public function testEntityImportUpdate() {
    // Execute an initial import: no previous destination IDs.
    $initial_destination_ids = $this->plugin->import(
      $this->row,
      []
    );
    // Ensure that the source and destination IDs are the same.
    $this->assertEquals(array_values($this->row->getSourceIdValues()), $initial_destination_ids);

    // Test an update.
    // Initialize a preexisting configuration entity.
    $preexisting_config = $this->getMockForAbstractClass(
      ConfigEntityBase::class,
      [
        $this->row->getDestination(),
        self::ENTITY_TYPE_ID,
      ]
    );
    // Ensure that the preexisting config entity has the expected initial
    // property value. In this test, the source and destination IDs are the
    // same.
    $destination_ids = $this->row->getSourceIdValues();
    $destination_id = implode('.', $destination_ids);
    $this->assertEquals('initial value', $preexisting_config->get('test_property'));
    $this->entityStorage
      ->method('load')
      ->with($destination_id)
      ->willReturn($preexisting_config);

    $this->row->setDestinationProperty('test_property', 'updated value');
    $update_destination_ids = $this->plugin->import(
      $this->row,
      $destination_ids
    );

    $this->assertEquals(array_values($this->row->getSourceIdValues()), $update_destination_ids);
    // Ensure the property was updated.
    $this->assertEquals('updated value', $preexisting_config->get('test_property'));
  }

  /**
   * Returns an EntityConfigBase plugin instance for testing.
   *
   * @param bool $translations
   *   Whether the plugin should be configured for importing translations.
   *   Defaults to FALSE.
   *
   * @return \Drupal\Tests\migrate\Unit\destination\TestEntityConfigBase
   *   A fully configured test plugin instance.
   */
  protected function getPlugin(bool $translations = FALSE) {
    return new TestEntityConfigBase(
      [
        'plugin' => 'entity:' . self::ENTITY_TYPE_ID,
        'translations' => $translations,
      ],
      'entity' . self::ENTITY_TYPE_ID,
      ['id' => 'entity' . self::ENTITY_TYPE_ID],
      $this->prophesize(Migration::class)->reveal(),
      $this->entityStorage,
      [],
      $this->languageManager,
      $this->configFactory,
    );
  }

}

/**
 * An EntityConfigBase plugin instance for testing.
 *
 * This plugin has multiple destination IDs.
 */
class TestEntityConfigBase extends EntityConfigBase {

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['id1']['type'] = 'string';
    $ids['id2']['type'] = 'string';
    if ($this->isTranslationDestination()) {
      $ids['langcode']['type'] = 'string';
    }
    return $ids;
  }

}
