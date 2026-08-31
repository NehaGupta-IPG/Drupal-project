<?php

declare(strict_types=1);

namespace Drupal\Tests\big_pipe\Functional;

use Drupal\big_pipe\Render\BigPipe;
use Drupal\big_pipe\Render\Placeholder\BigPipeStrategy;
use Drupal\Component\Serialization\Json;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that BigPipe responses add only a single import map.
 *
 * A page only supports a single import map, which is sent with the main HTML
 * document. BigPipe placeholder replacements, sent as embedded AJAX or HTML
 * responses, must not add a second import map. The embedded AJAX responses
 * can add module preloads for the libraries they attach; the embedded HTML
 * responses (no-JS BigPipe placeholders) are sent to browsers with
 * JavaScript disabled, where module scripts cannot execute, so they add no
 * module preloads either.
 */
#[Group('big_pipe')]
#[Group('asset')]
#[RunTestsInSeparateProcesses]
class BigPipeImportMapTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['big_pipe', 'importmap_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Ignore the <meta> refresh that big_pipe.module sets, for no-JS
    // detection.
    $this->maximumMetaRefreshCount = 0;
  }

  /**
   * Returns the module path of the importmap_test module.
   *
   * @return string
   *   The module path.
   */
  protected function modulePath(): string {
    return $this->container->get('extension.list.module')->getPath('importmap_test');
  }

  /**
   * Returns the expected module preloads of the importmap_test/consumer library.
   *
   * @return array
   *   The expected attributes of the module preload link elements.
   */
  protected function expectedModulePreloads(): array {
    $path = $this->modulePath();
    return [
      [
        'rel' => 'modulepreload',
        'href' => $this->container->get('file_url_generator')->generate("$path/js/foo.js")->toString() . '?v=1.0',
      ],
      [
        'rel' => 'modulepreload',
        'href' => 'https://example.com/importmap-test-external.mjs',
        'crossorigin' => 'anonymous',
      ],
      [
        'rel' => 'modulepreload',
        'href' => $this->container->get('file_url_generator')->generate("$path/js/scope/foo.js")->toString() . '?v=1.0',
      ],
    ];
  }

  /**
   * Asserts that the response contains a single import map in the initial response.
   *
   * The import map must be in the initial response, before the first BigPipe
   * chunk (when there is a BigPipe start signal).
   */
  protected function assertSingleImportMapInInitialResponse(): void {
    $content = $this->getSession()->getPage()->getContent();
    $this->assertSame(1, preg_match_all('/<script type="importmap">/', $content), 'The response contains a single import map.');
    $import_map_position = strpos($content, '<script type="importmap">');
    $this->assertNotFalse($import_map_position, 'The response contains an import map.');
    // The no-JS BigPipe placeholders do not send a start signal.
    if (str_contains($content, BigPipe::START_SIGNAL)) {
      $start_signal_position = strpos($content, BigPipe::START_SIGNAL);
      $this->assertLessThan($start_signal_position, $import_map_position, 'The import map is in the initial response, before the first BigPipe chunk.');
    }
  }

  /**
   * Tests that a BigPipe JS placeholder replacement does not add an import map.
   *
   * The placeholder replacement is sent as an embedded AJAX response, and adds
   * module preloads for the libraries it attaches.
   */
  public function testBigPipeJsResponseAddsModulePreloadsAndNotImportMap(): void {
    // BigPipe only works when the request is associated with a session.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/importmap-test/bigpipe-consumer');
    $this->assertSingleImportMapInInitialResponse();
    // The BigPipe placeholder replacement is an embedded AJAX response.
    $embedded_responses = $this->getSession()->getPage()->findAll('css', 'script[type="application/vnd.drupal-ajax"][data-big-pipe-replacement-for-placeholder-with-id]');
    $this->assertCount(1, $embedded_responses);
    $ajax_content = $embedded_responses[0]->getText();
    $this->assertStringNotContainsString('<script type="importmap"', $ajax_content, 'The embedded AJAX response does not contain an import map.');
    $commands = Json::decode($ajax_content);
    $preload_commands = array_filter($commands, static fn (array $command) => ($command['command'] ?? NULL) === 'add_module_preload');
    $this->assertCount(1, $preload_commands, 'The embedded AJAX response adds module preloads for the libraries it attaches.');
    $preload_command = array_values($preload_commands)[0];
    $this->assertSame($this->expectedModulePreloads(), $preload_command['data']);
    // The module preloads are added before the module script, so that the
    // modules can be fetched as early as possible.
    $command_names = array_column($commands, 'command');
    $this->assertContains('add_js', $command_names, 'The embedded AJAX response adds the module script.');
    $this->assertLessThan(array_search('add_js', $command_names), array_search('add_module_preload', $command_names));
  }

  /**
   * Tests that a BigPipe no-JS placeholder replacement does not add an import map.
   *
   * The placeholder replacement is sent as an embedded HTML response. It does
   * not add module preloads either: the no-JS BigPipe placeholders are only
   * sent to browsers with JavaScript disabled, where module scripts cannot
   * execute, so there is no module to preload.
   */
  public function testBigPipeNoJsResponseAddsNoImportMapAndNoModulePreloads(): void {
    // BigPipe only works when the request is associated with a session.
    $this->drupalLogin($this->rootUser);
    // The no-JS BigPipe placeholders are used when the big_pipe_nojs cookie
    // is set.
    $this->getSession()->setCookie(BigPipeStrategy::NOJS_COOKIE, '1');
    $this->drupalGet('/importmap-test/bigpipe-consumer');
    $this->assertSingleImportMapInInitialResponse();
    // No module preloads anywhere in the response: the attached module
    // script cannot execute in a browser without JavaScript.
    $this->assertCount(0, $this->getSession()->getPage()->findAll('css', 'link[rel="modulepreload"]'));
  }

}
