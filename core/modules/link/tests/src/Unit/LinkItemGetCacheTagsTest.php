<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Unit;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\link\Plugin\Field\FieldType\LinkItem;
use Drupal\node\Entity\Node;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests LinkItem.
 */
#[CoversMethod(LinkItem::class, 'getCacheTags')]
#[Group('link')]
class LinkItemGetCacheTagsTest extends UnitTestCase {

  /**
   * Verify no cache tags are returned for non-entity URIs.
   *
   * @param string $uri
   *   The URI to test.
   */
  #[TestWith(['route:<front>'])]
  #[TestWith(['https://example.com'])]
  #[TestWith(['this is a malformed uri'])]
  public function testNonEntityUris(string $uri): void {
    $link_item = $this->createPartialMock(LinkItem::class, []);
    $link_item->uri = $uri;
    $this->assertSame([], $link_item->getCacheTags());
  }

  /**
   * Verify no cache tags are returned if the entity storage can't be loaded.
   */
  public function testBadEntityType(): void {
    $entity_type_name = 'bad_entity_type';
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())
      ->method('getStorage')
      ->with($entity_type_name)
      ->willThrowException(new PluginNotFoundException($entity_type_name));
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    \Drupal::setContainer($container);

    $link_item = $this->createPartialMock(LinkItem::class, []);
    $link_item->uri = "entity:$entity_type_name/1";
    $this->assertSame([], $link_item->getCacheTags());
  }

  /**
   * Verify cache tags are returned for a referenced entity.
   */
  public function testGoodReference(): void {
    $tags = ['node:1'];
    $node = $this->createMock(Node::class);
    $node->expects($this->once())
      ->method('getCacheTags')
      ->willReturn($tags);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('load')
      ->with(1)
      ->willReturn($node);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->expects($this->once())
      ->method('getStorage')
      ->with('node')
      ->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $manager);
    \Drupal::setContainer($container);

    $link_item = $this->createPartialMock(LinkItem::class, []);
    $link_item->uri = 'entity:node/1';
    $this->assertSame($tags, $link_item->getCacheTags());
  }

}
