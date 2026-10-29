<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Graph;

use Drupal\Component\Graph\CyclicGraphException;
use Drupal\Component\Graph\Graph;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

/**
 * Tests Drupal\Component\Graph\Graph.
 */
#[CoversClass(Graph::class)]
#[CoversClass(CyclicGraphException::class)]
#[Group('Graph')]
class GraphTest extends TestCase {

  /**
   * Tests depth-first-search features.
   */
  public function testDepthFirstSearch(): void {
    // The sample graph used is:
    // @code
    // 1 --> 2 --> 3     5 ---> 6
    //       |     ^     ^
    //       |     |     |
    //       |     |     |
    //       +---> 4 <-- 7      8 ---> 9
    // @endcode
    $graph = $this->normalizeGraph([
      1 => [2],
      2 => [3, 4],
      3 => [],
      4 => [3],
      5 => [6],
      7 => [4, 5],
      8 => [9],
      9 => [],
    ]);
    $graph_object = new Graph($graph);
    $graph = $graph_object->searchAndSort();

    $expected_paths = [
      1 => [2, 3, 4],
      2 => [3, 4],
      3 => [],
      4 => [3],
      5 => [6],
      7 => [4, 3, 5, 6],
      8 => [9],
      9 => [],
    ];
    $this->assertPaths($graph, $expected_paths);

    $expected_reverse_paths = [
      1 => [],
      2 => [1],
      3 => [2, 1, 4, 7],
      4 => [2, 1, 7],
      5 => [7],
      7 => [],
      8 => [],
      9 => [8],
    ];
    $this->assertReversePaths($graph, $expected_reverse_paths);

    // Assert that DFS didn't created "missing" vertexes automatically.
    $this->assertFalse(isset($graph[6]), 'Vertex 6 has not been created');

    $expected_components = [
      [1, 2, 3, 4, 5, 7],
      [8, 9],
    ];
    $this->assertComponents($graph, $expected_components);

    $expected_weights = [
      [1, 2, 3],
      [2, 4, 3],
      [7, 4, 3],
      [7, 5],
      [8, 9],
    ];
    $this->assertWeights($graph, $expected_weights);
  }

  /**
   * Tests acyclic graph verification.
   */
  public function testVerifyGraphIsAcyclic(): void {
    $graph = $this->normalizeGraph([
      1 => [2],
      2 => [3],
      3 => [],
    ]);
    $graph_object = new Graph($graph);

    $this->assertSame($graph_object, $graph_object->verifyGraphIsAcyclic());
  }

  /**
   * Tests cyclic graph verification.
   */
  public function testVerifyGraphIsAcyclicThrowsException(): void {
    $graph = $this->normalizeGraph([
      1 => [2],
      2 => [3],
      3 => [1],
    ]);
    $graph_object = new Graph($graph);

    try {
      $graph_object->verifyGraphIsAcyclic();
      $this->fail('Expected a cyclic graph exception.');
    }
    catch (CyclicGraphException $e) {
      $this->assertSame([1, 2, 3, 1], $e->getCycle());
      $this->assertSame('The graph contains a cycle: 1 -> 2 -> 3 -> 1.', $e->getMessage());
    }
  }

  /**
   * Tests that sorting a cyclic graph is deprecated.
   */
  #[IgnoreDeprecations]
  public function testSearchAndSortWithoutAcyclicVerificationIsDeprecated(): void {
    $graph = $this->normalizeGraph([
      1 => [2],
      2 => [1],
    ]);
    $graph_object = new Graph($graph);

    $this->expectUserDeprecationMessage('Calling Drupal\Component\Graph\Graph::searchAndSort() on a cyclic graph without first calling Drupal\Component\Graph\Graph::verifyGraphIsAcyclic() is deprecated in drupal:11.4.0 and will throw Drupal\Component\Graph\CyclicGraphException in drupal:13.0.0. Call Drupal\Component\Graph\Graph::verifyGraphIsAcyclic() before sorting and handle cyclic graph input. See https://www.drupal.org/node/3595546');
    $graph_object->searchAndSort();
  }

