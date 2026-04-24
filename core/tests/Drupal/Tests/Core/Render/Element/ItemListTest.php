<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Render\Element;

use Drupal\Core\Render\Element\ItemList;
use Drupal\Tests\Core\Render\RendererTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

  /**
   * Tests item list rendering.
   */
  #[DataProvider('providerRenderItemList')]
  public function testRenderItemList(array $element, array $contains, array $not_contains = []): void {
    $output = (string) $this->renderer->renderRoot($element);

    foreach ($contains as $expected) {
      $this->assertStringContainsString($expected, $output);
    }

    foreach ($not_contains as $unexpected) {
      $this->assertStringNotContainsString($unexpected, $output);
    }
  }

  /**
   * Data provider for testRenderItemList().
   */
  public static function providerRenderItemList(): array {
    return [
      'unordered list by default' => [
        [
          '#type' => 'item_list',
          '#items' => ['One', 'Two'],
        ],
        ['<ul', '<li>One</li>', '<li>Two</li>'],
        ['<ol'],
      ],
      'ordered list' => [
        [
          '#type' => 'item_list',
          '#list_type' => 'ol',
          '#items' => ['One', 'Two'],
        ],
        ['<ol', '<li>One</li>', '<li>Two</li>'],
        ['<ul'],
      ],
      'title' => [
        [
          '#type' => 'item_list',
          '#title' => 'My title',
          '#items' => ['One'],
        ],
        ['My title', '<li>One</li>'],
        [],
      ],
      'empty text' => [
        [
          '#type' => 'item_list',
          '#items' => [],
          '#empty' => 'No items',
        ],
        ['No items'],
        ['<li>'],
      ],
      'attributes' => [
        [
          '#type' => 'item_list',
          '#items' => ['One'],
          '#attributes' => [
            'class' => ['my-list'],
            'id' => 'my-list-id',
          ],
        ],
        ['<ul', 'class="my-list"', 'id="my-list-id"', '<li>One</li>'],
        [],
      ],
    ];
  }

}
