<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

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
    'content_moderation',
    'block',
    'block_content',
    'node',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Moderated content', 'moderated_content', TRUE);
    $this->grantUserPermissionToCreateContentOfType($this->adminUser, 'moderated_content');

    // Enable additional languages.
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

    // Create the first user.
    $web_user = $this->drupalCreateUser([
      'view any unpublished content',
      'access content overview',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      'use editorial transition archived_draft',
      'use editorial transition archived_published',
    ]);

    $this->grantUserPermissionToCreateContentOfType($web_user, 'moderated_content');

    // Create a second user.
    $second_web_user = $this->drupalCreateUser([
      'view any unpublished content',
      'access content overview',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      'use editorial transition archived_draft',
      'use editorial transition archived_published',
      'translate any entity',
    ]);

    $this->grantUserPermissionToCreateContentOfType($second_web_user, 'moderated_content');

    $this->drupalLogin($web_user);

    // Create the first revision of the content.
    // The author will be "web_user".
    $this->drupalGet('node/add/moderated_content');
    $this->submitForm([
      'title[0][value]' => 'First version of the content en.',
      'moderation_state[0][state]' => 'published',
    ], 'Save');

    $this->drupalLogin($second_web_user);

    $node = $this->getNodeByTitle('First version of the content en.');
    $translation_path = sprintf('node/%d/translations/add/en/es', $node->id());
    // Create a second revision.
    $this->drupalGet($translation_path);
    $this->submitForm([
      'title[0][value]' => 'First version of the content es.',
      'moderation_state[0][state]' => 'published',
    ], 'Save');

    // Check that the revision number is right.
    $this->assertUserNodeCount($web_user->id(), 1, 'en', 'node_field_data');
    $this->assertUserNodeCount($second_web_user->id(), 1, 'es', 'node_field_data');

    $this->assertUserNodeCount($web_user->id(), 2, 'en', 'node_field_revision');
    $this->assertUserNodeCount($second_web_user->id(), 1, 'es', 'node_field_revision');

    // Cancel "web_user" account.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('user/' . $web_user->id() . '/cancel');
    $this->submitForm([], 'Confirm');

    // Verify that the revision of the first user was assigned to the anonymous
    // user.
    $this->assertUserNodeCount($web_user->id(), 0, 'en', 'node_field_data');
    $this->assertUserNodeCount(0, 1, 'en', 'node_field_data');
    $this->assertUserNodeCount($second_web_user->id(), 1, 'es', 'node_field_data');

    $this->assertUserNodeCount($web_user->id(), 0, 'en', 'node_field_revision');
    $this->assertUserNodeCount(0, 2, 'en', 'node_field_revision');
    $this->assertUserNodeCount($second_web_user->id(), 1, 'es', 'node_field_revision');

    // Check content as an anonymous user.
    $this->drupalLogout();

    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);

    // Check the default language.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
    $this->drupalGet($node->toUrl('canonical', ['language' => $node->language()])->toString());
    $this->assertSession()->pageTextContains('First version of the content en.');

    // Check that the author, previously "web_user", now is anonymous.
    $this->assertEquals(0, $node->uid->entity->id());
    $this->assertEquals('First version of the content en.', $node->title->value);

    // Check that the translation revision still has the right user
    // "second_web_user".
    $translation = $node->getTranslation('es');

    // Check the translation.
    $this->drupalGet('es/node/' . $node->id());
    $this->assertSession()->pageTextContains('First version of the content es.');
    $this->assertEquals('First version of the content es.', $translation->title->value);
    $this->assertEquals($second_web_user->id(), $translation->uid->entity->id(), 'Check user on translation.');
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
