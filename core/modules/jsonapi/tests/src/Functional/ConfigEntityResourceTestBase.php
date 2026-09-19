<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use GuzzleHttp\RequestOptions;

/**
 * Resource test base class for config entities.
 *
 * @todo Simplify this in https://www.drupal.org/node/3423459 or https://www.drupal.org/node/3423462.
 */
abstract class ConfigEntityResourceTestBase extends ResourceTestBase {

  /**
   * A list of test methods to skip.
   *
   * @var array
   */
  const SKIP_METHODS = [
    // @todo Remove in https://www.drupal.org/node/3423462: the FieldConfigs using this FieldStorageConfig
    'testRelated',
    // @todo Remove in https://www.drupal.org/node/3423462: the `instances` (FieldConfigs) of this FieldStorageConfig
    'testRelationships',
    // @todo Remove in https://www.drupal.org/node/3423459.
    'testDeleteIndividual',
    'testRevisions',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    if (in_array($this->name(), static::SKIP_METHODS, TRUE)) {
      // Skip before installing Drupal to prevent unnecessary use of resources.
      $this->markTestSkipped("Not yet supported for config entities.");
    }
    parent::setUp();
  }

  /**
   * Tests field configuration that has no create access or validatable settings.
   */
  protected function assertFieldConfigWritesRejected(string $settings_schema): void {
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save();
    $this->setUpAuthorization('POST');
    $original = $this->entityLoadUnchanged($this->entity->id())->toArray();
    $options = $this->getAuthenticationRequestOptions();
    $options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $options[RequestOptions::BODY] = Json::encode($this->getPostDocument());
    $url = Url::fromRoute(sprintf('jsonapi.%s.collection.post', static::$resourceTypeName));
    // These entity access handlers do not grant create access, even to field
    // administrators. JSON:API must not bypass the entity access decision.
    $response = $this->request('POST', $url, $options);
    $this->assertResourceResponse(403, FALSE, $response);

    $url = Url::fromRoute(sprintf('jsonapi.%s.individual', static::$resourceTypeName), ['entity' => $this->entity->uuid()]);
    $options[RequestOptions::BODY] = Json::encode([
      'data' => [
        'type' => static::$resourceTypeName,
        'id' => $this->entity->uuid(),
        'attributes' => ['label' => 'Changed label'],
      ],
    ]);
    $response = $this->request('PATCH', $url, $options);
    $this->assertResourceErrorResponse(422, "settings: The value at property path settings cannot be validated because the resolved schema type \"$settings_schema\" is not fully validatable.", NULL, $response, '/data/attributes/settings');
    $this->assertSame($original, $this->entityLoadUnchanged($this->entity->id())->toArray());
    $this->doTestDeleteIndividual();
  }

}
