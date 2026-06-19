<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui_test\Drush\Commands;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Seeds and purges large test menus for menu_tree_ui scale testing.
 *
 * Discovered by Drush 12+ via its standard <module>/src/Drush/Commands/
 * scanning path. No drush.services.yml needed.
 */
final class MenuTreeSeederCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Reset entity storage statics every N saves to keep peak memory bounded.
   */
  private const int BATCH_SIZE = 500;

  /**
   * Hard cap matching core's menu_tree.p1..p9 materialized-path schema.
   */
  private const int MAX_DEPTH = 9;

  /**
   * Word pool for generated titles.
   *
   * Real lorem so search has variety to filter against and humans can scan
   * results.
   *
   * cspell:ignore lorem ipsum dolor amet consectetur adipiscing elit eiusmod
   * cspell:ignore tempor incididunt labore dolore aliqua enim veniam quis
   * cspell:ignore nostrud exercitation ullamco laboris aliquip commodo
   * cspell:ignore consequat duis aute irure reprehenderit voluptate velit
   * cspell:ignore cillum fugiat nulla pariatur excepteur occaecat cupidatat
   * cspell:ignore proident culpa officia deserunt mollit anim laborum
   */
  private const array LOREM = [
    'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing',
    'elit', 'sed', 'eiusmod', 'tempor', 'incididunt', 'labore', 'dolore',
    'magna', 'aliqua', 'enim', 'minim', 'veniam', 'quis', 'nostrud',
    'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'commodo',
    'consequat', 'duis', 'aute', 'irure', 'reprehenderit', 'voluptate',
    'velit', 'esse', 'cillum', 'fugiat', 'nulla', 'pariatur', 'excepteur',
    'sint', 'occaecat', 'cupidatat', 'proident', 'culpa', 'officia',
    'deserunt', 'mollit', 'anim', 'laborum',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Seeds a menu with N levels of test links.
   *
   * Use --counts to control shape. Depth equals the number of comma-separated
   * values (capped at 9, matching core's menu_tree.p1..p9 schema).
   */
  #[CLI\Command(name: 'menu-tree-ui:seed', aliases: ['mtu:seed'])]
  #[CLI\Argument(name: 'menu', description: 'Menu machine name (e.g. main, admin).')]
  #[CLI\Option(name: 'counts', description: 'Comma-separated children-per-level (e.g. "15,8,6,5,3" for a 5-level tree).')]
  #[CLI\Option(name: 'prefix', description: 'Title prefix for seeded links (used by purge to scope deletion).')]
  #[CLI\Option(name: 'seed', description: 'Random seed for reproducible titles (default: time-based).')]
  #[CLI\Option(name: 'dry-run', description: 'Report the plan without writing anything.')]
  #[CLI\Usage(name: 'drush mtu:seed main', description: 'Default 5-level tree, ~15k links with lorem titles.')]
  #[CLI\Usage(name: 'drush mtu:seed main --counts=10,10,10,10,10', description: 'Balanced 5-level tree, 111,110 links.')]
  #[CLI\Usage(name: 'drush mtu:seed main --counts=5000', description: 'Wide single-level stress test.')]
  #[CLI\Usage(name: 'drush mtu:seed main --seed=42', description: 'Reproducible titles for snapshot-style tests.')]
  public function seed(
    string $menu = 'main',
    array $options = [
      'counts' => '15,8,6,5,3',
      'prefix' => 'mtu',
      'seed' => '',
      'dry-run' => FALSE,
    ],
  ): int {
    $prefix = (string) $options['prefix'];
    $dryRun = (bool) $options['dry-run'];
    $seed = (string) $options['seed'] !== '' ? (int) $options['seed'] : NULL;

    $counts = $this->parseCounts((string) $options['counts']);
    if ($counts === NULL) {
      $this->io()->error('--counts must be a comma-separated list of positive integers, max ' . self::MAX_DEP1TH . ' levels.');
      return DrushCommands::EXIT_FAILURE;
    }

    if (!$this->menuExists($menu)) {
      $this->io()->error("Menu '$menu' does not exist.");
      return DrushCommands::EXIT_FAILURE;
    }

    $total = $this->calculateTotal($counts);

    $this->io()->section('Seeder plan');
    $defs = [
      ['Menu' => $menu],
      ['Depth' => (string) count($counts)],
      ['Per level' => implode(' × ', array_map('strval', $counts))],
      ['Total links' => number_format($total)],
      ['Title prefix' => $prefix],
      ['Seed' => $seed === NULL ? '(random)' : (string) $seed],
    ];
    $this->io()->definitionList(...$defs);

    if ($dryRun) {
      $this->io()->note('Dry run — nothing written.');
      return DrushCommands::EXIT_SUCCESS;
    }

    if (!$this->io()->confirm("Create $total links in '$menu'?", TRUE)) {
      return DrushCommands::EXIT_FAILURE;
    }

    if ($seed !== NULL) {
      mt_srand($seed);
    }

    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $start = microtime(TRUE);
    $progress = $this->io()->createProgressBar($total);
    $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%  %message%');
    $progress->setMessage('seeding…');
    $progress->start();

    $created = 0;
    $this->seedLevel($storage, $menu, $counts, NULL, $progress, $created, $prefix);

    $progress->finish();
    $this->io()->newLine(2);

    // Phase 2: assign sequential weights within each sibling group so the tree
    // has a stable, deterministic order. Without this all siblings share weight
    // 0 and the menu tree storage can return them in an unpredictable order.
    $this->io()->section('Assigning weights');
    $weightProgress = $this->io()->createProgressBar($total);
    $weightProgress->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%');
    $weightProgress->start();
    $this->assignWeights($storage, $menu, $prefix, $weightProgress);
    $weightProgress->finish();
    $this->io()->newLine(2);

    $elapsed = microtime(TRUE) - $start;
    $rate = $total / max($elapsed, 0.001);
    $this->io()->success(sprintf(
      'Seeded %s links into "%s" in %.1fs (%.0f/s).',
      number_format($total),
      $menu,
      $elapsed,
      $rate,
    ));
    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Deletes test links from a menu.
   *
   * Defaults to only deleting links whose title starts with the seeder prefix,
   * so you don't accidentally wipe real menu content alongside test data.
   */
  #[CLI\Command(name: 'menu-tree-ui:purge', aliases: ['mtu:purge'])]
  #[CLI\Argument(name: 'menu', description: 'Menu machine name.')]
  #[CLI\Option(name: 'prefix', description: 'Only delete links whose title starts with this prefix. Pass empty string to delete all menu_link_content links in the menu (dangerous).')]
  #[CLI\Option(name: 'dry-run', description: 'Report the count without deleting.')]
  #[CLI\Usage(name: 'drush mtu:purge main', description: 'Delete all "mtu …" links from the main menu.')]
  #[CLI\Usage(name: 'drush mtu:purge main --prefix=""', description: 'Delete ALL menu_link_content links from main.')]
  public function purge(
    string $menu = 'main',
    array $options = [
      'prefix' => 'mtu',
      'dry-run' => FALSE,
    ],
  ): int {
    $prefix = (string) $options['prefix'];
    $dryRun = (bool) $options['dry-run'];

    if (!$this->menuExists($menu)) {
      $this->io()->error("Menu '$menu' does not exist.");
      return DrushCommands::EXIT_FAILURE;
    }

    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', $menu);
    if ($prefix !== '') {
      $query->condition('title', $prefix . '%', 'LIKE');
    }
    $ids = $query->execute();
    $count = count($ids);

    if ($count === 0) {
      $this->io()->note("No matching links in '$menu'.");
      return DrushCommands::EXIT_SUCCESS;
    }

    $description = $prefix === ''
      ? "ALL $count menu_link_content links in '$menu'"
      : "$count links matching '$prefix*' in '$menu'";

    if ($dryRun) {
      $this->io()->note("Would delete $description.");
      return DrushCommands::EXIT_SUCCESS;
    }

    if (!$this->io()->confirm("Delete $description?", FALSE)) {
      return DrushCommands::EXIT_FAILURE;
    }

    $start = microtime(TRUE);
    $progress = $this->io()->createProgressBar($count);
    $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s%');
    $progress->start();

    foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
      $entities = $storage->loadMultiple($chunk);
      $storage->delete($entities);
      $progress->advance(count($chunk));
      $storage->resetCache();
    }
    $progress->finish();
    $this->io()->newLine(2);

    $elapsed = microtime(TRUE) - $start;
    $this->io()->success(sprintf(
      'Deleted %s links in %.1fs.',
      number_format($count),
      $elapsed,
    ));
    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Recursive descender for one level of the seeded tree.
   *
   * Creates $counts[0] siblings under $parent, then recurses with the rest
   * of $counts for each. Depth is implicit in the array length.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Menu link content storage.
   * @param string $menu
   *   Menu machine name.
   * @param int[] $counts
   *   Children-per-level for the remaining depth. Mutated in-recursion (we
   *   array_shift the head off each call).
   * @param string|null $parent
   *   Plugin ID of the parent link, or NULL for the top level.
   * @param \Symfony\Component\Console\Helper\ProgressBar $progress
   *   Progress bar advanced once per created link.
   * @param int $created
   *   Pass-by-reference running total; mutated in place rather than returned
   *   so the recursion stays straightforward.
   * @param string $prefix
   *   Title prefix applied to every generated link.
   */
  private function seedLevel(
    EntityStorageInterface $storage,
    string $menu,
    array $counts,
    ?string $parent,
    ProgressBar $progress,
    int &$created,
    string $prefix,
  ): void {
    if ($counts === []) {
      return;
    }
    $n = (int) array_shift($counts);
    for ($i = 1; $i <= $n; $i++) {
      $entity = $this->createLink($storage, $menu, $this->loremTitle($prefix), $parent);
      $progress->advance();
      $created++;
      if ($created % self::BATCH_SIZE === 0) {
        $storage->resetCache();
        $progress->setMessage('mem ' . ByteSizeMarkup::create(memory_get_usage(TRUE)));
      }
      if ($counts !== []) {
        $this->seedLevel($storage, $menu, $counts, $entity->getPluginId(), $progress, $created, $prefix);
      }
    }
  }

  /**
   * Returns "prefix Word [word [word]]" — 1–3 lorem words, first capitalized.
   *
   * The prefix is kept on every title so the purge command can scope deletion
   * by LIKE 'prefix%' without touching real menu content.
   */
  private function loremTitle(string $prefix): string {
    $count = mt_rand(1, 3);
    $keys = (array) array_rand(self::LOREM, $count);
    $words = array_map(static fn(int $k): string => self::LOREM[$k], $keys);
    $words[0] = ucfirst($words[0]);
    return $prefix . ' ' . implode(' ', $words);
  }

  /**
   * Parses a --counts string into an array of positive ints.
   *
   * Returns NULL for invalid input (non-numeric, negative, zero, too many
   * levels) so the caller can surface a clean error message.
   *
   * @param string $raw
   *   The raw --counts value, e.g. "15,8,6,5,3".
   *
   * @return int[]|null
   *   Parsed counts, or NULL on invalid input.
   */
  private function parseCounts(string $raw): ?array {
    $parts = array_map('trim', explode(',', $raw));
    if (count($parts) > self::MAX_DEPTH) {
      return NULL;
    }
    $counts = [];
    foreach ($parts as $part) {
      if (!ctype_digit($part)) {
        return NULL;
      }
      $n = (int) $part;
      if ($n < 1) {
        return NULL;
      }
      $counts[] = $n;
    }
    return $counts === [] ? NULL : $counts;
  }

  /**
   * Assigns sequential weights to every seeded sibling group.
   *
   * Queries all seeded IDs sorted by parent string then entity ID (i.e.
   * creation order within each parent), then walks them in order. Each time
   * the parent changes the weight counter resets to 0, so siblings end up
   * with weights 0, 1, 2, … matching the order they were created.
   *
   * Processes in BATCH_SIZE chunks (load → set weight → save → reset cache)
   * so peak memory stays bounded even for very large menus.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   Menu link content storage.
   * @param string $menu
   *   Menu machine name.
   * @param string $prefix
   *   Title prefix that scopes which links to process.
   * @param \Symfony\Component\Console\Helper\ProgressBar $progress
   *   Progress bar advanced once per saved link.
   */
  private function assignWeights(
    EntityStorageInterface $storage,
    string $menu,
    string $prefix,
    ProgressBar $progress,
  ): void {
    // Fetch IDs sorted by parent (groups siblings together) then by entity ID
    // (preserves creation order within each group).
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', $menu)
      ->condition('title', $prefix . '%', 'LIKE')
      ->sort('parent')
      ->sort('id')
      ->execute();

    if (!$ids) {
      return;
    }

    $currentParent = NULL;
    $weight = 0;

    foreach (array_chunk(array_values($ids), self::BATCH_SIZE) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $entity) {
        $parent = $entity->getParentId();
        if ($parent !== $currentParent) {
          $currentParent = $parent;
          $weight = 0;
        }
        $entity->set('weight', $weight);
        $entity->save();
        $weight++;
        $progress->advance();
      }
      $storage->resetCache();
    }
  }

  /**
   * Sums links across every level: a + a*b + a*b*c for counts [a,b,c].
   */
  private function calculateTotal(array $counts): int {
    $total = 0;
    $running = 1;
    foreach ($counts as $n) {
      $running *= (int) $n;
      $total += $running;
    }
    return $total;
  }

  /**
   * Checks whether a menu entity with the given machine name exists.
   */
  private function menuExists(string $menu_name): bool {
    return (bool) $this->entityTypeManager->getStorage('menu')->load($menu_name);
  }

  /**
   * Creates and saves one menu_link_content entity.
   */
  private function createLink(
    EntityStorageInterface $storage,
    string $menu,
    string $title,
    ?string $parent,
  ): MenuLinkContent {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity */
    $entity = $storage->create([
      'title' => $title,
      'menu_name' => $menu,
      'link' => ['uri' => 'route:<nolink>'],
      'parent' => $parent ?? '',
    ]);
    $entity->save();
    return $entity;
  }

}
