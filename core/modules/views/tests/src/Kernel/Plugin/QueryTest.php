<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use Drupal\views_test_data\Plugin\views\query\QueryTest as QueryTestPlugin;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests query plugins.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class QueryTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view', 'test_exposed_relationship_admin_ui'];

  /**
   * Provides Views metadata for the test data table.
   */
  protected function viewsData() {
    $data = parent::viewsData();
    $data['views_test_data']['table']['base']['query_id'] = 'query_test';
    $data['views_test_data']['table']['base']['cache_tags'] = ['tag_test'];
    $data['views_test_data']['table']['base']['cache_contexts'] = ['user'];
    $data['views_test_data']['table']['base']['cache_max_age'] = 100;

    // Add additional cache information to the node and users_field_data for
    // testing QueryPluginBase's cache metadata bubbling from views data.
    $data['node']['table']['base']['cache_tags'] = ['node_tag_test'];
    $data['node']['table']['base']['cache_contexts'] = ['theme', 'session'];
    $data['node']['table']['base']['cache_max_age'] = 50;
    $data['users_field_data']['table']['base']['cache_tags'] = ['user_tag_test'];
    $data['users_field_data']['table']['base']['cache_max_age'] = 0;
    $data['users_field_data']['table']['base']['cache_contexts'] = ['user'];
    return $data;
  }

  /**
   * Tests query plugins.
   */
  public function testQuery(): void {
    $this->_testInitQuery();
    $this->_testQueryExecute();
    $this->queryMethodsTests();
  }

  /**
   * Tests the ViewExecutable::initQuery method.
   */
  public function _testInitQuery(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->initQuery();
    $this->assertInstanceOf(QueryTestPlugin::class, $view->query);
  }

  /**
   * Executes the views query, ensures it's not empty.
   */
  public function _testQueryExecute(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->initQuery();
    $view->query->setAllItems($this->dataSet());

    $this->executeView($view);
    $this->assertNotEmpty($view->result, 'Make sure the view result got filled');
  }

  /**
   * Tests methods provided by the QueryPluginBase.
   *
   * @see \Drupal\views\Plugin\views\query\QueryPluginBase
   */
  protected function queryMethodsTests(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->initQuery();
    $this->assertNull($view->query->getLimit(), 'Default to an empty limit.');
    $rand_number = rand(5, 10);
    $view->query->setLimit($rand_number);
    $this->assertEquals($rand_number, $view->query->getLimit(), 'set_limit adapts the amount of items.');
  }

  /**
   * Tests the bubbling of cache information from views data.
   */
  public function testCacheMetadataFromViewData() {
    // Test a view with no relationships.
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->initQuery();
    $this->assertContains('tag_test', $view->query->getCacheTags());
    $this->assertContains('user', $view->query->getCacheContexts());
    $this->assertEquals(100, $view->query->getCacheMaxAge());

    // Test a view with relationships.
    $view = Views::getView('test_exposed_relationship_admin_ui');
    $view->setDisplay();
    $view->build();
    $this->assertContains('node_tag_test', $view->query->getCacheTags());
    $this->assertContains('user_tag_test', $view->query->getCacheTags());
    $this->assertContains('theme', $view->query->getCacheContexts());
    $this->assertContains('session', $view->query->getCacheContexts());
    $this->assertContains('user', $view->query->getCacheContexts());
    $this->assertEquals(0, $view->query->getCacheMaxAge());
  }

}
