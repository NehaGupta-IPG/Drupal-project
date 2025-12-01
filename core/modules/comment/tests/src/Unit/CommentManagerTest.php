<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Unit;

use Drupal\comment\CommentManager;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\comment\CommentManager.
 */
#[CoversClass(CommentManager::class)]
#[Group('comment')]
class CommentManagerTest extends UnitTestCase {

  /**
   * Tests the getFields method.
   *
   * @legacy-covers ::getFields
   */
  public function testGetFields(): void {
    // Set up a content entity type.
    $entity_type = $this->createMock(\Drupal\Core\Entity\ContentEntityTypeInterface::class);
    $entity_type->expects($this->any())
      ->method('getClass')
      ->willReturn('Node');
    $entity_type->expects($this->any())
      ->method('entityClassImplements')
      ->with(FieldableEntityInterface::class)
      ->willReturn(TRUE);

    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $entity_field_manager->expects($this->once())
      ->method('getFieldMapByFieldType')
      ->willReturn([
        'node' => [
          'field_foobar' => [
            'type' => 'comment',
          ],
        ],
      ]);

    $entity_type_manager->expects($this->any())
      ->method('getDefinition')
      ->willReturn($entity_type);

    $comment_manager = new CommentManager(
      $entity_type_manager,
      $this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class),
      $this->createMock(\Drupal\Core\StringTranslation\TranslationInterface::class),
      $this->createMock(\Drupal\Core\Extension\ModuleHandlerInterface::class),
      $this->createMock(AccountInterface::class),
      $entity_field_manager,
      $this->prophesize(EntityDisplayRepositoryInterface::class)->reveal()
    );
    $comment_fields = $comment_manager->getFields('node');
    $this->assertArrayHasKey('field_foobar', $comment_fields);
  }

}
