<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\area;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\filter\FilterFormatInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\area\Text;

/**
 * @coversDefaultClass \Drupal\views\Plugin\views\area\Text
 * @group views
 */
class TextTest extends UnitTestCase {

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependencies(): void {
    $dependency_key = 'config';
    $format_id = 'test_format';

    $format = $this->createMock(FilterFormatInterface::class);
    $format->expects($this->once())
      ->method('getConfigDependencyKey')
      ->willReturn($dependency_key);
    $format->expects($this->once())
      ->method('getConfigDependencyName')
      ->willReturn('filter.format.' . $format_id);
    $format_storage = $this->createMock(EntityStorageInterface::class);
    $format_storage->expects($this->once())
      ->method('load')
      ->with($format_id)
      ->willReturn($format);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('filter_format')
      ->willReturn($format_storage);

    $plugin = new Text([], 'text', [], $entity_type_manager);
    $plugin->options['content']['format'] = $format_id;
    $expected = [$dependency_key => ['filter.format.' . $format_id]];
    $this->assertEquals($expected, $plugin->calculateDependencies());
  }

}
