<?php

declare(strict_types=1);

namespace Drupal\Tests\path_alias\Functional\Rest;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use Drupal\path_alias\Entity\PathAlias;

/**
 * Base class for path_alias EntityResource tests.
 */
abstract class PathAliasResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['path', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'path_alias';

  /**
   * {@inheritdoc}
   */
  protected static $patchProtectedFieldNames = [];

  /**
   * {@inheritdoc}
   */
  protected static $firstCreatedEntityId = 3;

  /**
   * {@inheritdoc}
   */
  protected static $secondCreatedEntityId = 4;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
    $this->grantPermissionsToTestedRole(['administer url aliases']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $path_alias = PathAlias::create([
      'path' => '/<front>',
      'alias' => '/frontpage1',
    ]);
    $path_alias->save();
    return $path_alias;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {

    return [
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
      'langcode' => [
        [
          'value' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
        ],
      ],
      'path' => [
        [
          'value' => '/<front>',
        ],
      ],
      'alias' => [
        [
          'value' => '/frontpage1',
        ],
      ],
      'status' => [
        [
          'value' => TRUE,
        ],
      ],
      'uuid' => [
        [
          'value' => $this->entity->uuid(),
        ],
      ],
      'revision_uid' => [],
      'changed' => [
        [
          'value' => (new \DateTime())->setTimestamp($this->entity->getChangedTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      'revision_timestamp' => [
        [
          'value' => (new \DateTime())->setTimestamp($this->entity->getChangedTime())->setTimezone(new \DateTimeZone('UTC'))->format(\DateTime::RFC3339),
          'format' => \DateTime::RFC3339,
        ],
      ],
      "revision_log" => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return [
      'path' => [
        [
          'value' => '/<front>',
        ],
      ],
      'alias' => [
        [
          'value' => '/frontpage1',
        ],
      ],
    ];
  }

}
