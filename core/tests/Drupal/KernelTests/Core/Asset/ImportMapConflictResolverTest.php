<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Asset;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\AbstractLogger;

/**
 * Tests import map conflicts between multiple libraries.
 *
 * The importmap_test_conflicts module defines the 'importmap-test-multi'
 * module specifier in three libraries with different values, and the
 * 'importmap-test-pair' specifier in two of them. The libraries are merged in
 * the order in which they are defined in the module's libraries.yml file, so
 * the 'conflict-a' library wins both conflicts.
 */
#[Group('Asset')]
#[RunTestsInSeparateProcesses]
class ImportMapConflictResolverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['importmap_test_conflicts'];

  /**
   * The asset resolver service.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * A spy logger, collecting all logged messages.
   *
   * @var \Psr\Log\AbstractLogger&\Drupal\KernelTests\Core\Asset\ImportMapConflictTestLogger
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->assetResolver = $this->container->get('asset.resolver');
    $this->logger = new ImportMapConflictTestLogger();
    $this->container->get(LoggerChannelFactoryInterface::class)->addLogger($this->logger);
    // Make sure that the import map is not served from a cache item created
    // by a previous test, since the logged conflicts only happen on a cache
    // miss.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['library_info']);
  }

  /**
   * Returns the path of the importmap_test_conflicts module.
   *
   * @return string
   *   The module path.
   */
  protected function modulePath(): string {
    return $this->container->get('extension.list.module')->getPath('importmap_test_conflicts');
  }

  /**
   * Returns the import map entry for a module specifier as an array.
   *
   * @param string $file
   *   The file of the entry, relative to the module path.
   *
   * @return array
   *   The import map entry.
   */
  protected function entry(string $file): array {
    return [
      'data' => $this->modulePath() . "/js/$file",
      'external' => FALSE,
      'version' => '1.0',
    ];
  }

  /**
   * Returns the conflict messages from the spy logger.
   *
   * @return array
   *   The logged conflict messages, as [level, message] arrays.
   */
  protected function conflictMessages(): array {
    return array_filter($this->logger->messages, static function (array $message) {
      return str_contains($message[1], 'Import map conflict');
    });
  }

  /**
   * Tests that a specifier defined by multiple libraries is a single conflict.
   *
   * The first definition in the merge order is used for the import map, and
   * all the definitions are recorded in the conflict passed to
   * hook_importmap_alter(). A conflict message is logged for each definition
   * that was not used.
   */
  public function testMultipleConflictingDefinitions(): void {
    $import_map = $this->assetResolver->getImportMap();
    // The 'conflict-a' library wins both conflicts.
    $this->assertSame($this->entry('multi-a.js'), $import_map['imports']['importmap-test-multi']);
    $this->assertSame($this->entry('pair-a.js'), $import_map['imports']['importmap-test-pair']);
    // The conflict passed to hook_importmap_alter() lists all the definitions
    // of the conflicting specifiers, keyed by the library that provided the
    // definition.
    $conflicts = $this->container->get('state')->get('importmap_test_conflicts.last_conflicts');
    $this->assertSame([
      'imports' => [
        'importmap-test-multi' => [
          'importmap_test_conflicts/conflict-a' => $this->entry('multi-a.js'),
          'importmap_test_conflicts/conflict-b' => $this->entry('multi-b.js'),
          'importmap_test_conflicts/conflict-c' => $this->entry('multi-c.js'),
        ],
        'importmap-test-pair' => [
          'importmap_test_conflicts/conflict-a' => $this->entry('pair-a.js'),
          'importmap_test_conflicts/conflict-b' => $this->entry('pair-b.js'),
        ],
      ],
      'scopes' => [],
      'integrity' => [],
    ], $conflicts);
    // A message is logged for each definition that was not used: two for the
    // three-way conflict, one for the two-way conflict.
    $messages = array_map(static function (array $message) {
      return $message[1];
    }, array_values($this->conflictMessages()));
    $this->assertCount(3, $messages);
    $this->assertCount(1, array_filter($messages, static function (string $message) {
      return str_contains($message, "the module specifier 'importmap-test-multi'")
        && str_contains($message, 'importmap_test_conflicts/conflict-a')
        && str_contains($message, 'importmap_test_conflicts/conflict-b');
    }), 'The conflict with the conflict-b library is logged.');
    $this->assertCount(1, array_filter($messages, static function (string $message) {
      return str_contains($message, "the module specifier 'importmap-test-multi'")
        && str_contains($message, 'importmap_test_conflicts/conflict-a')
        && str_contains($message, 'importmap_test_conflicts/conflict-c');
    }), 'The conflict with the conflict-c library is logged.');
    $this->assertCount(1, array_filter($messages, static function (string $message) {
      return str_contains($message, "the module specifier 'importmap-test-pair'")
        && str_contains($message, 'importmap_test_conflicts/conflict-a')
        && str_contains($message, 'importmap_test_conflicts/conflict-b');
    }), 'The conflict of importmap-test-pair is logged.');
    foreach (array_values($this->conflictMessages()) as $message) {
      $this->assertTrue(in_array($message[0], ['error', 3], TRUE), 'The conflict is logged as an error.');
      $this->assertStringContainsString('hook_importmap_alter', $message[1]);
    }
  }

  /**
   * Tests that conflicts are resolved per module specifier.
   *
   * The importmap_test_conflicts module resolves the
   * 'importmap-test-multi' conflict in hook_importmap_alter(), by using the
   * first definition that the merge did not pick, and unsetting the
   * conflict. The 'importmap-test-pair' conflict is left unresolved, and is
   * still logged.
   */
  public function testConflictsResolvedPerSpecifier(): void {
    $this->container->get('state')->set('importmap_test_conflicts.resolve', TRUE);
    $import_map = $this->assetResolver->getImportMap();
    // The conflict is resolved by using the definition of the 'conflict-b'
    // library, the first definition that the merge did not pick.
    $this->assertSame($this->entry('multi-b.js'), $import_map['imports']['importmap-test-multi']);
    // The unresolved conflict keeps the entry of the 'conflict-a' library.
    $this->assertSame($this->entry('pair-a.js'), $import_map['imports']['importmap-test-pair']);
    $messages = array_map(static function (array $message) {
      return $message[1];
    }, array_values($this->conflictMessages()));
    // Only the unresolved 'importmap-test-pair' conflict is logged.
    $this->assertCount(1, $messages);
    $this->assertStringContainsString("the module specifier 'importmap-test-pair'", $messages[0]);
    $this->assertStringNotContainsString('importmap-test-multi', $messages[0]);
  }

}

/**
 * A logger that collects the messages it receives.
 */
class ImportMapConflictTestLogger extends AbstractLogger {

  /**
   * The collected messages, as [level, message] arrays.
   *
   * @var array
   */
  public $messages = [];

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->messages[] = [$level, $message];
  }

}
