<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel\Views;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the file usage listing does not repeat each usage.
 *
 * @see https://www.drupal.org/project/drupal/issues/2632372
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileUsageListingTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'system',
    'views',
    'views_test_config',
    'views_test_data',
    'user',
    'field',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['file']);
    // The files view is optional config of the file module.
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * A file used in two places is listed twice, not four times.
   */
  public function testUsagePageListsEachUsageOnce(): void {
    $file = $this->createFileWithTwoUsages();
    $view = $this->executeUsageView((int) $file->id());

    $this->assertCount(2, $view->result);
    $ids = array_map(
      fn ($row) => (string) $view->field['entity_label']->getValue($row),
      $view->result,
    );
    sort($ids);
    $this->assertSame(['1', '2'], $ids);
  }

  /**
   * Existing config that still uses relationship "none" is rewritten on update.
   */
  public function testPostUpdatePointsFieldsAtRelationship(): void {
    $view = View::load('files');
    $displays = $view->get('display');
    foreach (['entity_label', 'type', 'module', 'count'] as $field_id) {
      $field = &$displays['page_2']['display_options']['fields'][$field_id];
      $field['relationship'] = 'none';
    }
    unset($field);
    $view->set('display', $displays);
    $view->save();

    $file = $this->createFileWithTwoUsages();
    $broken = $this->executeUsageView((int) $file->id());
    $this->assertCount(4, $broken->result);

    $module_handler = $this->container->get('module_handler');
    $module_handler->loadInclude('file', 'php', 'file.post_update');
    file_post_update_file_usage_listing_relationship();

    $updated = View::load('files');
    $fields = $updated->get('display')['page_2']['display_options']['fields'];
    foreach (['entity_label', 'type', 'module', 'count'] as $field_id) {
      $this->assertSame('fid', $fields[$field_id]['relationship']);
    }

    $fixed = $this->executeUsageView((int) $file->id());
    $this->assertCount(2, $fixed->result);
  }

  /**
   * Creates a permanent file with two usage rows.
   */
  protected function createFileWithTwoUsages(): FileInterface {
    $file = File::create([
      'uri' => 'public://file-usage-listing.txt',
      'filename' => 'file-usage-listing.txt',
      'filemime' => 'text/plain',
      'status' => FileInterface::STATUS_PERMANENT,
    ]);
    $file->save();

    $connection = $this->container->get('database');
    foreach (['1', '2'] as $id) {
      $connection->insert('file_usage')
        ->fields([
          'fid' => $file->id(),
          'module' => 'file',
          'type' => 'file',
          'id' => $id,
          'count' => 1,
        ])
        ->execute();
    }
    return $file;
  }

  /**
   * Executes the file usage display for one file.
   */
  protected function executeUsageView(int $fid): ViewExecutable {
    $view = Views::getView('files');
    $view->setDisplay('page_2');
    $view->preExecute([$fid]);
    $view->execute();
    return $view;
  }

}
