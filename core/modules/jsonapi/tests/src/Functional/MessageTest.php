<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\contact\Entity\ContactForm;
use Drupal\contact\Entity\Message;
use Drupal\Core\Url;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * JSON:API integration test for the "Message" content entity type.
 *
 * @group jsonapi
 */
class MessageTest extends ResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['contact'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'contact_message';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'contact_message--camelids';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\contact\MessageInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected static $labelFieldName = 'subject';

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    $this->grantPermissionsToTestedRole(['access site-wide contact form']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    if (!ContactForm::load('camelids')) {
      // Create a "Camelids" contact form.
      ContactForm::create([
        'id' => 'camelids',
        'label' => 'Llama',
        'message' => 'Let us know what you think about llamas',
        'reply' => 'Llamas are indeed awesome!',
        'recipients' => [
          'llama@example.com',
          'contact@example.com',
        ],
      ])->save();
    }

    $message = Message::create([
      'contact_form' => 'camelids',
      'subject' => 'Llama Gabilondo',
      'message' => 'Llamas are awesome!',
    ]);
    $message->save();

    return $message;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    throw new \Exception('Not yet supported.');
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument(): array {
    return [
      'data' => [
        'type' => 'contact_message--camelids',
        'attributes' => [
          'subject' => 'Drama llama',
          'message' => 'http://www.urbandictionary.com/define.php?term=drama%20llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method) {
    if ($method === 'POST') {
      return "Message entities are not stored.";
    }
    return parent::getExpectedUnauthorizedAccessMessage($method);
  }

  /**
   * {@inheritdoc}
   */
  public function testGetIndividual(): void {
    // Contact Message entities are not stored, so they cannot be retrieved.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "jsonapi.contact_message--camelids.individual" does not exist.');

    Url::fromRoute('jsonapi.contact_message--camelids.individual')->toString(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTestPatchIndividual(): void {
    // Contact Message entities are not stored, so they cannot be modified.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "jsonapi.contact_message--camelids.individual" does not exist.');

    Url::fromRoute('jsonapi.contact_message--camelids.individual')->toString(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTestDeleteIndividual(): void {
    // Contact Message entities are not stored, so they cannot be deleted.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "jsonapi.contact_message--camelids.individual" does not exist.');

    Url::fromRoute('jsonapi.contact_message--camelids.individual')->toString(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function testRelated(): void {
    // Contact Message entities are not stored, so they cannot be retrieved.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "jsonapi.contact_message--camelids.related" does not exist.');

    Url::fromRoute('jsonapi.contact_message--camelids.related')->toString(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function testRelationships(): void {
    // Contact Message entities are not stored, so they cannot be retrieved.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "jsonapi.contact_message--camelids.relationship.get" does not exist.');

    Url::fromRoute('jsonapi.contact_message--camelids.relationship.get')->toString(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function testCollection(): void {
    $collection_url = Url::fromRoute('jsonapi.contact_message--camelids.collection.post')->setAbsolute(TRUE);
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    // 405 because Message entities are not stored, so they cannot be retrieved,
    // yet the same URL can be used to POST them.
    $response = $this->request('GET', $collection_url, $request_options);
    $this->assertSame(405, $response->getStatusCode());
    $this->assertSame(['POST'], $response->getHeader('Allow'));
  }

  /**
   * {@inheritdoc}
   */
  public function testRevisions(): void {
    // Contact Message entities are not stored, so they cannot be retrieved.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "jsonapi.contact_message--camelids.individual" does not exist.');

    Url::fromRoute('jsonapi.contact_message--camelids.individual')->toString(TRUE);
  }

  /**
   * Tests POSTing an individual resource, plus edge cases to ensure good DX.
   */
  public function testPostIndividual(): void {
    // @todo Remove this in https://www.drupal.org/node/2300677.
    if ($this->entity instanceof ConfigEntityInterface) {
      $this->assertTrue(TRUE, 'POSTing config entities is not yet supported.');
      return;
    }

    // Try with all of the following request bodies.
    $not_parseable_request_body = '!{>}<';
    $parseable_valid_request_body = Json::encode($this->getPostDocument());
    $parseable_invalid_request_body_missing_type = Json::encode($this->removeResourceTypeFromDocument($this->getPostDocument()));
    if ($this->entity->getEntityType()->hasKey('label')) {
      $parseable_invalid_request_body = Json::encode($this->makeNormalizationInvalid($this->getPostDocument(), 'label'));
    }
    $parseable_invalid_request_body_2 = Json::encode(NestedArray::mergeDeep(['data' => ['id' => $this->randomMachineName(129)]], $this->getPostDocument()));
    $parseable_invalid_request_body_3 = Json::encode(NestedArray::mergeDeep(['data' => ['attributes' => ['field_rest_test' => $this->randomString()]]], $this->getPostDocument()));
    $parseable_invalid_request_body_4 = Json::encode(NestedArray::mergeDeep(['data' => ['attributes' => ['field_nonexistent' => $this->randomString()]]], $this->getPostDocument()));

    // The URL and Guzzle request options that will be used in this test. The
    // request options will be modified/expanded throughout this test:
    // - to first test all mistakes a developer might make, and assert that the
    //   error responses provide a good DX
    // - to eventually result in a well-formed request that succeeds.
    $url = Url::fromRoute(sprintf('jsonapi.%s.collection.post', static::$resourceTypeName));
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions());

    // DX: 405 when read-only mode is enabled.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(405, sprintf("JSON:API is configured to accept only read operations. Site administrators can configure this at %s.", Url::fromUri('base:/admin/config/services/jsonapi')->setAbsolute()->toString(TRUE)->getGeneratedUrl()), $url, $response);
    if ($this->resourceType->isLocatable()) {
      $this->assertSame(['GET'], $response->getHeader('Allow'));
    }
    else {
      $this->assertSame([''], $response->getHeader('Allow'));
    }

    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);

    // DX: 415 when no Content-Type request header.
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(415, $response->getStatusCode());

    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    // DX: 403 when unauthorized.
    $response = $this->request('POST', $url, $request_options);
    $reason = $this->getExpectedUnauthorizedAccessMessage('POST');
    $this->assertResourceErrorResponse(403, (string) $reason, $url, $response);

    $this->setUpAuthorization('POST');

    // DX: 403 when no request body. Would normally expect 400 but all
    // requests forbidden.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, (string) $reason, $url, $response, FALSE);

    $request_options[RequestOptions::BODY] = $not_parseable_request_body;

    // DX: 403 when request body not parseable. Would normally expect 400.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, (string) $reason, $url, $response, FALSE);

    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';

    // 403 for well-formed request. Would normally expect 201, but all
    // requests forbidden.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, (string) $reason, $url, $response, FALSE);
  }

}
