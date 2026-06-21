<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder_test\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\treegrid_reorder\MoveIntent;
use Drupal\treegrid_reorder\TreeReorderManager;
use Drupal\treegrid_reorder_test\TestTreeMoveHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves a minimal treegrid that uses the reorder gestures.
 *
 * A single flat list of items, ordered from State, rendered into a
 * <drupal-treegrid> with a drag handle per row. page() server-renders the top
 * level; children() feeds the spine's refresh after a move (the root level is
 * the list, every other parent is a leaf); move() runs the request through
 * TreeReorderManager + TestTreeMoveHandler and persists the new order.
 */
final class TreegridReorderTestController extends ControllerBase {

  /**
   * Renders the test page: a <drupal-treegrid> of flat, movable rows.
   */
  public function page(): array {
    $set_size = count($this->order());
    $rows = '';
    foreach (array_values($this->order()) as $i => $id) {
      $pos = $i + 1;
      $tabindex = $pos === 1 ? '0' : '-1';
      $rows .= '<tr role="row" data-id="' . Html::escape($id)
        . '" data-parent-id="" data-has-children="false" data-movable="true"'
        . ' aria-level="1" aria-posinset="' . $pos . '" aria-setsize="' . $set_size
        . '" tabindex="' . $tabindex . '">'
        . '<td role="gridcell">'
        . '<span class="reorder-test-handle" role="button" tabindex="-1" title="Drag to reorder"></span>'
        . '<span class="reorder-test-title">' . Html::escape('Item ' . $id) . '</span>'
        . '</td></tr>';
    }

    $children_url = Url::fromRoute('treegrid_reorder_test.children')->toString();
    $html = '<drupal-treegrid data-provider="treegrid_reorder_test" data-children-url="'
      . Html::escape($children_url) . '"><table role="treegrid"><tbody>'
      . $rows . '</tbody></table></drupal-treegrid>';

    return [
      '#markup' => Markup::create($html),
      '#attached' => [
        'library' => ['treegrid_reorder_test/treegrid_reorder_test'],
        'drupalSettings' => [
          'treegridReorderTest' => [
            'moveUrl' => Url::fromRoute('treegrid_reorder_test.move')->toString(),
          ],
        ],
      ],
      // The order is read from State, which page cache cannot express.
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Returns one level as JSON: the list at the root, nothing below a leaf.
   */
  public function children(Request $request): JsonResponse {
    $parent = (string) $request->query->get('parent', '');
    if ($parent !== '') {
      return new JsonResponse(['parent' => $parent, 'items' => []]);
    }

    $items = [];
    foreach (array_values($this->order()) as $id) {
      $items[] = [
        'id' => $id,
        'parent_id' => '',
        'title' => 'Item ' . $id,
        'has_children' => FALSE,
        'movable' => TRUE,
        'depth' => 1,
      ];
    }
    return new JsonResponse(['parent' => NULL, 'items' => $items]);
  }

  /**
   * Applies a move through the generic manager and persists the new order.
   */
  public function move(Request $request): JsonResponse {
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'bad_request'], 400);
    }

    $before_id = $payload['before_id'] ?? NULL;
    $intent = new MoveIntent(
      (string) ($payload['id'] ?? ''),
      (string) ($payload['new_parent'] ?? ''),
      $before_id === NULL ? NULL : (string) $before_id,
    );

    $handler = new TestTreeMoveHandler($this->state());
    $result = (new TreeReorderManager())->validateMove('test', $intent, $handler);
    if (empty($result['ok'])) {
      return new JsonResponse($result, 400);
    }

    $handler->applyMove('test', $intent);
    return new JsonResponse(['ok' => TRUE]);
  }

  /**
   * The current ordered list of item IDs.
   *
   * @return string[]
   *   The item IDs, in order.
   */
  private function order(): array {
    return $this->state()->get(TestTreeMoveHandler::STATE_KEY, TestTreeMoveHandler::DEFAULT_ORDER);
  }

}
