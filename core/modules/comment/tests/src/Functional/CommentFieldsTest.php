<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional;

use Drupal\comment\CommentingStatus;
use Drupal\comment\Entity\CommentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests fields on comments.
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class CommentFieldsTest extends CommentTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the default 'comment_body' field is correctly added.
   */
  public function testCommentDefaultFields(): void {
    // Do not make assumptions on default node types created by the test
    // installation profile, and create our own.
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $this->addDefaultCommentField('node', 'test_node_type');

    // Check that the 'comment_body' field is present on the comment bundle.
    $field = FieldConfig::loadByName('comment', 'comment', 'comment_body');
    $this->assertNotEmpty($field, 'The comment_body field is added when a comment bundle is created');

    $field->delete();

    // Check that the 'comment_body' field is not deleted since it is persisted
    // even if it has no fields.
    $field_storage = FieldStorageConfig::loadByName('comment', 'comment_body');
    $this->assertInstanceOf(FieldStorageConfig::class, $field_storage);

    // Create a new content type.
    $type_name = 'test_node_type_2';
    $this->drupalCreateContentType(['type' => $type_name]);
    $this->addDefaultCommentField('node', $type_name);

    // Check that the 'comment_body' field exists and has an instance on the
    // new comment bundle.
    $field_storage = FieldStorageConfig::loadByName('comment', 'comment_body');
    $this->assertInstanceOf(FieldStorageConfig::class, $field_storage);
    $field = FieldConfig::loadByName('comment', 'comment', 'comment_body');
    $this->assertTrue(isset($field), "The comment_body field is present for comments on type $type_name");

    // Test adding a field that defaults to CommentingStatus::Closed.
    $this->addDefaultCommentField('node', 'test_node_type', 'who_likes_ponies', CommentingStatus::Closed, 'who_likes_ponies');

    $field = FieldConfig::load('node.test_node_type.who_likes_ponies');
    $this->assertEquals(CommentingStatus::Closed->value, $field->getDefaultValueLiteral()[0]['status']);
  }

  /**
   * Tests that you can remove a comment field.
   */
  public function testCommentFieldDelete(): void {
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $this->addDefaultCommentField('node', 'test_node_type');
    // We want to test the handling of removing the primary comment field, so we
    // ensure there is at least one other comment field attached to a node type
    // so that comment_entity_load() runs for nodes.
    $this->addDefaultCommentField('node', 'test_node_type', 'comment2');

    // Create a sample node.
    $node = $this->drupalCreateNode([
      'title' => 'Baloney',
      'type' => 'test_node_type',
    ]);

    $this->drupalLogin($this->webUser);

    $this->drupalGet('node/' . $node->nid->value);
    $elements = $this->cssSelect('.comment-form');
    $this->assertCount(2, $elements, 'There are two comment fields on the node.');

    // Delete the first comment field.
    FieldStorageConfig::loadByName('node', 'comment')->delete();
    $this->drupalGet('node/' . $node->nid->value);
    $elements = $this->cssSelect('.comment-form');
    $this->assertCount(1, $elements, 'There is one comment field on the node.');
  }

  /**
   * Tests link building with non-default comment field names.
   */
  public function testCommentFieldLinksNonDefaultName(): void {
    $this->drupalCreateContentType(['type' => 'test_node_type']);
    $this->addDefaultCommentField('node', 'test_node_type', 'comment2');

    $web_user2 = $this->drupalCreateUser([
      'access comments',
      'post comments',
      'create article content',
      'edit own comments',
      'skip comment approval',
      'access content',
    ]);

    // Create a sample node.
    $node = $this->drupalCreateNode([
      'title' => 'Baloney',
      'type' => 'test_node_type',
      'promote' => TRUE,
    ]);

    // Go to the node first so that web_user2 see new comments.
    $this->drupalLogin($web_user2);
    $this->drupalGet($node->toUrl());
    $this->drupalLogout();

    // Test that buildCommentedEntityLinks() does not break when the 'comment'
    // field does not exist. Requires at least one comment.
    $this->drupalLogin($this->webUser);
    $this->postComment($node, 'Here is a comment', '', NULL, 'comment2');
    $this->drupalLogout();

    $this->drupalLogin($web_user2);

    // We want to check the attached drupalSettings of
    // \Drupal\comment\CommentLinkBuilder::buildCommentedEntityLinks. Therefore
    // we need a node listing, let's use views for that.
    $this->container->get('module_installer')->install(['views'], TRUE);
    $this->drupalGet('node');
  }

  /**
   * Tests creating a comment field through the interface.
   */
  #[DataProvider('providerCommentFieldCreate')]
  public function testCommentFieldCreate(string $entity_type, string $field_ui_path): void {
    if ($entity_type === 'media') {
      $this->container->get('module_installer')->install(['media']);
      $this->createMediaType('image', ['id' => 'image']);
    }

    $user = $this->drupalCreateUser([
      "administer $entity_type fields",
    ]);
    $this->drupalLogin($user);

    // Create a comment field on the target entity type.
    $edit = [
      'label' => 'Test comment',
      'field_name' => 'test_comment',
    ];
    $this->drupalGet($field_ui_path . '/fields/add-field');
    $this->clickLink('Comments');
    $this->submitForm($edit, 'Continue');

    // Comment types for nodes do not provide an option for this target type.
    $message = 'There is no comment type yet.';
    $this->assertSession()->fieldNotExists('field_storage[subform][settings][comment_type]');
    $this->assertSession()->pageTextContains($message);
    $this->assertSession()->linkNotExists('Add a new comment type.');
    $this->submitForm([], 'Update settings');
    $this->assertSession()->statusMessageContains($message, 'error');
    $this->assertNull(FieldStorageConfig::loadByName($entity_type, 'field_test_comment'));

    // An administrator can create the missing type and return to the field.
    $user->addRole($this->drupalCreateRole(['administer comment types']));
    $user->save();
    $this->drupalLogin($user);
    $settings_path = $field_ui_path . '/add-field/' . $entity_type . '/field_test_comment';
    $this->drupalGet($settings_path);
    $this->clickLink('Add a new comment type.');
    $this->assertSession()->fieldValueEquals('target_entity_type_id', $entity_type);
    $this->submitForm([
      'id' => 'test_comment_type',
      'label' => 'Test comments',
    ], 'Save and manage fields');
    $this->assertSession()->addressEquals($settings_path);
    $this->assertSame($entity_type, CommentType::load('test_comment_type')->getTargetEntityTypeId());

    // Only the matching type is available after returning to the field settings.
    $select = $this->assertSession()->selectExists('field_storage[subform][settings][comment_type]');
    $this->assertSession()->pageTextNotContains('Select the Comment type to use for this comment field.');
    $this->assertCount(1, $select->findAll('css', 'option'));
    $this->assertSession()->optionExists('field_storage[subform][settings][comment_type]', 'test_comment_type');
    $this->submitForm([
      'field_storage[subform][settings][comment_type]' => 'test_comment_type',
    ], 'Update settings');

    // Try to save the comment field with "Comments per page"
    // setting value as zero.
    $edit = [
      'settings[per_page]' => 0,
    ];
    $this->drupalGet($settings_path);
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusMessageContains('Saved Test comment configuration.', 'status');
    $storage = FieldStorageConfig::loadByName($entity_type, 'field_test_comment');
    $this->assertSame('test_comment_type', $storage->getSetting('comment_type'));
  }

  /**
   * Provides entity types with no matching comment type.
   *
   * @return array<string, array{string, string}>
   *   The target entity type and its field UI path.
   */
  public static function providerCommentFieldCreate(): array {
    return [
      'user' => ['user', 'admin/config/people/accounts'],
      'media' => ['media', 'admin/structure/media/manage/image'],
    ];
  }

}
