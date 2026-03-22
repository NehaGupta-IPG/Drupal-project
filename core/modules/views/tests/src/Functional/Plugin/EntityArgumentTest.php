<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Plugin;

use Drupal\Tests\taxonomy\Functional\Views\TaxonomyTestBase;
use Drupal\user\UserInterface;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the handler of the view: entity target argument.
 *
 * @see \Drupal\views\Plugin\views\argument\EntityArgument
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class EntityArgumentTest extends TaxonomyTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static array $testViews = ['test_entity_id_argument'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to administer taxonomy.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp($import_test_views, $modules);
    ViewTestData::createTestViews(static::class, ['views_test_config']);

    // Create an administrative user.
    $this->adminUser = $this->drupalCreateUser(['administer taxonomy', 'bypass node access']);
    $this->drupalLogin($this->adminUser);

  }

  /**
   * Tests the generated title of a view with an entity target argument.
   */
  public function testArgumentTitle(): void {
    $view = Views::getView('test_entity_id_argument');
    $assert_session = $this->assertSession();

    // Test with single entity ID examples.
    $this->drupalGet('/entity-id-argument-test');
    $assert_session->titleEquals($view->getTitle() . ' | Drupal');
    $this->drupalGet('/entity-id-argument-test/1');
    $assert_session->titleEquals('test: title ' . $this->term1->label() . ', input ' . $this->term1->id() . ' | Drupal');
    $this->drupalGet('/entity-id-argument-test/2');
    $assert_session->titleEquals('test: title ' . $this->term2->label() . ', input ' . $this->term2->id() . ' | Drupal');

    // Test with multiple entity IDs examples.
    $this->drupalGet('/entity-id-argument-test/1,2');
    $assert_session->titleEquals('test: title ' . $this->term1->label() . ', ' . $this->term2->label() . ', input ' . $this->term1->id() . ',' . $this->term2->id() . ' | Drupal');
    $this->drupalGet('/entity-id-argument-test/2,1');
    $assert_session->titleEquals('test: title ' . $this->term2->label() . ', ' . $this->term1->label() . ', input ' . $this->term2->id() . ',' . $this->term1->id() . ' | Drupal');
    $this->drupalGet('/entity-id-argument-test/1+2');
    $assert_session->titleEquals('test: title ' . $this->term1->label() . ' + ' . $this->term2->label() . ', input ' . $this->term1->id() . '+' . $this->term2->id() . ' | Drupal');
    $this->drupalGet('/entity-id-argument-test/2+1');
    $assert_session->titleEquals('test: title ' . $this->term2->label() . ' + ' . $this->term1->label() . ', input ' . $this->term2->id() . '+' . $this->term1->id() . ' | Drupal');
  }

  /**
   * Tests validating access to the argument.
   */
  public function testArgumentValidateAccess(): void {
    $assert_session = $this->assertSession();
    // Unpublish term2.
    $this->term2->setUnpublished()->save();

    // Admin is logged in and has access to unpublished taxonomy.
    $this->drupalGet('/entity-id-argument-test-access/1');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('/entity-id-argument-test-access/2');
    $assert_session->statusCodeEquals(200);

    // Create a less privileged user who can't access term2.
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $this->drupalGet('/entity-id-argument-test-access/1');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('/entity-id-argument-test-access/2');
    $assert_session->statusCodeEquals(403);

    // Publish term2 so this user has access to it.
    $this->term2->setPublished()->save();
    $this->drupalGet('/entity-id-argument-test-access/2');
    $assert_session->statusCodeEquals(200);

    // Re-unpublish term2 and confirm access is rescinded.
    $this->term2->setUnpublished()->save();
    $this->drupalGet('/entity-id-argument-test-access/2');
    $assert_session->statusCodeEquals(403);
  }

}