  /**
   * Normalizes a graph.
   *
   * @param array $graph
   *   A graph array processed by \Drupal\Component\Graph\Graph::searchAndSort()
   *
   * @return array
   *   The normalized version of a graph.
   */
  protected function normalizeGraph($graph): array {
    $normalized_graph = [];
    foreach ($graph as $vertex => $edges) {
      // Create vertex even if it hasn't any edges.
      $normalized_graph[$vertex] = [];
      foreach ($edges as $edge) {
        $normalized_graph[$vertex]['edges'][$edge] = TRUE;
      }
    }
    return $normalized_graph;
  }

  /**
   * Verify expected paths in a graph.
   *
   * @param array $graph
   *   A graph array processed by \Drupal\Component\Graph\Graph::searchAndSort()
   * @param array $expected_paths
   *   An associative array containing vertices with their expected paths.
   *
   * @internal
   */
  protected function assertPaths(array $graph, array $expected_paths): void {
    foreach ($expected_paths as $vertex => $paths) {
      // Build an array with keys = $paths and values = TRUE.
      $expected = array_fill_keys($paths, TRUE);
      $result = $graph[$vertex]['paths'] ?? [];
      $this->assertEquals($expected, $result, sprintf('Expected paths for vertex %s: %s, got %s', $vertex, $this->displayArray($expected, TRUE), $this->displayArray($result, TRUE)));
    }
  }

  /**
   * Verify expected reverse paths in a graph.
   *
   * @param array $graph
   *   A graph array processed by \Drupal\Component\Graph\Graph::searchAndSort()
   * @param array $expected_reverse_paths
   *   An associative array containing vertices with their expected reverse
   *   paths.
   *
   * @internal
   */
  protected function assertReversePaths(array $graph, array $expected_reverse_paths): void {
    foreach ($expected_reverse_paths as $vertex => $paths) {
      // Build an array with keys = $paths and values = TRUE.
      $expected = array_fill_keys($paths, TRUE);
      $result = $graph[$vertex]['reverse_paths'] ?? [];
      $this->assertEquals($expected, $result, sprintf('Expected reverse paths for vertex %s: %s, got %s', $vertex, $this->displayArray($expected, TRUE), $this->displayArray($result, TRUE)));
    }
  }

  /**
   * Verify expected components in a graph.
   *
   * @param array $graph
   *   A graph array processed by
   *   \Drupal\Component\Graph\Graph::searchAndSort().
   * @param array $expected_components
   *   An array containing of components defined as a list of their vertices.
   *
   * @internal
   */
  protected function assertComponents(array $graph, array $expected_components): void {
    $unassigned_vertices = array_fill_keys(array_keys($graph), TRUE);
    foreach ($expected_components as $component) {
      $result_components = [];
      foreach ($component as $vertex) {
        $result_components[] = $graph[$vertex]['component'];
        unset($unassigned_vertices[$vertex]);
      }
      $this->assertCount(1, array_unique($result_components), sprintf('Expected one unique component for vertices %s, got %s', $this->displayArray($component), $this->displayArray($result_components)));
    }
    $this->assertEquals([], $unassigned_vertices, sprintf('Vertices not assigned to a component: %s', $this->displayArray($unassigned_vertices, TRUE)));
  }

  /**
   * Verify expected order in a graph.
   *
   * @param array $graph
   *   A graph array processed by \Drupal\Component\Graph\Graph::searchAndSort()
   * @param array $expected_orders
   *   An array containing lists of vertices in their expected order.
   *
   * @internal
   */
  protected function assertWeights(array $graph, array $expected_orders): void {
    foreach ($expected_orders as $order) {
      $previous_vertex = array_shift($order);
      foreach ($order as $vertex) {
        $this->assertLessThan($graph[$vertex]['weight'], $graph[$previous_vertex]['weight'], sprintf("Weight of vertex %s should be less than vertex %s.", $previous_vertex, $vertex));
      }
    }
  }

  /**
   * Helper function to output vertices as comma-separated list.
   *
   * @param array $paths
   *   An array containing a list of vertices.
   * @param bool $keys
   *   (optional) Whether to output the keys of $paths instead of the values.
   */
  protected function displayArray($paths, $keys = FALSE): string {
    if (!empty($paths)) {
      return implode(', ', $keys ? array_keys($paths) : $paths);
    }
    else {
      return '(empty)';
    }
  }

}
