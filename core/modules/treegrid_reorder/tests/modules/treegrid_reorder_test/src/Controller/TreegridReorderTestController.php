<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder_test\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\treegrid\TreegridCsrfToken;
use Drupal\treegrid_reorder\MoveIntent;
use Drupal\treegrid_reorder\TreeReorderManager;
use Drupal\treegrid_reorder_test\TestTreeMoveHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves a nested treegrid that uses the reorder gestures.
 *
 * The tree lives in State (see TestTreeMoveHandler). page() server-renders the
 * top level into a <drupal-treegrid>; children() serves any level; move() runs
 * a request through TreeReorderManager + TestTreeMoveHandler and persists it;
 * echoWrite() answers a write with no side effect on the tree, for the request
 * helper's tests.
 *
 * State flags let a test shape the server's behavior. Delays are in
 * milliseconds.
 */
final class TreegridReorderTestController extends ControllerBase {

  /**
   * Milliseconds to wait before the children endpoint answers.
   */
  public const CHILDREN_DELAY = 'treegrid_reorder_test.children_delay';

  /**
   * Makes the children endpoint fail with a 500.
   *
   * An array with 'after' (how many requests, counted by CHILDREN_HITS, are
   * served first) and 'count' (how many then fail, or NULL for every later
   * request).
   */
  public const CHILDREN_FAIL = 'treegrid_reorder_test.children_fail';

  /**
   * Number of requests the children endpoint has received.
   */
  public const CHILDREN_HITS = 'treegrid_reorder_test.children_hits';

  /**
   * Milliseconds to wait before the move endpoint answers.
   */
  public const MOVE_DELAY = 'treegrid_reorder_test.move_delay';

  /**
   * Makes the move endpoint refuse a move.
   *
   * An array with 'code' and, optionally, 'status' (default 400).
   */
  public const MOVE_FAIL = 'treegrid_reorder_test.move_fail';

  /**
   * Number of requests the move endpoint has received.
   */
  public const MOVE_HITS = 'treegrid_reorder_test.move_hits';

  /**
   * Milliseconds to wait before the echo endpoint answers.
   */
  public const ECHO_DELAY = 'treegrid_reorder_test.echo_delay';

  /**
   * Number of requests the echo endpoint has received.
   */
  public const ECHO_HITS = 'treegrid_reorder_test.echo_hits';

  /**
   * The stored revision for the move and echo endpoints, or NULL for none.
   *
   * While set, a request whose X-Treegrid-Reorder-Revision header differs is
   * refused as stale, and a matching one stores a new random revision and
   * returns it in the same header.
   */
  public const REVISION = 'treegrid_reorder_test.revision';

  /**
   * Whether the move and echo endpoints answer 409 busy.
   */
  public const BUSY = 'treegrid_reorder_test.busy';

  /**
   * The revision the page renders into data-treegrid-reorder-revision.
   *
   * NULL renders no attribute; a string, including '', renders it.
   */
  public const PAGE_REVISION = 'treegrid_reorder_test.page_revision';

  /**
   * What the page renders into data-treegrid-csrf-token.
   *
   * NULL renders no attribute, TRUE renders the token placeholder, and a
   * string is rendered as it is.
   */
  public const PAGE_CSRF = 'treegrid_reorder_test.page_csrf';

  /**
   * Whether the page renders data-treegrid-reorder-move-url (default TRUE).
   */
  public const PAGE_MOVE_URL = 'treegrid_reorder_test.page_move_url';

  private const REVISION_HEADER = 'X-Treegrid-Reorder-Revision';

