<?php

namespace Drupal\Component\Graph;

/**
 * Directed acyclic graph manipulation.
 *
 * This class represents a directed acyclic graph (DAG) and provides methods
 * for processing and sorting it.
 *
 * Example of a graph structure:
 * @code
 *   1────►2────►3
 *         │     │
 *         │     ▼
 *         └───► 4
 * @endcode
 *
 * Example of defining a graph in PHP:
 * @code
 *   $graph[1]['edges'][2] = 1;
 *   $graph[2]['edges'][3] = 1;
 *   $graph[2]['edges'][4] = 1;
 *   $graph[3]['edges'][4] = 1;
 * @endcode
 */
class Graph {

  /**
   * Holds the directed acyclic graph.
   *
   * @var array
   */
  protected $graph;

  /**
   * Whether the graph has been explicitly checked for cycles.
   *
   * @var bool
   */
  protected $graphVerifiedAcyclic = FALSE;

  /**
   * Instantiates the directed acyclic graph object.
   *
   * @param array $graph
   *   A three-dimensional associative array, with the first keys being the
   *   names of the vertices, which can be strings or numbers. The second key is
   *   'edges', whose value is an array keyed by the names of the vertices
   *   connected to it; the values in this array can be simply TRUE or may
   *   contain other data.
   */
  public function __construct($graph) {
    $this->graph = $graph;
  }

  /**
   * Verifies that the graph is acyclic.
   *
   * @return $this
   *
   * @throws \Drupal\Component\Graph\CyclicGraphException
   *   Thrown when the graph contains a cycle.
   */
  public function verifyGraphIsAcyclic() {
    if ($cycle = $this->findCycle()) {
      throw new CyclicGraphException($cycle);
    }
    $this->graphVerifiedAcyclic = TRUE;
    return $this;
  }

  /**
   * Performs a depth-first search and sort on the directed acyclic graph.
   *
   * @return array
   *   The given $graph with more secondary keys filled in:
   *   - 'paths': Contains a list of vertices than can be reached on a path from
   *     this vertex.
   *   - 'reverse_paths': Contains a list of vertices that has a path from them
   *     to this vertex.
   *   - 'weight': If there is a path from a vertex to another then the weight
   *      of the latter is higher.
   *   - 'component': Vertices in the same component have the same component
   *     identifier.
   */
  public function searchAndSort() {
    if (!$this->graphVerifiedAcyclic && $this->findCycle()) {
      @trigger_error('Calling ' . __METHOD__ . '() on a cyclic graph without first calling ' . self::class . '::verifyGraphIsAcyclic() is deprecated in drupal:11.4.0 and will throw ' . CyclicGraphException::class . ' in drupal:13.0.0. Call ' . self::class . '::verifyGraphIsAcyclic() before sorting and handle cyclic graph input. See https://www.drupal.org/node/3595546', E_USER_DEPRECATED);
    }

    $state = [
      // The order of last visit of the depth first search. This is the reverse
      // of the topological order if the graph is acyclic.
      'last_visit_order' => [],
      // The components of the graph.
      'components' => [],
    ];
    // Perform the actual search.
    foreach ($this->graph as $start => $data) {
      $this->depthFirstSearch($state, $start);
    }

    // We do such a numbering that every component starts with 0. This is useful
    // for module installs as we can install every 0 weighted module in one
    // request, and then every 1 weighted etc.
    $component_weights = [];

    foreach ($state['last_visit_order'] as $vertex) {
      $component = $this->graph[$vertex]['component'];
      if (!isset($component_weights[$component])) {
        $component_weights[$component] = 0;
      }
      $this->graph[$vertex]['weight'] = $component_weights[$component]--;
    }

    return $this->graph;
  }

  /**
   * Finds a cycle in the graph.
   *
   * @return array
   *   The first detected cycle path, or an empty array if the graph is acyclic.
   */
  protected function findCycle() {
    $state = [];
    $stack = [];
    foreach (array_keys($this->graph) as $vertex) {
      if (!isset($state[$vertex])) {
        if ($cycle = $this->findCycleFromVertex($vertex, $state, $stack)) {
          return $cycle;
        }
      }
    }
    return [];
  }

