<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Kernel\Controller;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonapi\Controller\FileUpload;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\jsonapi\Controller\FileUpload.
 */
#[CoversClass(FileUpload::class)]
#[Group('jsonapi')]
#[RunTestsInSeparateProcesses]
class FileUploadTest extends JsonapiKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'serialization',
    'system',
    'user',
    'jsonapi_test_field_access',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    // Add the additional table schemas.
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    NodeType::create([
      'type' => 'lorem',
      'name' => 'Lorem',
    ])->save();
    $type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $type->save();
    $type = NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ]);
    $type->save();
    $this->createEntityReferenceField('node', 'article', 'field_relationships', 'Relationship', 'node', 'default', ['target_bundles' => ['article']], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    // Create a file field on the article bundle, used to test field-item-level
    // access via hook_entity_field_access() with a real FieldItemList.
    FieldStorageConfig::create([
      'field_name' => 'field_test_file',
      'entity_type' => 'node',
      'type' => 'file',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_file',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Test file',
    ])->save();

    Role::create([
      'id' => 'article_editor',
      'label' => 'article editor',
      'permissions' => [
        'access content',
        'create article content',
        'edit any article content',
      ],
    ])->save();

    Role::create([
      'id' => 'page_editor',
      'label' => 'page editor',
      'permissions' => [
        'access content',
        'create page content',
        'edit any page content',
      ],
    ])->save();

    Role::create([
      'id' => 'editor',
      'label' => 'editor',
      'permissions' => [
        'bypass node access',
      ],
    ])->save();
  }

  /**
   * Tests checkFileUploadAccess().
   *
   * Covers entity-level access, bundle-scoped field access, field-item-level
   * access via hook_entity_field_access(), and the runtime mismatch guard.
   */
  public function testCheckFileUploadAccess(): void {
    // Create a set of users for access testing.
    $article_editor = User::create([
      'name' => 'article editor',
      'mail' => 'article@localhost',
      'status' => 1,
      // Do not use UID 1 as that has access to everything.
      'uid' => 2,
      'roles' => ['article_editor'],
    ]);
    $page_editor = User::create([
      'name' => 'page editor',
      'mail' => 'page@localhost',
      'status' => 1,
      'uid' => 3,
      'roles' => ['page_editor'],
    ]);
    $editor = User::create([
      'name' => 'editor',
      'mail' => 'editor@localhost',
      'status' => 1,
      'uid' => 4,
      'roles' => ['editor'],
    ]);
    $no_access_user = User::create([
      'name' => 'no access',
      'mail' => 'user@localhost',
      'status' => 1,
      'uid' => 5,
    ]);

    // Create an entity to test access against.
    $node = Node::create([
      'title' => 'dummy_title',
      'type' => 'article',
      'uid' => 1,
    ]);

    $field_manager = $this->container->get('entity_field.manager');
    // While the method is only used to check file fields it should work without
    // error for any field whether it is a base field or a bundle field.
    $base_field_definition = $field_manager->getBaseFieldDefinitions('node')['title'];
    $article_field_definitions = $field_manager->getFieldDefinitions('node', 'article');
    $bundle_field_definition = $article_field_definitions['field_relationships'];
    $file_field_definition = $article_field_definitions['field_test_file'];

    // Tests the expected access result for each user.
    // The $article_editor account can edit any article.
    $result = FileUpload::checkFileUploadAccess($article_editor, $base_field_definition, $node);
    $this->assertTrue($result->isAllowed());
    // The article editor cannot create a node of undetermined type.
    $result = FileUpload::checkFileUploadAccess($article_editor, $base_field_definition);
    $this->assertFalse($result->isAllowed());
    // The article editor can edit any article.
    $result = FileUpload::checkFileUploadAccess($article_editor, $bundle_field_definition, $node);
    $this->assertTrue($result->isAllowed());
    // The article editor can create an article. The type can be determined
    // because the field is a bundle field.
    $result = FileUpload::checkFileUploadAccess($article_editor, $bundle_field_definition);
    $this->assertTrue($result->isAllowed());

    // The $editor account has the bypass node access permissions and can edit
    // and create all node types.
    $result = FileUpload::checkFileUploadAccess($editor, $base_field_definition, $node);
    $this->assertTrue($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($editor, $base_field_definition);
    $this->assertTrue($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($editor, $bundle_field_definition, $node);
    $this->assertTrue($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($editor, $bundle_field_definition);
    $this->assertTrue($result->isAllowed());

    // The $page_editor account can only edit and create pages therefore has no
    // access.
    $result = FileUpload::checkFileUploadAccess($page_editor, $base_field_definition, $node);
    $this->assertFalse($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($page_editor, $base_field_definition);
    $this->assertFalse($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($page_editor, $bundle_field_definition, $node);
    $this->assertFalse($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($page_editor, $bundle_field_definition);
    $this->assertFalse($result->isAllowed());

    // The $no_access_user account has no access at all.
    $result = FileUpload::checkFileUploadAccess($no_access_user, $base_field_definition, $node);
    $this->assertFalse($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($no_access_user, $base_field_definition);
    $this->assertFalse($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($no_access_user, $bundle_field_definition, $node);
    $this->assertFalse($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($no_access_user, $bundle_field_definition);
    $this->assertFalse($result->isAllowed());

    // hook_entity_field_access() in the test module denies field_test_file
    // edits when $items is not NULL (entity context present).
    $result = FileUpload::checkFileUploadAccess($article_editor, $file_field_definition);
    $this->assertTrue($result->isAllowed());
    $result = FileUpload::checkFileUploadAccess($article_editor, $file_field_definition, $node);
    $this->assertFalse($result->isAllowed());

    // A field definition scoped to a different bundle must throw.
    $page_node = Node::create(['title' => 'a page', 'type' => 'page', 'uid' => 1]);
    $this->expectException(\InvalidArgumentException::class);
    FileUpload::checkFileUploadAccess($article_editor, $bundle_field_definition, $page_node);
  }

}
