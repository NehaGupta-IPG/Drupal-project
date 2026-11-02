<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityViewDisplayConfigUpdater;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Entity\EntityViewDisplayConfigUpdater.
 */
#[CoversClass(EntityViewDisplayConfigUpdater::class)]
#[Group('Entity')]
class EntityViewDisplayConfigUpdaterTest extends UnitTestCase {

  /**
   * Tests that existing formatter configuration is preserved.
   */
  #[DataProvider('configuredLabelProvider')]
  public function testPreserveConfiguration(string $label): void {
    $component = [
      'type' => 'language',
      'label' => $label,
      'settings' => [
        'link_to_entity' => TRUE,
        'native_language' => TRUE,
      ],
      'weight' => 3,
      'region' => 'content',
    ];
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getTargetEntityTypeId')->willReturn('node');
    $display->method('getComponent')->with('langcode')->willReturn($component);
    $display->expects($this->never())->method('setComponent');

    $updater = new EntityViewDisplayConfigUpdater();
    $this->assertFalse($updater->updateEntityViewDisplay($display));
    $this->assertFalse($updater->needsEntityViewDisplayUpdate($display));
  }

  /**
   * Provides configured label positions.
   */
  public static function configuredLabelProvider(): array {
    return [
      'inline' => ['inline'],
      'above' => ['above'],
      'hidden' => ['hidden'],
      'visually hidden' => ['visually_hidden'],
    ];
  }

  /**
   * Tests that defaults are added once without replacing existing settings.
   */
  #[DataProvider('missingDefaultsProvider')]
  public function testAddMissingDefaults(array $component, array $expected): void {
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getTargetEntityTypeId')->willReturn('node');
    $display->method('getComponent')->with('langcode')->willReturnCallback(
      static function () use (&$component): array {
        return $component;
      }
    );
    $display->expects($this->once())->method('setComponent')
      ->with('langcode', $expected)
      ->willReturnCallback(static function (string $name, array $options) use (&$component, $display) {
        $component = $options;
        return $display;
      });

    $updater = new EntityViewDisplayConfigUpdater();
    $this->assertTrue($updater->updateEntityViewDisplay($display));
    $this->assertSame($expected, $component);
    $this->assertFalse($updater->updateEntityViewDisplay($display));
  }

  /**
   * Provides incomplete language formatter configuration.
   */
  public static function missingDefaultsProvider(): array {
    return [
      'no defaults' => [
        ['type' => 'language'],
        [
          'type' => 'language',
          'label' => 'above',
          'settings' => ['link_to_entity' => FALSE, 'native_language' => FALSE],
        ],
      ],
      'inline label with partial settings' => [
        [
          'label' => 'inline',
          'settings' => ['native_language' => TRUE],
        ],
        [
          'label' => 'inline',
          'settings' => ['native_language' => TRUE, 'link_to_entity' => FALSE],
        ],
      ],
      'hidden label without settings' => [
        ['label' => 'hidden', 'weight' => 3],
        [
          'label' => 'hidden',
          'weight' => 3,
          'settings' => ['link_to_entity' => FALSE, 'native_language' => FALSE],
        ],
      ],
    ];
  }

  /**
   * Tests that unrelated displays are not changed.
   */
  #[DataProvider('unrelatedDisplayProvider')]
  public function testUnrelatedDisplay(string $entity_type_id): void {
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getTargetEntityTypeId')->willReturn($entity_type_id);
    $display->expects($entity_type_id === 'node' ? $this->once() : $this->never())
      ->method('getComponent')->with('langcode')->willReturn(NULL);
    $display->expects($this->never())->method('setComponent');

    $this->assertFalse((new EntityViewDisplayConfigUpdater())->updateEntityViewDisplay($display));
  }

  /**
   * Provides displays without a language formatter to update.
   */
  public static function unrelatedDisplayProvider(): array {
    return [
      'node without language component' => ['node'],
      'non-node display' => ['taxonomy_term'],
    ];
  }

}
