<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiSpec;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * JSON:API integration test for the "DateFormat" config entity type.
 */
#[Group('jsonapi')]
#[RunTestsInSeparateProcesses]
class DateFormatTest extends ConfigEntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'date_format';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'date_format--date_format';

  /**
   * {@inheritdoc}
   */
  protected static $anonymousUsersCanViewLabels = TRUE;

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\Core\Datetime\DateFormatInterface
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
    $this->grantPermissionsToTestedRole(['administer site configuration']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a date format.
    $date_format = DateFormat::create([
      'id' => 'llama',
      'label' => 'Llama',
      'pattern' => 'F d, Y',
    ]);

    $date_format->save();

    return $date_format;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument(): array {
    $self_url = Url::fromUri('base:/jsonapi/date_format/date_format/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'type' => 'date_format--date_format',
        'links' => [
          'self' => ['href' => $self_url],
        ],
        'attributes' => [
          'dependencies' => [],
          'label' => 'Llama',
          'langcode' => 'en',
          'locked' => FALSE,
          'pattern' => 'F d, Y',
          'status' => TRUE,
          'drupal_internal__id' => 'llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected static $firstCreatedEntityId = 'special';

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument(): array {
    return [
      'data' => [
        'type' => 'date_format--date_format',
        'attributes' => [
          'drupal_internal__id' => 'special',
          'label' => 'My special date format',
          'pattern' => 'U',
        ],
      ],
    ];
  }

  /**
   * @testWith [null, "This value should not be null."]
   *           ["⌚", "This is not a valid date format."]
   */
  public function testPostValidationErrors(?string $pattern, string $expected_validation_error) {
    $this->setUpAuthorization('POST');
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);

    $doc = $this->getPostDocument();
    $doc['data']['attributes']['pattern'] = $pattern;

    // Create date_format POST request.
    $url = Url::fromRoute(sprintf('jsonapi.%s.collection.post', static::$resourceTypeName));
    $request_options = $this->getAuthenticationRequestOptions();
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $request_options[RequestOptions::HEADERS]['Content-Type'] = 'application/vnd.api+json';
    $request_options[RequestOptions::BODY] = Json::encode($doc);

    // POST request: 422 when adding relationships to non-existing resources.
    $response = $this->request('POST', $url, $request_options);
    $expected_document = [
      'errors' => [
        0 => [
          'title' => 'Unprocessable Content',
          'status' => '422',
          'detail' => "pattern: $expected_validation_error",
          'source' => [
            'pointer' => '/data/attributes/pattern',
          ],
        ],
      ],
      'jsonapi' => static::$jsonApiMember,
    ];
    $this->assertResourceResponse(422, $expected_document, $response);
  }

}
