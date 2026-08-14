<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_link_content\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\menu_link_content\Hook\MenuLinkContentHooks;
use Drupal\path_alias\PathAliasInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MenuLinkContentHooks path alias handling.
 */
#[CoversClass(MenuLinkContentHooks::class)]
#[Group('menu_link_content')]
class MenuLinkContentHooksTest extends UnitTestCase {

  /**
   * Tests that insert does not look up menu links for a NULL or empty alias.
   *
   * @param string|null $alias
   *   The path alias value.
   */
  #[DataProvider('providerEmptyPathAlias')]
  public function testPathAliasInsertIgnoresEmptyAlias(?string $alias): void {
    $hooks = $this->createHooks($this->createEntityTypeManagerExpectingNoStorageLookup());
    $path_alias = $this->createStub(PathAliasInterface::class);
    $path_alias->method('getAlias')->willReturn($alias);
    $hooks->pathAliasInsert($path_alias);
  }

  /**
   * Tests that delete does not look up menu links for a NULL or empty alias.
   *
   * @param string|null $alias
   *   The path alias value.
   */
  #[DataProvider('providerEmptyPathAlias')]
  public function testPathAliasDeleteIgnoresEmptyAlias(?string $alias): void {
    $hooks = $this->createHooks($this->createEntityTypeManagerExpectingNoStorageLookup());
    $path_alias = $this->createStub(PathAliasInterface::class);
    $path_alias->method('getAlias')->willReturn($alias);
    $hooks->pathAliasDelete($path_alias);
  }

  /**
   * Tests that update skips a NULL or empty new alias.
   *
   * @param string|null $alias
   *   The new path alias value.
   */
  #[DataProvider('providerEmptyPathAlias')]
  public function testPathAliasUpdateIgnoresEmptyNewAlias(?string $alias): void {
    $hooks = $this->createHooks($this->createEntityTypeManagerExpectingUriLookup('internal:/old-alias'));
    $original = $this->createStub(PathAliasInterface::class);
    $original->method('getAlias')->willReturn('/old-alias');
    $path_alias = $this->createStub(PathAliasInterface::class);
    $path_alias->method('getAlias')->willReturn($alias);
    $path_alias->method('getOriginal')->willReturn($original);
    $hooks->pathAliasUpdate($path_alias);
  }

  /**
   * Tests that update skips a NULL or empty original alias.
   *
   * @param string|null $alias
   *   The original path alias value.
   */
  #[DataProvider('providerEmptyPathAlias')]
  public function testPathAliasUpdateIgnoresEmptyOriginalAlias(?string $alias): void {
    $hooks = $this->createHooks($this->createEntityTypeManagerExpectingUriLookup('internal:/new-alias'));
    $original = $this->createStub(PathAliasInterface::class);
    $original->method('getAlias')->willReturn($alias);
    $path_alias = $this->createStub(PathAliasInterface::class);
    $path_alias->method('getAlias')->willReturn('/new-alias');
    $path_alias->method('getOriginal')->willReturn($original);
    $hooks->pathAliasUpdate($path_alias);
  }

  /**
   * Provides NULL and empty path alias values.
   *
   * @return array
   *   Test cases keyed by a description.
   */
  public static function providerEmptyPathAlias(): array {
    return [
      'NULL' => [NULL],
      'empty string' => [''],
    ];
  }

  /**
   * Creates the hooks object under test.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return \Drupal\menu_link_content\Hook\MenuLinkContentHooks
   *   The hooks object.
   */
  protected function createHooks(EntityTypeManagerInterface $entity_type_manager): MenuLinkContentHooks {
    return new MenuLinkContentHooks(
      $entity_type_manager,
      $this->createStub(ModuleHandlerInterface::class),
      $this->createStub(MenuLinkManagerInterface::class),
    );
  }

  /**
   * Creates an entity type manager that must not load entity storage.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager mock.
   */
  protected function createEntityTypeManagerExpectingNoStorageLookup(): EntityTypeManagerInterface {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->never())->method('getStorage');
    return $entity_type_manager;
  }

  /**
   * Creates an entity type manager that loads menu links once for a URI.
   *
   * @param string $uri
   *   The internal URI expected in the storage lookup.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager mock.
   */
  protected function createEntityTypeManagerExpectingUriLookup(string $uri): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['link.uri' => $uri])
      ->willReturn([]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('menu_link_content')
      ->willReturn($storage);
    return $entity_type_manager;
  }

}