  /**
   * Searches for a cycle from a vertex.
   *
   * @param string|int $vertex
   *   The vertex to inspect.
   * @param array $state
   *   The traversal state, keyed by vertex.
   * @param array $stack
   *   The current traversal stack.
   *
   * @return array
   *   The first detected cycle path, or an empty array if none is found.
   */
  protected function findCycleFromVertex($vertex, array &$state, array &$stack) {
    $state[$vertex] = 'visiting';
    $stack[] = $vertex;

    foreach ($this->graph[$vertex]['edges'] ?? [] as $end => $v) {
      // Edges to unknown vertices are ignored by ::depthFirstSearch(), so they
      // cannot form a cycle in the sortable graph.
      if (!array_key_exists($end, $this->graph)) {
        continue;
      }
      if (($state[$end] ?? NULL) === 'visiting') {
        $cycle_start = array_search($end, $stack, TRUE);
        $cycle = array_slice($stack, $cycle_start);
        $cycle[] = $end;
        return $cycle;
      }
      if (!isset($state[$end])) {
        if ($cycle = $this->findCycleFromVertex($end, $state, $stack)) {
          return $cycle;
        }
      }
    }

    array_pop($stack);
    $state[$vertex] = 'visited';
    return [];
  }

  /**
   * Performs a depth-first search on a graph.
   *
   * @param array $state
   *   An associative array. The key 'last_visit_order' stores a list of the
   *   vertices visited. The key components stores list of vertices belonging
   *   to the same the component.
   * @param string|int $start
   *   An arbitrary vertex where we started traversing the graph.
   * @param string|int|null $component
   *   The component of the last vertex.
   *
   * @see \Drupal\Component\Graph\Graph::searchAndSort()
   */
  protected function depthFirstSearch(&$state, $start, &$component = NULL) {
    // Assign new component for each new vertex, i.e. when not called
    // recursively.
    if (!isset($component)) {
      $component = $start;
    }
    // Nothing to do, if we already visited this vertex.
    if (isset($this->graph[$start]['paths'])) {
      return;
    }
    // Mark $start as visited.
    $this->graph[$start]['paths'] = [];

    // Assign $start to the current component.
    $this->graph[$start]['component'] = $component;
    $state['components'][$component][] = $start;

    // Visit edges of $start.
    if (isset($this->graph[$start]['edges'])) {
      foreach ($this->graph[$start]['edges'] as $end => $v) {
        // Mark that $start can reach $end.
        $this->graph[$start]['paths'][$end] = $v;

        if (isset($this->graph[$end]['component']) && $component != $this->graph[$end]['component']) {
          // This vertex already has a component, use that from now on and
          // reassign all the previously explored vertices.
          $new_component = $this->graph[$end]['component'];
          foreach ($state['components'][$component] as $vertex) {
            $this->graph[$vertex]['component'] = $new_component;
            $state['components'][$new_component][] = $vertex;
          }
          unset($state['components'][$component]);
          $component = $new_component;
        }
        // Only visit existing vertices.
        if (isset($this->graph[$end])) {
          // Visit the connected vertex.
          $this->depthFirstSearch($state, $end, $component);

          // All vertices reachable by $end are also reachable by $start.
          $this->graph[$start]['paths'] += $this->graph[$end]['paths'];
        }
      }
    }

    // Now that any other subgraph has been explored, add $start to all reverse
    // paths.
    foreach ($this->graph[$start]['paths'] as $end => $v) {
      if (isset($this->graph[$end])) {
        $this->graph[$end]['reverse_paths'][$start] = $v;
      }
    }

    // Record the order of the last visit. This is the reverse of the
    // topological order if the graph is acyclic.
    $state['last_visit_order'][] = $start;
  }

}
