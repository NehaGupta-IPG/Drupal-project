<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\Core\Entity\EntityAccessControlHandler
 *
 * @group Entity
 */
class EntityAccessControlHandlerTest extends UnitTestCase {

  /**
   * The system under test.
   *
   * @var \Drupal\Core\Entity\EntityAccessControlHandler
   */
  protected $entityAccessControlHandler;

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $entityType;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityType = $this->prophesize(EntityTypeInterface::class);

    $this->moduleHandler = $this->prophesize(ModuleHandlerInterface::class);

    $this->entityAccessControlHandler = new EntityAccessControlHandler($this->entityType->reveal());
    $this->entityAccessControlHandler->setModuleHandler($this->moduleHandler->reveal());
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    parent::tearDown();
  }

  /**
   * Provides data to self::testAccess().
   */
  public static function provideAccess(): array {
    return [
      'duplicate allowed' => [
        TRUE,
        'duplicate',
        [
          'entity.foo.administer' => TRUE,
        ],
        'entity.foo.administer',
      ],
      'duplicate forbidden because no view or create access' => [
        NULL,
        'duplicate',
        [
          'entity.foo.administer' => FALSE,
        ],
        'entity.foo.administer',
      ],
      'duplicate forbidden because no admin permission' => [
        NULL,
        'duplicate',
        [
          'entity.foo.administer' => TRUE,
        ],
        FALSE,
      ],
    ];
  }

  /**
   * @covers ::access
   * @covers ::checkAccess
   *
   * @dataProvider provideAccess
   *
   * @param bool|null $expected
   *   TRUE if allowed, FALSE if forbidden, or NULL if neutral.
   * @param string $operation
   *   The operation.
   * @param array $permissions
   *   The permissions.
   * @param string $entity_type_admin_permission
   *   The entity type admin permission.
   */
  public function testAccess(?bool $expected, string $operation, array $permissions, string $entity_type_admin_permission): void {
    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn(2);
    foreach ($permissions as $permission => $has_permission) {
      $account->hasPermission($permission)->willReturn($has_permission);
    }

    $language = $this->prophesize(LanguageInterface::class);

    $entity = $this->prophesize(EntityInterface::class);
    $entity->bundle()->willReturn('');
    $entity->getCacheContexts()->willReturn([]);
    $entity->getCacheMaxAge()->willReturn(7);
    $entity->getCacheTags()->willReturn([]);
    $entity->language()->willReturn($language);
    $entity->uuid()->willReturn('');

    $this->entityType->getAdminPermission()
      ->willReturn($entity_type_admin_permission);

    $this->moduleHandler->invokeAll(Argument::cetera())->willReturn([]);

    $result = $this->entityAccessControlHandler->access($entity->reveal(), $operation, $account->reveal(), TRUE);
    if ($expected === TRUE) {
      $this->assertTrue($result->isAllowed());
    }
    elseif ($expected === FALSE) {
      $this->assertTrue($result->isForbidden());
    }
    if ($expected === NULL) {
      $this->assertTrue($result->isNeutral());
    }
  }

}
