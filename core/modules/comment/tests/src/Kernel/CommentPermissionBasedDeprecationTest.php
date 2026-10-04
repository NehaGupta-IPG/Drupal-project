<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Kernel;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeViewBuilder;
use Drupal\Tests\comment\Traits\CommentCreationTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests comment permission-based deprecation.
 */
#[Group('comment')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class CommentPermissionBasedDeprecationTest extends KernelTestBase {

  use CommentCreationTrait;
  use CommentTestTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'comment_deprecation_test',
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');

    $this->createContentType(['type' => 'page'], FALSE);
    $this->installEntitySchema('node');

    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['comment', 'system']);
    $this->createCommentType('node');

    $this->addDefaultCommentField('node', 'page');
    $this->container->get('state')->set('comment.maintain_entity_statistics', TRUE);

    $this->setUpCurrentUser(permissions: [
      'access content',
      'access comments',
      'post comments',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function testCommentPermissionBasedDeprecation(): void {
    $renderer = $this->container->get('renderer');
    $nodeViewBuilder = $this->container->get('entity_type.manager')->getHandler('node', 'view_builder');
    assert($nodeViewBuilder instanceof NodeViewBuilder);

    $node = $this->createNode([
      'type' => 'page',
      'title' => 'The node title',
    ]);
    $this->createComment([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'subject' => $this->randomString(),
    ]);

    // Reload the node to ensure that the comment field has data.
    $node = Node::load($node->id());

    $build = $nodeViewBuilder->viewField($node->get('comment'), 'full');
    $withEntityAccessControl = $renderer->renderInIsolation($build);

    // Configure the comment field formatter to use the deprecated
    // permission-based comment access control.
    $display = $this->container->get('entity_display.repository')->getViewDisplay('node', 'page', 'default');
    $component = $display->getComponent('comment');
    $component['settings']['use_entity_access_control'] = FALSE;
    $display->setComponent('comment', $component)->save();

    // Flag to theme registry alterer to use the legacy template that hardcodes
    // the 'Add new comment' string into the template.
    // @see \Drupal\comment_deprecation_test\Hook\CommentDeprecationTestHooks::alterThemeRegistry()
    $this->container->get('state')->set('comment_deprecation_test.use_deprecated', TRUE);

    // Force the theme registry to rebuild and use the legacy template.
    $this->container->get('theme.registry')->reset();
    $this->container->get('cache.default')->delete('theme_registry:core');

    // Prevent the HTML IDs from getting a suffix on the second rendering as we
    // want to compare the 2 markups.
    Html::resetSeenIds();

    $build = $nodeViewBuilder->viewField($node->get('comment'), 'full');
    $withoutEntityAccessControl = $renderer->renderInIsolation($build);

    $this->assertSame(
      $this->normalizeMarkup((string) $withEntityAccessControl),
      $this->normalizeMarkup((string) $withoutEntityAccessControl),
    );
  }

  /**
   * Normalizes rendered markup for comparison.
   *
   * @param string $markup
   *   The rendered markup.
   *
   * @return string
   *   The markup with leading and trailing whitespace removed from each line,
   *   without blank lines, and with the random form build ID and form token
   *   replaced by placeholders.
   */
  protected function normalizeMarkup(string $markup): string {
    $lines = explode("\n", $markup)
        |> (fn($x) => array_map('trim', $x))
        |> array_filter(...);
    return preg_replace([
      '/(data-drupal-selector=")form-[a-z0-9-]+(")/',
      '/(name="form_build_id" value=")form-[\w-]+(")/',
      '/(name="form_token" value=")[\w-]+(")/',
    ], '$1PLACEHOLDER$2', implode("\n", $lines));
  }

}
