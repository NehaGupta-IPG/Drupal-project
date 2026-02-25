<?php

declare(strict_types=1);

namespace Drupal\Tests\link\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests link formatter access check behavior.
 */
#[Group('link')]
#[RunTestsInSeparateProcesses]
final class LinkFormatterAccessCheckTest extends FieldKernelTestBase {

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->renderer = $this->container->get('renderer');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installConfig(['node']);
    $this->installSchema('node', ['node_access']);

    // Give anonymous users permission to access content.
    $anonymous_role = Role::load(RoleInterface::ANONYMOUS_ID);
    $anonymous_role->grantPermission('access content');
    $anonymous_role->save();

    // Add a link field to the test entity type.
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_link',
      'type' => 'link',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_link',
      'bundle' => 'entity_test',
      'settings' => [
        'title' => 1,
      ],
    ])->save();
  }

  /**
   * Tests access_check on entity URLs.
   */
  public function testAccessCheck(): void {
    // Create a node to link to.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Linked node',
      'status' => 1,
    ]);
    $node->save();

    // Create a test entity linking to that node.
    $entity = EntityTest::create([
      'field_link' => [
        'uri' => 'entity:node/' . $node->id(),
        'title' => 'View node',
      ],
    ]);
    $entity->save();

    // Helper for rendering.
    $render = function (bool $access_check) use ($entity): string {
      $build = $entity->get('field_link')->view([
        'label' => 'hidden',
        'weight' => 0,
        'settings' => [
          'trim_length' => '80',
          'url_only' => '',
          'url_plain' => '',
          'rel' => '',
          'target' => '',
          'access_check' => $access_check,
        ],
      ]);
      return trim((string) $this->renderer->renderRoot($build));
    };

    // Published node, access_check=FALSE/TRUE → link should render.
    $this->assertSame('<div><a href="/node/1">View node</a></div>', $render(FALSE));
    $this->assertSame('<div><a href="/node/1">View node</a></div>', $render(TRUE));

    // Unpublished node, access_check=TRUE → link should not render.
    // We have to reset the static cache of node access control handler.
    $node->setUnpublished()->save();
    $this->entityTypeManager->getAccessControlHandler('node')->resetCache();
    $this->assertSame('', $render(TRUE));

    // Unpublished node, access_check=FALSE → link should render.
    $this->assertSame('<div><a href="/node/1">View node</a></div>', $render(FALSE));
  }

}
