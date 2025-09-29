<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests general content moderation workflow related with user and nodes.
 */
#[Group('content_moderation')]
#[RunTestsInSeparateProcesses]
class ModerationStateUserTest extends ModerationStateTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Moderated content', 'moderated_content', TRUE);

    // Enable a second language.
    ConfigurableLanguage::createFromLangcode('es')->save();
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'moderated_content', TRUE);

    // Set the default user cancel method.
    $this->config('user.settings')
      ->set('cancel_method', 'user_cancel_reassign')
      ->save();

    // Add permissions to the admin account.
    $role_ids = $this->adminUser->getRoles();
    $role_id = reset($role_ids);
    $role = Role::load($role_id);
    $role->grantPermission('administer users');
    $role->grantPermission('view any unpublished content');
    $role->save();
  }

  /**
   * Tests that canceling a user retains the default revision.
   */
  public function testUserCancel(): void {
    // Create two users.
    $user1 = $this->drupalCreateUser();
    $user2 = $this->drupalCreateUser();

    // Create a node that is authored by the first user and translated by the
    // second user.
    $node = $this->createNode([
      'type' => 'moderated_content',
      'title' => 'First version of the content en.',
      'langcode' => 'en',
      'moderation_state' => 'published',
      'uid' => $user1->id(),
    ]);

    $node->addTranslation('es', [
      'title' => 'First version of the content es.',
      'moderation_state' => 'published',
      'uid' => $user2->id(),
    ])->save();

    // Add a forward draft revision in the original language.
    $node = $node->getTranslation('en');
    $node->setNewRevision();
    $node->moderation_state = 'draft';
    $node
      ->set('title', 'Second version of the content en.')
      ->setRevisionUserId($user1->id());
    $node->save();

    // Check that the revisions have been created correctly.
    $this->assertUserNodeCount($user1->id(), 1, 'en', 'node_field_data');
    $this->assertUserNodeCount($user1->id(), 0, 'es', 'node_field_data');
    $this->assertUserNodeCount($user2->id(), 0, 'en', 'node_field_data');
    $this->assertUserNodeCount($user2->id(), 1, 'es', 'node_field_data');

    $this->assertUserNodeCount($user1->id(), 3, 'en', 'node_field_revision');
    $this->assertUserNodeCount($user1->id(), 0, 'es', 'node_field_revision');
    $this->assertUserNodeCount($user2->id(), 0, 'en', 'node_field_revision');
    $this->assertUserNodeCount($user2->id(), 2, 'es', 'node_field_revision');

    // Cancel the account of the first user.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('user/' . $user1->id() . '/cancel');
    $this->submitForm([], 'Confirm');

    // Verify that the revision of the first user was assigned to the anonymous
    // user.
    $this->assertUserNodeCount($user1->id(), 0, 'en', 'node_field_data');
    $this->assertUserNodeCount(0, 1, 'en', 'node_field_data');
    $this->assertUserNodeCount($user2->id(), 1, 'es', 'node_field_data');

    $this->assertUserNodeCount($user1->id(), 0, 'en', 'node_field_revision');
    $this->assertUserNodeCount(0, 3, 'en', 'node_field_revision');
    $this->assertUserNodeCount($user2->id(), 2, 'es', 'node_field_revision');

    // Check content as an anonymous user.
    $this->drupalLogout();

    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);

    // Check the default language.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->drupalGet($node->toUrl('canonical', ['language' => $node->language()])->toString());
    $this->assertSession()->pageTextContains('First version of the content en.');

    // Check that the author, previously "user1", now is anonymous.
    $this->assertEquals(0, $node->getOwnerId());
    // The original, non-forward revision should be the one that is shown.
    $this->assertEquals('First version of the content en.', $node->title->value);

    // Check that the translation revision still has the right user "user2".
    $translation = $node->getTranslation('es');

    // Check the translation.
    $this->drupalGet('es/node/' . $node->id());
    $this->assertSession()->pageTextContains('First version of the content es.');
    $this->assertEquals('First version of the content es.', $translation->title->value);
    $this->assertEquals($user2->id(), $translation->getOwnerId(), 'Check user on translation.');
  }

  /**
   * Assert node count by langcode and uid.
   */
  protected function assertUserNodeCount(int|string $uid, int $count, string $langcode, string $table): void {
    $query = \Drupal::database()->select($table, 'n');
    $result = $query
      ->fields('n')
      ->condition('uid', $uid)
      ->condition('langcode', $langcode)
      ->countQuery()
      ->execute();

    $this->assertEquals($count, $result->fetchField(), 'User with uid ' . $uid . 'should have ' . $count . ' rows on table ' . $table . ' and language ' . $langcode);
  }

}
