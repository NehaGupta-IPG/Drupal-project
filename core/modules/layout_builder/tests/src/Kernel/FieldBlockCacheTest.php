<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * @group layout_builder
 */
class FieldBlockCacheTest extends EntityKernelTestBase {

  public function testFieldBlockCache(): void {
    $field_storage = FieldStorageConfig::create([
      'type' => 'string',
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
    ]);
    $field_storage->save();

    FieldConfig::create([
      'bundle' => 'entity_test',
      'field_storage' => $field_storage,
    ])->save();

    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('entity_test', 'entity_test')
      ->setComponent('field_test', ['type' => 'string'])
      ->save();

    $this->enableModules(['block_content', 'layout_discovery', 'layout_builder']);

    $logger = new TestLogger();
    $this->container->get(LoggerChannelFactoryInterface::class)
      ->addLogger($logger);

    $this->installEntitySchema('block_content');
    /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $display */
    $this->container->get(EntityDisplayRepositoryInterface::class)
      ->getViewDisplay('entity_test', 'entity_test')
      ->enableLayoutBuilder()
      ->save();
    $this->assertFalse($logger->hasRecordThatContains('The "%plugin_id" block plugin was not found'));
  }

}