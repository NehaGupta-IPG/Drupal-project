<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\ckeditor5\Plugin\CKEditor5Plugin\Heading;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\jsonapi\JsonApiSpec;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * JSON:API integration test for the "Editor" config entity type.
 */
#[Group('jsonapi')]
#[RunTestsInSeparateProcesses]
class EditorTest extends ConfigEntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'editor', 'ckeditor5'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'editor';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'editor--editor';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\editor\EditorInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected static $firstCreatedEntityId = 'special';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    $this->grantPermissionsToTestedRole(['administer filters']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    // Create a "Llama" filter format.
    $llama_format = FilterFormat::create([
      'name' => 'Llama',
      'format' => 'llama',
      'langcode' => 'es',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<a> <b> <lo> <p>',
          ],
        ],
      ],
    ]);

    $llama_format->save();

    // Create a "Camelids" editor.
    $camelids = Editor::create([
      'format' => 'llama',
      'editor' => 'ckeditor5',
      'image_upload' => [
        'status' => FALSE,
      ],
    ]);
    $camelids
      ->setImageUploadSettings([
        'status' => TRUE,
        'scheme' => 'public',
        'directory' => 'inline-images',
        'max_size' => NULL,
        'max_dimensions' => [
          'width' => NULL,
          'height' => NULL,
        ],
      ])
      ->save();

    return $camelids;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument(): array {
    $self_url = Url::fromUri('base:/jsonapi/editor/editor/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
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
        'type' => 'editor--editor',
        'links' => [
          'self' => ['href' => $self_url],
        ],
        'attributes' => [
          'dependencies' => [
            'config' => [
              'filter.format.llama',
            ],
            'module' => [
              'ckeditor5',
            ],
          ],
          'editor' => 'ckeditor5',
          'image_upload' => [
            'status' => TRUE,
            'scheme' => 'public',
            'directory' => 'inline-images',
            'max_size' => NULL,
            'max_dimensions' => [
              'width' => NULL,
              'height' => NULL,
            ],
          ],
          'langcode' => 'en',
          'settings' => [
            'toolbar' => [
              'items' => ['heading', 'bold', 'italic'],
            ],
            'plugins' => [
              'ckeditor5_heading' => Heading::DEFAULT_CONFIGURATION,
            ],
          ],
          'status' => TRUE,
          'drupal_internal__format' => 'llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument(): array {
    if (!FilterFormat::load('special')) {
      FilterFormat::create([
        'name' => 'My special format',
        'format' => 'special',
        'langcode' => 'en',
        'filters' => [],
      ])->save();
    }

    return [
      'data' => [
        'type' => 'editor--editor',
        'attributes' => [
          'dependencies' => [
            'config' => [
              'filter.format.special',
            ],
          ],
          'drupal_internal__format' => 'special',
          'editor' => 'ckeditor5',
          'settings' => [
            'toolbar' => [
              'items' => ['bold', 'italic'],
            ],
          ],
          'image_upload' => [
            'status' => FALSE,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPatchDocument() {
    return [
      'data' => [
        'type' => 'editor--editor',
        'id' => $this->entity->uuid(),
        'attributes' => [
          'drupal_internal__format' => 'llama',
          // This specifies a different image upload directory.
          'image_upload' => [
            'status' => TRUE,
            'scheme' => 'public',
            'directory' => 'inline-images-changed',
            'max_size' => NULL,
            'max_dimensions' => [
              'width' => NULL,
              'height' => NULL,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method): string {
    return "The 'administer filters' permission is required.";
  }

  /**
   * {@inheritdoc}
   */
  protected function createAnotherEntity($key) {
    FilterFormat::create([
      'name' => 'Pachyderm',
      'format' => 'pachyderm',
      'langcode' => 'fr',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<a> <b> <lo> <p>',
          ],
        ],
      ],
    ])->save();

    $entity = Editor::create([
      'format' => 'pachyderm',
      'editor' => 'ckeditor5',
      'image_upload' => [
        'status' => FALSE,
      ],
    ]);

    $entity->setImageUploadSettings([
      'status' => TRUE,
      'scheme' => 'public',
      'directory' => 'inline-images',
      'max_size' => NULL,
      'max_dimensions' => [
        'width' => NULL,
        'height' => NULL,
      ],
    ])->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected static function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Also reset the 'filter_format' entity access control handler because
    // editor access also depends on access to the configured filter format.
    \Drupal::entityTypeManager()->getAccessControlHandler('filter_format')->resetCache();
    return parent::entityAccess($entity, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function testPatchIndividual() {
    // Ensure ::getModifiedEntityForPatchTesting() can pick an alternative value
    // for the 'format' property.
    // cSpell:disable
    FilterFormat::create([
      // TRICKY: `llama` is transformed to `yynzn` by str_rot13() in the test.
      // @see ::getModifiedEntityForPatchTesting()
      'format' => 'yynzn',
      'name' => $this->randomString(),
    ])->save();
    // cSpell:enable

    return parent::testPatchIndividual();
  }

  /**
   * Cannot use `unicorn` because `editor.settings.unicorn` is not validatable.
   */
  public function testEditorPluginWithNonFullyValidatableSettings() {
    $this->container->get('module_installer')->install(['editor_test']);

    $this->setUpAuthorization('POST');
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save(TRUE);

    $doc = $this->getPostDocument();
    // @see \Drupal\editor_test\Plugin\Editor\UnicornEditor
    $doc['data']['attributes']['editor'] = 'unicorn';
    // @see `type: editor.settings.unicorn` in core/modules/editor/tests/modules/editor_test/config/schema/editor_test.schema.yml
    $doc['data']['attributes']['settings'] = [
      'ponies_too' => FALSE,
    ];

    // Create editor POST request.
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
          'detail' => "settings: The value at property path settings cannot be validated. Contact the developer of the \"unicorn\" editor plugin to make this validatable.",
          'source' => [
            'pointer' => '/data/attributes/settings',
          ],
        ],
      ],
      'jsonapi' => static::$jsonApiMember,
    ];
    $this->assertResourceResponse(422, $expected_document, $response);
  }

}
