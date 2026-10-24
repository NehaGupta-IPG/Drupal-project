<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Render\Element\ItemList;
use Drupal\Tests\Core\Render\RendererTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Render\Element\ItemList.
 */
#[CoversClass(ItemList::class)]
#[Group('Render')]
class ItemListTest extends RendererTestBase {

  /**
   * Tests getInfo().
   */
  public function testGetInfo(): void {
    $item_list = new ItemList([], 'item_list', 'item_list');
    $info = $item_list->getInfo();

    $this->assertSame('item_list', $info['#theme']);
    $this->assertSame([], $info['#items']);
    $this->assertNull($info['#title']);
    $this->assertSame('ul', $info['#list_type']);
    $this->assertNull($info['#empty']);
    $this->assertSame([], $info['#attributes']);
    $this->assertSame([], $info['#wrapper_attributes']);
    $this->assertSame([], $info['#context']);
  }

}
