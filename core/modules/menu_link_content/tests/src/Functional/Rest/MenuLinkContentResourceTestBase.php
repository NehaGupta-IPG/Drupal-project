<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_link_content\Functional\Rest;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\Tests\rest\Functional\ResourceTestBase;

/**
 * ResourceTestBase for MenuLinkContent entity.
 */
abstract class MenuLinkContentResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['content_translation', 'menu_link_content'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'menu_link_content';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [
    'changed' => NULL,
  ];

  /**
   * The MenuLinkContent entity.
   *
   * @var \Drupal\menu_link_content\MenuLinkContentInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // @todo We override the parent setUp(), because the result of calling
    // loadUnchanged() is not what it should be.

    ResourceTestBase::setUp();

    // Calculate REST Resource config entity ID.
    static::$resourceConfigId = 'entity.' . static::$entityTypeId;

    $this->entityStorage = $this->container->get('entity_type.manager')
      ->getStorage(static::$entityTypeId);

    // Add access-protected field.
    FieldStorageConfig::create([
      'entity_type' => static::$entityTypeId,
      'field_name' => 'field_rest_test',
      'type' => 'text',
    ])
      ->setCardinality(1)
      ->save();
    FieldConfig::create([
      'entity_type' => static::$entityTypeId,
      'field_name' => 'field_rest_test',
      'bundle' => 'menu_link_content',
    ])
      ->setLabel('Test field')
      ->setTranslatable(FALSE)
      ->save();

    // Add multi-value field.
    FieldStorageConfig::create([
      'entity_type' => static::$entityTypeId,
      'field_name' => 'field_rest_test_multivalue',
      'type' => 'string',
    ])
      ->setCardinality(3)
      ->save();
    FieldConfig::create([
      'entity_type' => static::$entityTypeId,
      'field_name' => 'field_rest_test_multivalue',
      'bundle' => 'menu_link_content',
    ])
      ->setLabel('Test field: multi-value')
      ->setTranslatable(FALSE)
      ->save();

    // Create an entity.
    $this->entity = $this->createEntity();

    // Set a default value on the fields.
    $this->entity->set('field_rest_test', ['value' => 'All the faith they had had had had no effect on the outcome of their life.']);
    $this->entity->set('field_rest_test_multivalue', [['value' => 'One'], ['value' => 'Two']]);
    $this->entity->set('rest_test_validation', ['value' => 'allowed value']);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    switch ($method) {
      case 'GET':
      case 'POST':
      case 'PATCH':
      case 'DELETE':
        $this->grantPermissionsToTestedRole(['administer menu']);
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $menu_link = MenuLinkContent::create([
      'id' => 'llama',
      'title' => 'Llama Gabilondo',
      'description' => 'Llama Gabilondo',
      'link' => [
        'uri' => 'https://nl.wikipedia.org/wiki/Llama',
        'options' => [
          'fragment' => 'a-fragment',
          'attributes' => [
            'class' => ['example-class'],
          ],
        ],
      ],
      'weight' => 0,
      'menu_name' => 'main',
    ]);
    $menu_link->save();

    return $menu_link;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'title' => [
        [
          'value' => 'Drama llama',
        ],
      ],
      'link' => [
        [
          'uri' => 'http://www.urbandictionary.com/define.php?term=drama%20llama',
          'options' => [
            'fragment' => 'a-fragment',
            'attributes' => [
              'class' => ['example-class'],
            ],
          ],
        ],
      ],
      'bundle' => [
        [
          'value' => 'menu_link_content',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
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
      'revision_id' => [
        [
          'value' => 1,
        ],
      ],
      'title' => [
        [
          'value' => 'Llama Gabilondo',
        ],
      ],
      'link' => [
        [
          'uri' => 'https://nl.wikipedia.org/wiki/Llama',
          'title' => NULL,
          'options' => [
            'fragment' => 'a-fragment',
            'attributes' => [
              'class' => ['example-class'],
            ],
          ],
        ],
      ],
      'weight' => [
        [
          'value' => 0,
        ],
      ],
      'menu_name' => [
        [
          'value' => 'main',
        ],
      ],
      'langcode' => [
        [
          'value' => 'en',
        ],
      ],
      'bundle' => [
        [
          'value' => 'menu_link_content',
        ],
      ],
      'description' => [
        [
          'value' => 'Llama Gabilondo',
        ],
      ],
      'external' => [
        [
          'value' => FALSE,
        ],
      ],
      'rediscover' => [
        [
          'value' => FALSE,
        ],
      ],
      'expanded' => [
        [
          'value' => FALSE,
        ],
      ],
      'enabled' => [
        [
          'value' => TRUE,
        ],
      ],
      'changed' => [
        [
          'value' => (new \DateTime())->setTimestamp($this->entity->getChangedTime())
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'default_langcode' => [
        [
          'value' => TRUE,
        ],
      ],
      'parent' => [],
      'revision_created' => [
        [
          'value' => (new \DateTime())->setTimestamp((int) $this->entity->getRevisionCreationTime())
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'revision_user' => [],
      'revision_log_message' => [],
      'revision_translation_affected' => [
        [
          'value' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    switch ($method) {
      case 'DELETE':
        return "The 'administer menu' permission is required.";

      default:
        return parent::getExpectedUnauthorizedAccessMessage($method);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedCacheContexts() {
    return [
      'languages:language_interface',
      'url.site',
      'user.permissions',
    ];
  }

}
