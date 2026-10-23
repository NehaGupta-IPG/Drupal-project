<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests node template suggestions.
 */
#[Group('node')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class NodeTemplateSuggestionsTest extends KernelTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUpEnvironment(): void {
    parent::setUpEnvironment();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    $this->installConfig(['system']);

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
  }

  /**
   * Tests node template suggestions.
   *
   * @see \Drupal\node\Hook\NodeThemeHooks::themeSuggestionsNode
   */
  public function testNodeThemeHookSuggestions(): void {
    // Create node to be rendered.
    $node = $this->createNode();
    $view_mode = 'full';

    // Simulate theming of the node.
    $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, $view_mode);

    $variables['elements'] = $build;
    $suggestions = \Drupal::moduleHandler()->invokeAll('theme_suggestions_node', [$variables]);

    $this->assertEquals([
      'node__full',
      'node__page',
      'node__page__full',
      'node__' . $node->id(),
      'node__' . $node->id() . '__full',
    ],
    $suggestions,
    'Found expected node suggestions.');

    // Change the view mode.
    $view_mode = 'node.my_custom_view_mode';
    $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, $view_mode);

    $variables['elements'] = $build;
    $suggestions = \Drupal::moduleHandler()->invokeAll('theme_suggestions_node', [$variables]);

    $this->assertEquals([
      'node__node_my_custom_view_mode',
      'node__page',
      'node__page__node_my_custom_view_mode',
      'node__' . $node->id(),
      'node__' . $node->id() . '__node_my_custom_view_mode',
    ],
    $suggestions,
    'Found expected node suggestions.');
  }

}
