<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\locale\File\LocaleFile;
use Drupal\locale\LocaleFetch;
use Drupal\locale\LocaleProjectNotFoundException;
use Drupal\locale\LocaleTranslationSource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests locale batches.
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocaleBatchTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'locale',
    'language',
  ];

  /**
   * Tests that a batch operation for an untracked project throws.
   */
  public function testMissingProjectThrows(): void {
    $context = [
      'sandbox' => [],
      'results' => [],
      'finished' => 0.5,
      'message' => '',
    ];

    $this->expectException(LocaleProjectNotFoundException::class);
    \Drupal::service(LocaleFetch::class)->batchImport('missing_project', 'fr', [], $context);
  }

  /**
   * Tests that a queue task for an untracked project is discarded, not retried.
   */
  public function testMissingProjectQueueTaskIsDiscarded(): void {
    $queue = \Drupal::queue('locale_translation', TRUE);
    $queue->createItem([LocaleFetch::class . ':batchImport', ['missing_project', 'fr', []]]);

    $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('locale_translation');
    $item = $queue->claimItem();
    // The worker catches the exception so the item can be removed instead of
    // being left to be processed again on the next cron run.
    $worker->processItem($item->data);
    $queue->deleteItem($item);

    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * Checks that the import batch finishes if the translation has already been imported.
   */
  public function testBuildProjects(): void {
    $this->installConfig(['locale']);
    $this->installSchema('locale', ['locale_file']);
    $this->container->get('module_handler')->loadInclude('locale', 'batch.inc');

    \Drupal::database()->insert('locale_file')
      ->fields([
        'project' => 'drupal',
        'langcode' => 'en',
        'filename' => 'drupal.po',
        'version' => \Drupal::VERSION,
        'timestamp' => time(),
      ])
      ->execute();

    $context = [];
    \Drupal::service(LocaleFetch::class)->batchImport('drupal', 'en', [], $context);
    $this->assertEquals(1, $context['finished']);
    $this->assertEquals('Ignoring already imported translation for drupal.', $context['message']);
  }

  /**
   * Tests English translations skip default drupal.org pattern.
   */
  public function testEnglishTranslationSkipsDefaultPattern(): void {
    $this->installConfig(['locale', 'language']);
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locale_file']);
    $this->container->get('module_handler')->loadInclude('locale', 'batch.inc');

    // Create source matching default drupal.org pattern.
    $source = new LocaleTranslationSource(
      project: 'test_module',
      langcode: 'en',
    );
    $source->version = '1.0.0';
    $source->files[LOCALE_TRANSLATION_REMOTE] = new LocaleFile('test_module-1.0.0.en.po', 'https://ftp.drupal.org/files/translations/all/test_module/test_module-1.0.0.en.po', '');

    \Drupal::keyValue('locale.translation_status')->setMultiple(['test_module' => ['en' => $source]]);

    $context = ['results' => []];
    \Drupal::service(LocaleFetch::class)->batchStatusCheck(
      'test_module',
      'en',
      ['use_remote' => TRUE, 'finish_feedback' => TRUE],
      $context
    );

    // Should be marked as failed (skipped) for English default pattern.
    $this->assertContains('test_module', $context['results']['failed_files']);
    $this->assertCount(0, $context['results']['files'] ?? []);
  }

  /**
   * Tests non-English languages do not skip default drupal.org pattern.
   */
  public function testNonEnglishLanguagesUnaffected(): void {
    $this->installConfig(['locale', 'language']);
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locale_file']);
    $this->container->get('module_handler')->loadInclude('locale', 'batch.inc');

    // Create source matching default drupal.org pattern.
    $source = new LocaleTranslationSource(
      project: 'test_module',
      langcode: 'de',
    );
    $source->version = '1.0.0';
    $source->files[LOCALE_TRANSLATION_REMOTE] = new LocaleFile('test_module-1.0.0.en.po', 'https://ftp.drupal.org/files/translations/all/test_module/test_module-1.0.0.en.po', '');

    \Drupal::keyValue('locale.translation_status')->setMultiple(['test_module' => ['de' => $source]]);

    $context = ['results' => []];
    \Drupal::service(LocaleFetch::class)->batchStatusCheck(
      'test_module',
      'de',
      ['use_remote' => TRUE, 'finish_feedback' => TRUE],
      $context
    );

    $this->assertContains('test_module', $context['results']['files']);
    $this->assertCount(0, $context['results']['failed_files'] ?? []);
  }

}
