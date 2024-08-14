<?php

namespace Drupal\Tests\path\Functional;

use Drupal\entity_test\Entity\EntityTestExternal;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Path form UI for external entity.
 *
 * @group path
 */
class PathEntityTestExternalFormTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_test',
    'path',
    'path_entity_test_external',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the Path form UI.
   */
  public function testEntityTestExternalForm() {
    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'create url aliases',
    ]));

    // Create an entity_test_external entity.
    $this->drupalGet('/entity_test_external/add');
    $this->submitForm([], 'Save');
    $this->assertSession()->statusMessageContains('entity_test_external 1 has been created.', 'status');
    // Update the entity.
    $this->drupalGet('/entity_test_external/1/edit');
    $this->submitForm([], 'Save');
    $this->assertSession()->statusMessageContains('entity_test_external 1 has been updated.', 'status');
    // Try to set a path.
    $this->drupalGet('/entity_test_external/1/edit');
    $this->submitForm(['path[0][alias]' => '/something'], 'Save');
    $this->assertSession()->statusMessageContains('An entity without a route cannot have a path.', 'error');
    // Delete the entity.
    $entity = EntityTestExternal::load('1');
    $entity->delete();
  }

}
