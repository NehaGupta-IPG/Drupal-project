<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi_translation\Functional;

use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTest as BaseJsonApiFunctionalTest;

/**
 * Tests that the basic JSON:API functionality keeps working as before.
 *
 * @group jsonapi_translation
 *
 * @internal
 */
class JsonApiFunctionalTest extends BaseJsonApiFunctionalTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'basic_auth',
    'content_translation',
    'jsonapi_translation',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ContentLanguageSettings::create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'article',
    ])
      ->setLanguageAlterable(TRUE)
      ->save();
  }

}