  /**
   * Renders the test page: the top level of the tree.
   */
  public function page(): array {
    $handler = $this->handler();
    $items = $this->levelItems($handler, '');
    $set_size = count($items);
    $rows = '';
    foreach ($items as $i => $item) {
      $rows .= $this->renderRow($item, $i + 1, $set_size);
    }

    $children_url = Url::fromRoute('treegrid_reorder_test.children')->toString();
    $attributes = ' data-provider="treegrid_reorder_test" data-children-url="' . Html::escape($children_url) . '"';
    if ($this->state()->get(self::PAGE_MOVE_URL, TRUE)) {
      $move_url = Url::fromRoute('treegrid_reorder_test.move')->toString();
      $attributes .= ' data-treegrid-reorder-move-url="' . Html::escape($move_url) . '"';
    }
    $revision = $this->state()->get(self::PAGE_REVISION);
    if ($revision !== NULL) {
      $attributes .= ' data-treegrid-reorder-revision="' . Html::escape((string) $revision) . '"';
    }
    $build = [
      '#attached' => [
        'library' => ['treegrid_reorder_test/treegrid_reorder_test'],
      ],
      // The tree is read from State, which page cache cannot express.
      '#cache' => ['max-age' => 0],
    ];
    $csrf = $this->state()->get(self::PAGE_CSRF);
    if ($csrf === TRUE) {
      $attributes .= ' data-treegrid-csrf-token="' . TreegridCsrfToken::addPlaceholder($build) . '"';
    }
    elseif ($csrf !== NULL) {
      $attributes .= ' data-treegrid-csrf-token="' . Html::escape((string) $csrf) . '"';
    }
    $html = '<div class="reorder-test-container">'
      . '<input type="text" class="reorder-test-field" data-test-field="inside">'
      . '<drupal-treegrid' . $attributes . '><table role="treegrid"><tbody>'
      . $rows . '</tbody></table></drupal-treegrid></div>'
      . '<input type="text" class="reorder-test-field" data-test-field="outside">';

    $build['#markup'] = Markup::create($html);
    return $build;
  }

  /**
   * Returns one level as JSON.
   */
  public function children(Request $request): JsonResponse {
    $hits = $this->bump(self::CHILDREN_HITS);
    $this->sleep(self::CHILDREN_DELAY);
    $fail = $this->state()->get(self::CHILDREN_FAIL);
    if (is_array($fail)) {
      $after = (int) ($fail['after'] ?? 0);
      $count = $fail['count'] ?? NULL;
      if ($hits > $after && ($count === NULL || $hits <= $after + (int) $count)) {
        return new JsonResponse(['ok' => FALSE], 500);
      }
    }

    $parent = (string) $request->query->get('parent', '');
    $items = [];
    foreach ($this->levelItems($this->handler(), $parent) as $item) {
      $items[] = [
        'id' => $item['id'],
        'parent_id' => $item['parent'],
        'title' => 'Item ' . $item['id'],
        'has_children' => $item['has_children'],
        'movable' => $item['movable'],
        'depth' => $item['depth'],
      ];
    }
    return new JsonResponse(['parent' => $parent === '' ? NULL : $parent, 'items' => $items]);
  }

