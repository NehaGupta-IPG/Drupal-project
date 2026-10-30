<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Unit;

use Drupal\Core\Link;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Core\Url;
use Drupal\link\Plugin\Field\FieldType\LinkItem;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests LinkItem::toLink().
 */
#[CoversMethod(LinkItem::class, 'toLink')]
#[Group('link')]
class LinkItemToLinkTest extends UnitTestCase {

  /**
   * Test with a good URL.
   */
  public function testGoodUrl(): void {
    $title_string = 'example';
    $url = Url::fromUri('https://example.com');

    $link_item = $this->createPartialMock(LinkItem::class, [
      'isEmpty',
      'get',
      'getUrl',
    ]);
    $link_item->method('isEmpty')
      ->willReturn(FALSE);
    $title = $this->createMock(TypedDataInterface::class);
    $title->method('getValue')
      ->willReturn($title_string);
    $link_item->method('get')
      ->with('title')
      ->willReturn($title);
    $link_item->method('getUrl')
      ->willReturn($url);

    $link = $link_item->toLink();
    $this->assertInstanceOf(Link::class, $link);
    $this->assertEquals($title_string, $link->getText());
    $this->assertEquals($url, $link->getUrl());
  }

  /**
   * Test with an empty field.
   */
  public function testEmptyField(): void {
    $link_item = $this->createPartialMock(LinkItem::class, ['isEmpty']);
    $link_item->method('isEmpty')
      ->willReturn(TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $link_item->toLink();
  }

  /**
   * Test with a bad URL.
   */
  public function testBadUrl(): void {
    $link_item = $this->createPartialMock(LinkItem::class, [
      'isEmpty',
      'get',
      'getUrl',
    ]);
    $link_item->method('isEmpty')
      ->willReturn(FALSE);
    $title = $this->createMock(TypedDataInterface::class);
    $title->method('getValue')
      ->willReturn(NULL);
    $link_item->method('get')
      ->with('title')
      ->willReturn($title);
    $link_item->method('getUrl')
      ->willThrowException(new \InvalidArgumentException());

    $this->expectException(\InvalidArgumentException::class);
    $link_item->toLink();
  }

}
