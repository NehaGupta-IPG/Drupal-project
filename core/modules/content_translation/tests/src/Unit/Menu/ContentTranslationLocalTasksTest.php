<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Unit\Menu;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Tests\Core\Menu\LocalTaskIntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests content translation local tasks.
 */
#[Group('content_translation')]
class ContentTranslationLocalTasksTest extends LocalTaskIntegrationTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->directoryList = [
      'content_translation' => 'core/modules/content_translation',
      'node' => 'core/modules/node',
      'system' => 'core/modules/system',
    ];
    parent::setUp();

    $entity_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');
    $entity_type->expects($this->any())
      ->method('getLinkTemplate')
      ->willReturnMap([
        ['canonical', 'entity.node.canonical'],
        [
          'drupal:content-translation-overview',
          'entity.node.content_translation_overview',
        ],
      ]);
    $entity_type->expects($this->any())
      ->method('hasLinkTemplate')
      ->with('version-history')
      ->willReturn(TRUE);
    $content_translation_manager = $this->createMock('Drupal\content_translation\ContentTranslationManagerInterface');
    $content_translation_manager->expects($this->any())
      ->method('getSupportedEntityTypes')
      ->willReturn([
        'node' => $entity_type,
      ]);
    // Setup theme handler for ThemeLocalTask.
    $theme_handler = $this->createMock(ThemeHandlerInterface::class);
    $theme_handler->expects($this->any())
      ->method('listInfo')
      ->willReturn([]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn([
        'node' => $entity_type,
      ]);
    \Drupal::getContainer()->set('content_translation.manager', $content_translation_manager);
    \Drupal::getContainer()->set('string_translation', $this->getStringTranslationStub());
    \Drupal::getContainer()->set('theme_handler', $theme_handler);
    \Drupal::getContainer()->set('entity_type.manager', $entity_type_manager);
  }

  /**
   * Tests the block admin display local tasks.
   */
  #[DataProvider('providerTestBlockAdminDisplay')]
  public function testBlockAdminDisplay($route, $expected): void {
    $this->assertLocalTasks($route, $expected);
  }

  /**
   * Provides a list of routes to test.
   */
  public static function providerTestBlockAdminDisplay() {
    return [
      [
        'entity.node.canonical',
        [
          [
            'content_translation.local_tasks:entity.node.content_translation_overview',
            'entity.node.canonical',
            'entity.node.edit_form',
            'entity.node.delete_form',
            'entity.version_history:node.version_history',
          ],
        ],
      ],
      [
        'entity.node.content_translation_overview',
        [
          [
            'content_translation.local_tasks:entity.node.content_translation_overview',
            'entity.node.canonical',
            'entity.node.edit_form',
            'entity.node.delete_form',
            'entity.version_history:node.version_history',
          ],
        ],
      ],
    ];
  }

}
