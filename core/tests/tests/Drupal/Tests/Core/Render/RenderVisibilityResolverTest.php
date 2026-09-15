<?php

namespace Drupal\Tests\Core\Render;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Render\RenderVisibilityResolver;
use Drupal\Core\Utility\CallableResolver;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the RenderVisibilityResolver service.
 *
 * @coversDefaultClass \Drupal\Core\Render\RenderVisibilityResolver
 * @group Render
 */
class RenderVisibilityResolverTest extends UnitTestCase {

  /**
   * The render visibility resolver under test.
   *
   * @var \Drupal\Core\Render\RenderVisibilityResolver
   */
  protected $resolver;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $callable_resolver = $this->createStub(CallableResolver::class);
    $this->resolver = new RenderVisibilityResolver($callable_resolver);
  }

  /**
   * Tests that empty render arrays return forbidden.
   */
  public function testResolveAccessEmptyArray(): void {
    $elements = [];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
    $this->assertFalse($result->isAllowed());
  }

  /**
   * Tests that hidden type elements return forbidden.
   */
  public function testResolveAccessHiddenType(): void {
    $elements = ['#type' => 'hidden', '#value' => 'test'];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that value type elements return forbidden.
   */
  public function testResolveAccessTypeValue(): void {
    $elements = ['#type' => 'value', '#value' => 'test'];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that token type elements return forbidden.
   */
  public function testResolveAccessTypeToken(): void {
    $elements = ['#type' => 'token'];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that elements with #access = TRUE return allowed.
   */
  public function testResolveAccessBooleanTrue(): void {
    $elements = [
      '#type' => 'markup',
      '#markup' => '<p>Hello</p>',
      '#access' => TRUE,
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

  /**
   * Tests that elements with #access = FALSE return forbidden.
   */
  public function testResolveAccessBooleanFalse(): void {
    $elements = [
      '#type' => 'markup',
      '#markup' => '<p>Hello</p>',
      '#access' => FALSE,
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that elements with #access as AccessResult return correctly.
   */
  public function testResolveAccessAccessResult(): void {
    $elements = [
      '#type' => 'markup',
      '#markup' => '<p>Hello</p>',
      '#access' => AccessResult::allowed(),
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

  /**
   * Tests that elements with #access as forbidden AccessResult return forbidden.
   */
  public function testResolveAccessAccessResultForbidden(): void {
    $elements = [
      '#type' => 'markup',
      '#markup' => '<p>Hello</p>',
      '#access' => AccessResult::forbidden(),
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that elements with #lazy_builder are treated as visible.
   */
  public function testResolveAccessLazyBuilder(): void {
    $elements = [
      '#lazy_builder' => ['some_callback', []],
      '#cache' => ['keys' => ['test']],
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

  /**
   * Tests that elements with #markup but no children are visible.
   */
  public function testResolveAccessMarkupOnly(): void {
    $elements = [
      '#markup' => '<p>Hello</p>',
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

  /**
   * Tests that elements with #plain_text are visible.
   */
  public function testResolveAccessPlainText(): void {
    $elements = [
      '#plain_text' => 'Hello world',
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

  /**
   * Tests recursive access evaluation on children.
   */
  public function testResolveAccessRecursiveChildren(): void {
    $elements = [
      'child1' => [
        '#type' => 'markup',
        '#markup' => '<p>Child 1</p>',
        '#access' => TRUE,
      ],
      'child2' => [
        '#type' => 'markup',
        '#markup' => '<p>Child 2</p>',
        '#access' => FALSE,
      ],
    ];
    $result = $this->resolver->resolveAccess($elements);
    // At least one child is visible, so result should be allowed.
    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

  /**
   * Tests that all-forbidden children return forbidden.
   */
  public function testResolveAccessAllChildrenForbidden(): void {
    $elements = [
      'child1' => [
        '#type' => 'markup',
        '#markup' => '<p>Child 1</p>',
        '#access' => FALSE,
      ],
      'child2' => [
        '#type' => 'hidden',
        '#value' => 'test',
      ],
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that empty children return forbidden.
   */
  public function testResolveAccessEmptyChildren(): void {
    $elements = [
      'child1' => [],
      'child2' => [],
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultForbidden::class, $result);
  }

  /**
   * Tests that isVisible returns TRUE for accessible elements.
   */
  public function testIsVisibleTrue(): void {
    $elements = [
      '#type' => 'markup',
      '#markup' => '<p>Hello</p>',
    ];
    $this->assertTrue($this->resolver->isVisible($elements));
  }

  /**
   * Tests that isVisible returns FALSE for inaccessible elements.
   */
  public function testIsVisibleFalse(): void {
    $elements = [
      '#type' => 'markup',
      '#markup' => '<p>Hello</p>',
      '#access' => FALSE,
    ];
    $this->assertFalse($this->resolver->isVisible($elements));
  }

  /**
   * Tests that deeply nested elements are evaluated recursively.
   */
  public function testDeeplyNestedEvaluation(): void {
    $elements = [
      'wrapper' => [
        'inner' => [
          'deep' => [
            '#type' => 'markup',
            '#markup' => '<p>Deep content</p>',
            '#access' => TRUE,
          ],
        ],
      ],
    ];
    $result = $this->resolver->resolveAccess($elements);
    $this->assertInstanceOf(AccessResultAllowed::class, $result);
  }

}
