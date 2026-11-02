<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Unit\Menu;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\Core\Menu\LocalTaskIntegrationTestBase;

/**
 * Tests media local tasks.
 *
 * @group media
 */
class MediaLocalTasksTest extends LocalTaskIntegrationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->directoryList = ['media' => 'core/modules/media'];
    parent::setUp();

    // Add services required for user local tasks.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([]);

    // Add config object mock for derivative.
    $config = $this->createMock(ImmutableConfig::class);
    $config->expects($this->any())
      ->method('get')
      ->willReturn([]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->any())
      ->method('get')
      ->willReturn($config);

    $this->container->set('entity_type.manager', $entity_type_manager);
    $this->container->set('string_translation', $this->getStringTranslationStub());
    $this->container->set('config.factory', $config_factory);
  }

  /**
   * Checks media tasks.
   *
   * @dataProvider getMediaReportRoutes
   */
  public function testMediaReportLocalTasks($route, $expected): void {
    $this->assertLocalTasks($route, $expected);
  }

  /**
   * Provides a list of routes to test.
   *
   * @return array[]
   *   An array of routes and expected local tasks.
   */
  public static function getMediaReportRoutes(): array {
    return [
      ['entity.media_type.edit_form', [['entity.media_type.edit_form']]],
      ['entity.media_type.collection', [['entity.media_type.collection']]],
      ['entity.media.collection', [['entity.media.collection']]],
      ['media.admin', [['media.admin']]],
    ];
  }

}
