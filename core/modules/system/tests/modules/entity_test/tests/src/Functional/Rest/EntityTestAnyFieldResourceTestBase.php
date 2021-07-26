<?php

namespace Drupal\Tests\entity_test\Functional\Rest;

use Drupal\entity_test\Entity\EntityTestAnyField;
use Drupal\entity_test\TraversableObject;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\user\Entity\User;

/**
 * Base class for AnyItem's resource test.
 */
abstract class EntityTestAnyFieldResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'entity_test_any_field';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [];

  /**
   * The main entity used for testing.
   *
   * @var \Drupal\entity_test\Entity\EntityTestAnyField
   */
  protected $entity;

  /**
   * The object value to assign to a @FieldType=any field.
   *
   * @var object
   */
  protected $traversableObject;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    $this->traversableObject = new TraversableObject(
      [
        'property1' => 'value1',
        'property2' => FALSE,
      ]
    );
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer entity_test content']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $entity = EntityTestAnyField::create([
      'name' => 'Llama',
      'type' => 'entity_test_any_field',
      'data' => $this->traversableObject,
    ]);
    $entity->setOwnerId(0);
    $entity->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    $author = User::load(0);

    return [
      'uuid' => [
        [
          'value' => $this->entity->uuid(),
        ],
      ],
      'id' => [
        [
          'value' => 1,
        ],
      ],
      'name' => [
        [
          'value' => 'Llama',
        ],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'created' => [
        [
          'value' => (new \DateTime())->setTimestamp((int) $this->entity->get('created')->value)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'user_id' => [
        [
          'target_id' => (int) $author->id(),
          'target_type' => 'user',
          'target_uuid' => $author->uuid(),
          'url' => $author->toUrl()->toString(),
        ],
      ],
      'data' => [
        ['value' => $this->traversableObject->toArray()],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'name' => [
        [
          'value' => 'Dramallama',
        ],
      ],
      'data' => [
        0 => ['value' => $this->traversableObject->toArray()],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    if ($this->config('rest.settings')->get('bc_entity_resource_permissions')) {
      return parent::getExpectedUnauthorizedAccessMessage($method);
    }

    return "The 'administer entity_test content' permission is required.";
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    return ['user.permissions'];
  }

}