  /**
   * Applies a move through the generic manager and persists it.
   */
  public function move(Request $request): JsonResponse {
    $this->bump(self::MOVE_HITS);
    $this->sleep(self::MOVE_DELAY);
    $refusal = $this->checkBusyAndRevision($request);
    if ($refusal !== NULL) {
      return $refusal;
    }
    $revision_headers = $this->revisionHeaders();

    $fail = $this->state()->get(self::MOVE_FAIL);
    if (is_array($fail)) {
      return new JsonResponse(
        ['ok' => FALSE, 'code' => $fail['code'] ?? 'failed', 'message' => 'Move refused.'],
        (int) ($fail['status'] ?? 400),
        $revision_headers,
      );
    }

    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'bad_request'], 400, $revision_headers);
    }

    $before_id = $payload['before_id'] ?? NULL;
    $intent = new MoveIntent(
      (string) ($payload['id'] ?? ''),
      (string) ($payload['new_parent'] ?? ''),
      $before_id === NULL ? NULL : (string) $before_id,
    );

    $handler = $this->handler();
    $result = (new TreeReorderManager())->validateMove('test', $intent, $handler);
    if (empty($result['ok'])) {
      return new JsonResponse($result, 400, $revision_headers);
    }

    $handler->applyMove('test', $intent);
    return new JsonResponse(['ok' => TRUE], 200, $revision_headers);
  }

  /**
   * Answers a write with no effect on the tree.
   */
  public function echoWrite(Request $request): JsonResponse {
    $this->bump(self::ECHO_HITS);
    $this->sleep(self::ECHO_DELAY);
    $refusal = $this->checkBusyAndRevision($request);
    if ($refusal !== NULL) {
      return $refusal;
    }
    return new JsonResponse(
      ['ok' => TRUE, 'received' => ['revision' => $request->headers->get(self::REVISION_HEADER)]],
      200,
      $this->revisionHeaders(),
    );
  }

  /**
   * Refuses a request as busy or stale when the State flags say so.
   *
   * A request that passes the revision check stores a new revision, which
   * revisionHeaders() then returns.
   */
  private function checkBusyAndRevision(Request $request): ?JsonResponse {
    if ($this->state()->get(self::BUSY)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'busy'], 409);
    }
    $stored = $this->state()->get(self::REVISION);
    if ($stored !== NULL && $request->headers->get(self::REVISION_HEADER) !== $stored) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'stale'], 409);
    }
    if ($stored !== NULL) {
      $this->state()->set(self::REVISION, bin2hex(random_bytes(8)));
    }
    return NULL;
  }

  /**
   * The response headers carrying the current revision, if revisions are on.
   *
   * @return array<string, string>
   *   The headers.
   */
  private function revisionHeaders(): array {
    $stored = $this->state()->get(self::REVISION);
    return $stored === NULL ? [] : [self::REVISION_HEADER => $stored];
  }

  /**
   * The move handler over the current State.
   */
  private function handler(): TestTreeMoveHandler {
    return new TestTreeMoveHandler($this->state());
  }

  /**
   * One level's items with the data both renderers need.
   *
   * @return array<int, array{id: string, parent: string, movable: ?bool, has_children: bool, depth: int}>
   *   The items, in order.
   */
  private function levelItems(TestTreeMoveHandler $handler, string $parent_id): array {
    $all = $handler->items();
    $parents = [];
    foreach ($all as $item) {
      $parents[$item['id']] = $item['parent'];
    }
    $with_children = array_flip(array_values(array_filter($parents)));

    $level = [];
    foreach ($all as $item) {
      if ($item['parent'] !== $parent_id) {
        continue;
      }
      $depth = 1;
      for ($up = $item['parent']; $up !== ''; $up = $parents[$up] ?? '') {
        $depth++;
      }
      $level[] = $item + [
        'has_children' => isset($with_children[$item['id']]),
        'depth' => $depth,
      ];
    }
    return $level;
  }

  /**
   * Server-renders one row, matching the DOM the test consumer builds.
   *
   * @param array{id: string, parent: string, movable: ?bool, has_children: bool, depth: int} $item
   *   The item.
   * @param int $pos_in_set
   *   The 1-based position among siblings.
   * @param int $set_size
   *   The number of siblings.
   */
  private function renderRow(array $item, int $pos_in_set, int $set_size): string {
    $id = Html::escape($item['id']);
    $movable = $item['movable'] === NULL ? '' : ' data-movable="' . ($item['movable'] ? 'true' : 'false') . '"';
    $expanded = $item['has_children'] ? ' aria-expanded="false"' : '';
    $tabindex = ($item['parent'] === '' && $pos_in_set === 1) ? '0' : '-1';
    $toggle = $item['has_children']
      ? '<button type="button" class="reorder-test-toggle" data-treegrid-toggle tabindex="-1"></button>'
      : '<span class="reorder-test-spacer" aria-hidden="true"></span>';

    return '<tr role="row" data-id="' . $id . '" data-parent-id="' . Html::escape($item['parent'])
      . '" data-has-children="' . ($item['has_children'] ? 'true' : 'false') . '"' . $movable
      . ' aria-level="' . $item['depth'] . '" aria-posinset="' . $pos_in_set
      . '" aria-setsize="' . $set_size . '"' . $expanded . ' tabindex="' . $tabindex . '">'
      . '<td role="gridcell" data-treegrid-name-cell>'
      . '<span class="reorder-test-handle" data-treegrid-reorder-handle role="button" tabindex="-1" title="Drag to reorder"></span>'
      . $toggle
      . '<span class="reorder-test-title" data-treegrid-reorder-title>' . Html::escape('Item ' . $item['id']) . '</span>'
      . '<input type="checkbox" class="reorder-test-control" data-treegrid-control>'
      . '</td></tr>';
  }

  /**
   * Increments a State counter and returns the new value.
   */
  private function bump(string $key): int {
    $value = (int) $this->state()->get($key, 0) + 1;
    $this->state()->set($key, $value);
    return $value;
  }

  /**
   * Waits for the number of milliseconds a State key holds, if any.
   */
  private function sleep(string $key): void {
    $milliseconds = (int) $this->state()->get($key, 0);
    if ($milliseconds > 0) {
      usleep($milliseconds * 1000);
    }
  }

}
