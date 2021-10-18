<?php

declare(strict_types = 1);

namespace Drupal\Tests\views\Functional\Entity;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests named parameters upcasting.
 *
 * @group views
 */
class NamedParamUpcastingTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'param_upcast_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['param_upcast'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);
    ViewTestData::createTestViews(get_class($this), ['param_upcast_test']);
  }

  /**
   * Tests named parameters upcasting.
   */
  public function testNamedParamUpcasting(): void {
    $account1 = $this->createUser();
    $account2 = $this->createUser();
    EntityTest::create([
      'name' => 'Entity 1',
      'user_id' => $account1,
      'type' => 'entity_test',
    ])->save();
    EntityTest::create([
      'name' => 'Entity 2',
      'user_id' => $account2,
      'type' => 'entity_test',
    ])->save();

    $this->drupalLogin($this->createUser(['view test entity']));

    // Check that named 'user' parameter has been upcasted to user entity.
    $this->drupalGet("/user/{$account1->id()}/content");
    $this->assertSession()->pageTextContains('Entity 1');
    $this->assertSession()->pageTextNotContains('Entity 2');
    $this->assertUpcasted();

    $this->drupalGet("/user/{$account2->id()}/content");
    $this->assertSession()->pageTextContains('Entity 2');
    $this->assertSession()->pageTextNotContains('Entity 1');
    $this->assertUpcasted();

    // Test with a wrong ID.
    $this->drupalGet('/user/99999/content');
    $this->assertNotUpcasted();
  }

  /**
   * Asserts that the user ID parameter has been upcasted.
   */
  protected function assertUpcasted(): void {
    $state = \Drupal::state();
    $this->assertTrue($state->get('param_upcast_test.upcasted'));
    $state->delete('param_upcast_test.upcasted');
  }

  /**
   * Asserts that the user ID parameter has not been upcasted.
   */
  protected function assertNotUpcasted(): void {
    $state = \Drupal::state();
    $this->assertNull($state->get('param_upcast_test.upcasted'));
    $state->delete('param_upcast_test.upcasted');
  }

}
