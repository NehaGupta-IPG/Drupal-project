<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests views contextual links on embed media.
 *
 * @group media
 */
class MediaEmbedContextualLinksTest extends MediaFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'contextual',
    'filter',
    'media',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests contextual links for an embed media to node body.
   */
  public function testMediaEmbedShowContextualLinks(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer filters',
      'administer media',
      'access contextual links',
    ]));
    $this->drupalGet('admin/config/content/formats/add');
    $this->submitForm([
      'name' => 'Media embed with contextual links',
      'format' => 'media_embed_with_contextual_link',
      'filters[media_embed][status]' => 1,
      'filters[media_embed][settings][show_contextual_links]' => TRUE,
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains('Added text format Media embed with contextual links.');

    // Create a media type.
    $mediaType = $this->createMediaType('test');

    // Create a media item.
    $media = Media::create([
      'bundle' => $mediaType->id(),
      'name' => 'Unnamed',
    ]);
    $media->save();

    // Create a node type.
    $node_type = NodeType::create([
      'type' => $this->randomMachineName(),
      'name' => $this->randomString(),
    ]);
    $node_type->save();

    // Add body field to node type.
    node_add_body_field($node_type);

    $node = Node::create([
      'type' => $node_type->id(),
      'title' => 'Test embed media to node body',
    ]);
    $node->body->value = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '" data-view-mode="default">&nbsp;</drupal-media>';
    $node->body->format = 'media_embed_with_contextual_link';
    $node->save();

    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementExists('css', 'div[data-contextual-id*="media:media=' . $media->id() . ':"]');
  }

}
