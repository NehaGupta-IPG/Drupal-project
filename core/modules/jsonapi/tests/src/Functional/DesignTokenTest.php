<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Theme\Entity\DesignToken;
use GuzzleHttp\RequestOptions;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiSpec;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * JSON:API integration test for the "DesignToken" config entity type.
 */
#[Group('jsonapi')]
#[RunTestsInSeparateProcesses]
class DesignTokenTest extends ConfigEntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected static $firstCreatedEntityId = 'special';

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'design_token';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'design_token--design_token';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\Core\Theme\Entity\DesignTokenInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    $this->grantPermissionsToTestedRole(['administer design tokens']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a design token.
    $entity = DesignToken::create([
      'id' => 'my_token',
      'path' => 'my-token',
      'type' => 'fontWeight',
      'scopes' => [
        ':root' => 500,
      ],
    ]);

    $entity->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument(): array {
    $self_url = Url::fromUri('base:/jsonapi/design_token/design_token/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    return [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => ['href' => JsonApiSpec::SUPPORTED_SPECIFICATION_PERMALINK],
          ],
        ],
        'version' => JsonApiSpec::SUPPORTED_SPECIFICATION_VERSION,
      ],
      'links' => [
        'self' => ['href' => $self_url],
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'design_token--design_token',
        'links' => [
          'self' => ['href' => $self_url],
        ],
        'attributes' => [
          'dependencies' => [],
          'langcode' => 'en',
          'status' => TRUE,
          'path' => 'my-token',
          'design_token_type' => 'fontWeight',
          'scopes' => [
            ':root' => '500',
          ],
          'drupal_internal__id' => 'my_token',
        ],
      ],
    ];
  }

  /**
   * Tests that scoped values remain subject to config schema validation.
   */
  public function testInvalidFontWeight(): void {
    $this->setUpAuthorization('POST');
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save();
    $original = $this->entityLoadUnchanged($this->entity->id())->toArray();
    $options = $this->getAuthenticationRequestOptions();
    $options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    foreach (['POST', 'PATCH'] as $method) {
      $document = $method === 'POST' ? $this->getPostDocument() : $this->getPatchDocument();
      $document['data']['attributes']['scopes'][':root'] = 0;
      $url = $method === 'POST'
        ? Url::fromRoute('jsonapi.design_token--design_token.collection.post')
        : Url::fromRoute('jsonapi.design_token--design_token.individual', ['entity' => $this->entity->uuid()]);
      $options[RequestOptions::BODY] = Json::encode($document);
      $response = $this->request($method, $url, $options);
      $this->assertResourceResponse(422, FALSE, $response);
      $errors = Json::decode((string) $response->getBody())['errors'];
      $this->assertCount(1, $errors);
      $this->assertSame('/data/attributes/scopes/:root', $errors[0]['source']['pointer']);
      $this->assertStringContainsString('between 1 and 1000', $errors[0]['detail']);
      $this->assertNull($this->entityLoadUnchanged('special'));
      $this->assertSame($original, $this->entityLoadUnchanged($this->entity->id())->toArray());
    }
  }

  /**
   * Tests that invalid primitive types are rejected before plugin construction.
   */
  public function testInvalidFontWeightType(): void {
    $this->setUpAuthorization('POST');
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save();
    $original = $this->entityLoadUnchanged($this->entity->id())->toArray();
    $options = $this->getAuthenticationRequestOptions();
    $options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    foreach (['POST', 'PATCH'] as $method) {
      $document = $method === 'POST' ? $this->getPostDocument() : $this->getPatchDocument();
      $document['data']['attributes']['scopes'][':root'] = '600';
      $url = $method === 'POST'
        ? Url::fromRoute('jsonapi.design_token--design_token.collection.post')
        : Url::fromRoute('jsonapi.design_token--design_token.individual', ['entity' => $this->entity->uuid()]);
      $options[RequestOptions::BODY] = Json::encode($document);
      $response = $this->request($method, $url, $options);
      $this->assertResourceErrorResponse(422, 'The attribute scopes.:root must be an integer.', $url, $response, FALSE);
      $this->assertNull($this->entityLoadUnchanged('special'));
      $this->assertSame($original, $this->entityLoadUnchanged($this->entity->id())->toArray());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getPatchDocument(): array {
    $document = parent::getPatchDocument();
    // The token path is immutable, unlike its scoped value.
    $document['data']['attributes']['path'] = 'my-token';
    return $document;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument(): array {
    return [
      'data' => [
        'type' => static::$resourceTypeName,
        'attributes' => [
          'drupal_internal__id' => 'special',
          'path' => 'special-token',
          'design_token_type' => 'fontWeight',
          'scopes' => [':root' => 600],
        ],
      ],
    ];
  }

}
