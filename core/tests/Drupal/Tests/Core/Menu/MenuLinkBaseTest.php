<?php

namespace Drupal\Tests\Core\Menu;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the menu link base object.
 *
 * @group Menu
 *
 * @coversDefaultClass \Drupal\Core\Menu\MenuLinkBase
 */
class MenuLinkBaseTest extends UnitTestCase {

  /**
   * Tests getOperations.
   *
   * @covers ::getOperations
   *
   * @dataProvider providerTestGetOperations
   *
   * @param bool $resettable
   *   Is the Menu link resettable.
   */
  public function testGetOperations(bool $resettable): void {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $link = MenuLinkNoOperationLinksMock::create(['id' => 'test', 'is_resettable' => $resettable]);
    $operations = $link->getOperations();
    $this->assertArrayHasKey('edit', $operations);
    $this->assertArrayNotHasKey('delete', $operations);
    $this->assertArrayNotHasKey('reset', $operations);
    $this->assertArrayNotHasKey('translate', $operations);
  }

  /**
   * Data provider for testGetOperations.
   *
   * @return array
   *   Values to test.
   */
  public function providerTestGetOperations(): array {
    return [
      'is_resettable' => [TRUE],
      'is_not_resettable' => [FALSE],
    ];
  }

}
