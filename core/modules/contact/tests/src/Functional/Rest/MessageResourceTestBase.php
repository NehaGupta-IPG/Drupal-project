<?php

declare(strict_types=1);

namespace Drupal\Tests\contact\Functional\Rest;

use Drupal\contact\Entity\ContactForm;
use Drupal\contact\Entity\Message;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Url;
use Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Resource test base for the ContactMessage entity.
 */
abstract class MessageResourceTestBase extends EntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['contact'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'contact_message';

  /**
   * {@inheritdoc}
   */
  protected static $labelFieldName = 'subject';

  /**
   * The Message entity.
   *
   * @var \Drupal\contact\MessageInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method) {
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
  protected function getNormalizedPostEntity() {
    return [
      'subject' => [
        [
          'value' => 'Drama llama',
        ],
      ],
      'contact_form' => [
        [
          'target_id' => 'camelids',
        ],
      ],
      'message' => [
        [
          'value' => 'http://www.urbandictionary.com/define.php?term=drama%20llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    throw new \Exception('Not yet supported.');
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
  public function testGet(): void {
    // Contact Message entities are not stored, so they cannot be retrieved.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "rest.entity.contact_message.GET" does not exist.');

    $this->provisionEntityResource();
    Url::fromRoute('rest.entity.contact_message.GET')->toString(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function testPatch(): void {
    // Contact Message entities are not stored, so they cannot be modified.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "rest.entity.contact_message.PATCH" does not exist.');

    $this->provisionEntityResource();
    Url::fromRoute('rest.entity.contact_message.PATCH')->toString(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function testDelete(): void {
    // Contact Message entities are not stored, so they cannot be deleted.
    $this->expectException(RouteNotFoundException::class);
    $this->expectExceptionMessage('Route "rest.entity.contact_message.DELETE" does not exist.');

    $this->provisionEntityResource();
    Url::fromRoute('rest.entity.contact_message.DELETE')->toString(TRUE);
  }

  /**
   * Overrides base class method to assert a 403 response for all calls.
   *
   * Checks that 403: forbidden returned for all calls.
   */
  public function testPost(): void {
    // @todo Remove this in https://www.drupal.org/node/2300677.
    if ($this->entity instanceof ConfigEntityInterface) {
      $this->assertTrue(TRUE, 'POSTing config entities is not yet supported.');
      return;
    }

    $this->initAuthentication();
    $has_canonical_url = $this->entity->hasLinkTemplate('canonical');

    // Try with all of the following request bodies.
    $not_parseable_request_body = '!{>}<';

    // The URL and Guzzle request options that will be used in this test. The
    // request options will be modified/expanded throughout this test:
    // - to first test all mistakes a developer might make, and assert that the
    //   error responses provide a good DX
    // - to eventually result in a well-formed request that succeeds.
    $url = $this->getEntityResourcePostUrl();
    $request_options = [];

    // DX: 404 when resource not provisioned. HTML response because missing
    // ?_format query string.
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame(['text/html; charset=UTF-8'], $response->getHeader('Content-Type'));

    $url->setOption('query', ['_format' => static::$format]);

    // DX: 404 when resource not provisioned.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(404, 'No route found for "POST ' . $this->getEntityResourcePostUrl()->setAbsolute()->toString() . '"', $response);

    $this->provisionEntityResource();
    // Simulate the developer again forgetting the ?_format query string.
    $url->setOption('query', []);

    // DX: 415 when no Content-Type request header. HTML response because
    // missing ?_format query string.
    $response = $this->request('POST', $url, $request_options);
    $this->assertSame(415, $response->getStatusCode());
    $this->assertSame(['text/html; charset=UTF-8'], $response->getHeader('Content-Type'));
    $this->assertStringContainsString('A client error happened', (string) $response->getBody());

    $url->setOption('query', ['_format' => static::$format]);

    // DX: 415 when no Content-Type request header.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(415, 'No "Content-Type" request header specified', $response);

    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    if (static::$auth) {
      // DX: forgetting authentication: authentication provider-specific error
      // response.
      $response = $this->request('POST', $url, $request_options);
      $this->assertResponseWhenMissingAuthentication('POST', $response);
    }

    $request_options = NestedArray::mergeDeep($request_options, $this->getAuthenticationRequestOptions('POST'));
    $forbiddenAccessMessage = $this->getExpectedUnauthorizedAccessMessage('POST');

    // DX: 403 when unauthorized.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, $forbiddenAccessMessage, $response);

    $this->setUpAuthorization('POST');
    // DX: 403 when no request body. Would normally return 400 for no
    // message body, but all requests forbidden.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, $forbiddenAccessMessage, $response);

    $request_options[RequestOptions::BODY] = $not_parseable_request_body;

    // DX: 403 when request body not parseable.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, $forbiddenAccessMessage, $response);

    $request_options[RequestOptions::HEADERS]['Content-Type'] = static::$mimeType;

    // 403 for well-formed request. Would normally return 200 for well formed
    // request, but request forbidden so returns 403.
    $response = $this->request('POST', $url, $request_options);
    $this->assertResourceErrorResponse(403, $forbiddenAccessMessage, $response);
  }

}
