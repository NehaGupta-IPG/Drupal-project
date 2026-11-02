<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi_translation\Functional;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\NodeInterface;
use Drupal\system\Entity\Menu;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;
use Drupal\user\Entity\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Multilingual functional test class.
 *
 * @group jsonapi_translation
 *
 * @coversDefaultClass \Drupal\jsonapi_translation\Controller\EntityResource
 *
 * @internal
 *
 * @see https://github.com/gabesullice/drupal-jsonapi-translations/blob/master/index.md
 */
class JsonApiTranslationFunctionalTest extends JsonApiFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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

    if ($this->profile !== 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Page',
      ]);
      $role_id = current($this->user->getRoles(TRUE));
      $this->grantPermissions(Role::load($role_id), ['edit any page content', 'delete any page content']);
      $this->rebuildAll();
    }

    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    $this->rebuildContainer();

    $entity_type_id = 'node';
    $bundle_name = 'article';

    ContentLanguageSettings::create([
      'target_entity_type_id' => $entity_type_id,
      'target_bundle' => $bundle_name,
    ])
      ->setThirdPartySetting('content_translation', 'enabled', TRUE)
      ->save();

    foreach (['field_test' => TRUE, 'field_test_ut' => FALSE] as $field_name => $is_translatable) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'type' => 'string',
        'entity_type' => $entity_type_id,
      ])
        ->save();

      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type_id,
        'bundle' => $bundle_name,
        'translatable' => $is_translatable,
      ])
        ->save();
    }

    $this->config('jsonapi.settings')
      ->set('read_only', FALSE)
      ->save(TRUE);
  }

  /**
   * Tests the GET method.
   *
   * @covers ::getIndividual
   */
  public function testGet(): void {
    // Ensure GET requests work in read-only mode.
    $this->config('jsonapi.settings')
      ->set('read_only', TRUE)
      ->save(TRUE);

    // Retrieve translations via the "Accept-Language" header, through which it
    // is possible to specify fallback choices.
    $headers = [
      'Accept-Language' => 'en,fr,it',
    ];
    $node = $this->createTranslatableNode('it');
    $this->doTestRequest($node, 'it', [], $headers);
    $this->createTranslation($node, 'fr');
    $this->doTestRequest($node, 'fr', [], $headers);
    $this->createTranslation($node, 'en');
    $this->doTestRequest($node, 'en', [], $headers);

    // Retrieve translations via the query string parameter. No fallback is
    // supported in this case.
    $node = $this->createTranslatableNode('fr');
    $this->doTestRequest($node, 'fr');
    $this->doTestQueryStringRequest($node, 'fr');
    $this->doTestQueryStringRequest($node, 'it', Response::HTTP_NOT_FOUND);
    $this->doTestQueryStringRequest($node, 'en', Response::HTTP_NOT_FOUND);
    $this->createTranslation($node, 'it');
    $this->doTestQueryStringRequest($node, 'it');
    $this->doTestQueryStringRequest($node, 'fr');
    $this->doTestQueryStringRequest($node, 'en', Response::HTTP_NOT_FOUND);

    // Verify that erroneous conditions are detected correctly.
    // @todo We need a new node to avoid caching issues. Remove this once a
    //   cache context taking the "Accept-Language" header into account is
    //   available. See https://www.drupal.org/project/drupal/issues/2430335.
    $node = $this->createTranslatableNode('en');
    $output = $this->doTestQueryStringRequest($node, 'en', Response::HTTP_BAD_REQUEST, $headers);
    $this->assertSame('Specifying both a request language and the "Accept-Language" header is not supported.', $output['errors'][0]['detail']);

    $headers = [
      'Content-Language' => 'fr',
    ];
    $output = $this->doTestQueryStringRequest($node, 'fr', Response::HTTP_BAD_REQUEST, $headers);
    $this->assertSame('Specifying the "Content-Language" header is not supported in cacheable requests.', $output['errors'][0]['detail']);

    $output = $this->doTestQueryStringRequest($node, 'es', Response::HTTP_UNPROCESSABLE_ENTITY);
    $this->assertSame('The specified language ("es") is invalid or has not been configured.', $output['errors'][0]['detail']);

    // Verify that entity types not supporting content translation are correctly
    // detected.
    $menu = Menu::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
    ]);
    $menu->save();
    $output = Json::decode($this->drupalGet('/jsonapi/menu/menu/' . $menu->uuid(), ['query' => ['langCode' => 'it']]));
    $this->assertSame('The request specified a preferred language, but the requested resource type does not support translation.', $output['errors'][0]['detail']);
  }

  /**
   * Tests an API request specifying language via the query string.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be retrieved.
   * @param string $expected_langcode
   *   The expected translation language.
   * @param int $expected_status
   *   (optional) The expected response HTTP status. Defaults to HTTP OK.
   * @param array $headers
   *   (optional) The request headers. Defaults to none.
   *
   * @return array
   *   The parsed JSON response.
   */
  protected function doTestQueryStringRequest(NodeInterface $node, string $expected_langcode, int $expected_status = Response::HTTP_OK, array $headers = []): array {
    return $this->doTestRequest($node, $expected_langcode, ['query' => ['langCode' => $expected_langcode]], $headers, $expected_status);
  }

  /**
   * Tests an API request.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be retrieved.
   * @param string $expected_langcode
   *   The expected translation language.
   * @param array $options
   *   (optional) The request options. Defaults to none.
   * @param array $headers
   *   (optional) The request headers. Defaults to none.
   * @param int $expected_status
   *   (optional) The expected response HTTP status. Defaults to HTTP OK.
   *
   * @return array
   *   The parsed JSON response.
   */
  protected function doTestRequest(NodeInterface $node, string $expected_langcode, array $options = [], array $headers = [], int $expected_status = Response::HTTP_OK): array {
    // We need to reset session, otherwise previous request headers will be
    // re-sent.
    $this->getSession()->reset();

    $node_path = '/jsonapi/node/article/' . $node->uuid();
    $output = Json::decode($this->drupalGet($node_path, $options, $headers));
    $this->assertSession()->statusCodeEquals($expected_status);

    if ($expected_status === Response::HTTP_OK) {
      $this->assertArrayHasKey('langcode', $output['data']['attributes']);
      $this->assertSame($expected_langcode, $output['data']['attributes']['langcode'] ?? '');
      $this->assertSession()->responseHeaderContains('Content-Language', $expected_langcode);
    }

    if (!isset($options['query']['langCode'])) {
      $expected_url = $this->buildUrl($node_path, ['query' => ['langCode' => $expected_langcode]]);
      $this->assertSession()->responseHeaderContains('Content-Location', $expected_url);
    }

    $this->assertSession()->responseHeaderContains('Vary', 'Accept-Language');

    return $output;
  }

  /**
   * Tests the POST method.
   *
   * @covers ::createIndividual
   * @covers ::createIndividualTranslation
   */
  public function testPost(): void {
    // Test creating a new node without specifying language results in a node
    // in the default language.
    $this->doTestNewNodeRequest('', Response::HTTP_CREATED, [], 'en');

    // Test that it is not possible to specify a language value explicitly, when
    // language is not alterable.
    $output = $this->doTestNewNodeRequest('en', Response::HTTP_FORBIDDEN);
    $this->assertSame('The current user is not allowed to POST the selected field (langcode).', $output['errors'][0]['detail']);

    // Specifying a langcode is allowed once configured to be alterable. Now an
    // entity can be created with the specified langcode.
    $language_settings = ContentLanguageSettings::loadByEntityTypeBundle('node', 'article');
    $language_settings
      ->setLanguageAlterable(TRUE)
      ->save();

    $this->doTestNewNodeRequest('en');
    $this->doTestNewNodeRequest('fr');

    // Change the default language to "it".
    $language_settings
      ->setDefaultLangcode('it')
      ->save();
    $this->doTestNewNodeRequest('', Response::HTTP_CREATED, [], 'it');

    // Test creating a node in non-default language.
    $headers = [
      'Content-Language' => 'fr',
    ];
    $this->doTestNewNodeRequest('', Response::HTTP_CREATED, $headers, 'fr');

    // Check that mismatching language information is correctly handle.
    $output = $this->doTestNewNodeRequest('it', Response::HTTP_UNPROCESSABLE_ENTITY, $headers);
    $this->assertSame('Translation resource language mismatch: "fr" (request metadata) vs "it" (request payload).', $output['errors'][0]['detail']);
    $this->doTestNewNodeRequest('fr', Response::HTTP_CREATED, $headers);

    $options = [
      'query' => ['langCode' => 'it'],
      'headers' => $headers,
    ];
    $output = $this->doTestNewNodeRequest('it', Response::HTTP_UNPROCESSABLE_ENTITY, $options);
    $this->assertSame('Translation resource language mismatch: "fr" (request metadata) vs "it" (request payload).', $output['errors'][0]['detail']);
    $output = $this->doTestNewNodeRequest('fr', Response::HTTP_CREATED, $options);

    // Check that creating a new translation without a language results in an
    // error.
    $node = current(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $output['data']['id']]));
    assert($node instanceof NodeInterface);
    $output = $this->doTestNewTranslationRequest($node, 'it', Response::HTTP_BAD_REQUEST, ['Content-Language' => '']);
    $this->assertSame('No resource translation language was specified.', $output['errors'][0]['detail']);

    // Test creating a valid translation.
    $this->doTestNewTranslationRequest($node, 'it');

    // Test other faulty cases.
    $output = $this->doTestNewTranslationRequest($node, 'it', Response::HTTP_CONFLICT);
    $this->assertSame('The "it" resource translation already exists.', $output['errors'][0]['detail']);
    $output = $this->doTestNewTranslationRequest($node, 'it', Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Language' => 'en']);
    $this->assertSame('Translation resource language mismatch: "en" (request metadata) vs "it" (request payload).', $output['errors'][0]['detail']);
    $output = $this->doTestNewTranslationRequest($node, 'en', Response::HTTP_UNPROCESSABLE_ENTITY, [], ['field_test_ut' => $this->randomString()]);
    $this->assertSame('The following fields are not translatable: "field_test_ut".', $output['errors'][0]['detail']);

    // Check that translations cannot be created for non-translatable nodes.
    $node = $this->drupalCreateNode();
    $output = $this->doTestNewTranslationRequest($node, 'it', Response::HTTP_UNPROCESSABLE_ENTITY);
    $this->assertSame('Translation is not enabled for the specified resource.', $output['errors'][0]['detail']);
  }

  /**
   * Tests a new translation request.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be translated.
   * @param string $expected_langcode
   *   The expected response language code.
   * @param int $expected_status
   *   (optional) The expected HTTP status. Defaults to "Created".
   * @param array $options
   *   (optional) Additional request options. Defaults to none.
   * @param array $attributes
   *   (optional) Additional request body attributes. Defaults to none.
   *
   * @return array
   *   The parsed JSON response.
   */
  protected function doTestNewTranslationRequest(NodeInterface $node, string $expected_langcode, int $expected_status = Response::HTTP_CREATED, array $options = [], array $attributes = []): array {
    $uuid = $node->uuid();
    $type = $node->bundle();

    $body = [
      'data' => [
        'type' => "node--$type",
        'id' => $uuid,
        'attributes' => $attributes + [
          'langcode' => $expected_langcode,
          'title' => $this->randomString(),
        ],
      ],
    ];

    if ($node->hasField('field_test')) {
      $body['data']['attributes']['field_test'] = $this->randomString();
    }

    if (!isset($options['Content-Language']) && !isset($options['headers'])) {
      $options['Content-Language'] = $expected_langcode;
    }
    if (!$options['Content-Language']) {
      unset($options['Content-Language']);
    }

    $individual_url = Url::fromRoute("jsonapi.node--$type.individual", [
      'entity' => $uuid,
    ]);
    return $this->doTestPostRequest($individual_url, $body, $expected_status, $options, $expected_langcode);
  }

  /**
   * Tests a new translation request.
   *
   * @param string $langcode
   *   The node default language code.
   * @param int $expected_status
   *   (optional) The expected HTTP status. Defaults to "Created".
   * @param array $options
   *   (optional) Additional request options. Defaults to none.
   * @param string|null $expected_langcode
   *   (optional) The expected response language code. Defaults to none.
   *
   * @return array
   *   The parsed JSON response.
   */
  protected function doTestNewNodeRequest(string $langcode, ?int $expected_status = Response::HTTP_CREATED, array $options = [], ?string $expected_langcode = NULL): array {
    $body = [
      'data' => [
        'type' => 'node--article',
        'attributes' => [
          'langcode' => $langcode,
          'title' => $this->randomString(),
          'default_langcode' => '1',
          'field_test' => $this->randomString(),
          'field_test_ut' => $this->randomString(),
        ],
      ],
    ];

    if (!$langcode) {
      unset($body['data']['attributes']['langcode']);
    }

    $collection_url = Url::fromRoute('jsonapi.node--article.collection.post');
    return $this->doTestPostRequest($collection_url, $body, $expected_status, $options, $expected_langcode ?: $langcode);
  }

  /**
   * Tests a generic POST request.
   *
   * @param \Drupal\Core\Url $url
   *   The request URL.
   * @param array $body
   *   The request body.
   * @param int $expected_status
   *   (optional) The expected HTTP status. Defaults to "Created".
   * @param array $options
   *   (optional) Additional request options. Defaults to none.
   * @param string|null $expected_langcode
   *   (optional) The expected response language code. Defaults to none.
   *
   * @return array
   *   The parsed JSON response.
   */
  protected function doTestPostRequest(Url $url, array $body, ?int $expected_status = Response::HTTP_CREATED, array $options = [], ?string $expected_langcode = NULL): array {
    $headers = $options;
    if (isset($options['headers'])) {
      $headers = $options['headers'];
      unset($options['headers']);
    }

    $response = $this->request('POST', $url, [
      'body' => Json::encode($body),
      'auth' => [$this->user->getAccountName(), $this->user->pass_raw],
      'headers' => ['Content-Type' => 'application/vnd.api+json'] + $headers,
      'options' => $options !== $headers ? $options : [],
    ]);

    $output = $this->getDocumentFromResponse($response, $expected_status === Response::HTTP_CREATED);

    if ($expected_status === Response::HTTP_CREATED) {
      $this->assertArrayHasKey('langcode', $output['data']['attributes']);
      $this->assertSame($expected_langcode, $output['data']['attributes']['langcode'] ?? '');
    }
    $this->assertEquals($expected_status, $response->getStatusCode());

    return $output;
  }

  /**
   * Tests the PATCH method.
   *
   * @covers ::patchIndividual
   */
  public function testPatch(): void {
    // Create a non-translatable node and verify that PATCH-ing still works as
    // expected.
    $node = $this->drupalCreateNode();
    $this->doTestPatchRequest($node);

    // Test that a translatable entity can be PATCH-ed without specifying a
    // language.
    $node = $this->createTranslatableNode('fr');
    $this->doTestPatchRequest($node);

    // Test that the default translation can be patched without specifying a
    // language, when a non-default translation exists, and that the latter is
    // not affected.
    $expected_translation_values = $this->createTranslation($node, 'it')->toArray();
    $output = $this->doTestPatchRequest($node);
    $this->assertSame('fr', $output['data']['attributes']['langcode']);

    $node = $this->reloadNode($node->id());
    $translation_values = $node->getTranslation('it')->toArray();
    $this->assertSame($expected_translation_values['title'], $translation_values['title']);
    $this->assertSame($expected_translation_values['field_test'], $translation_values['field_test']);

    // Test that an entity translation can be PATCH-ed as well.
    $this->doTestPatchRequest($node, 'it');

    // Test that a missing entity translation cannot be PATCH-ed and returns a
    // 404 status.
    $this->doTestPatchRequest($node, 'en', [], Response::HTTP_NOT_FOUND);

    // Test that it is possible to use a query string parameter rather than the
    // "Content-Language" header to specify the translation language.
    $options = [
      'query' => ['langCode' => 'it'],
      'attributes' => ['langcode' => 'it'],
    ];
    $this->doTestPatchRequest($node, NULL, $options);

    // Test that changing an untranslatable field value is only possible when
    // PATCH-ing the default translation.
    $ut_value = $this->randomString();
    $options = [
      'attributes' => ['field_test_ut' => $ut_value],
    ];
    $output = $this->doTestPatchRequest($node, 'fr', $options);
    $this->assertSame($ut_value, $output['data']['attributes']['field_test_ut']);
    $this->doTestPatchRequest($node, 'it', $options, Response::HTTP_UNPROCESSABLE_ENTITY);

    // Test that if a language query string parameter is provided, it needs to
    // match the "Content-Language" header and "langcode" attribute.
    $options = [
      'attributes' => ['langcode' => 'fr'],
    ];
    $this->doTestPatchRequest($node, 'it', $options, Response::HTTP_UNPROCESSABLE_ENTITY);
    $options = [
      'query' => ['langCode' => 'fr'],
    ];
    $this->doTestPatchRequest($node, 'it', $options, Response::HTTP_UNPROCESSABLE_ENTITY);
  }

  /**
   * Tests a generic PATCH request.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be modified.
   * @param string|null $expected_langcode
   *   (optional) The expected response language code. Defaults to none.
   * @param array $options
   *   (optional) Additional request options. Defaults to none.
   * @param int $expected_status
   *   (optional) The expected HTTP status. Defaults to "OK".
   *
   * @return array
   *   The parsed JSON response.
   */
  protected function doTestPatchRequest(NodeInterface &$node, ?string $expected_langcode = NULL, array $options = [], int $expected_status = Response::HTTP_OK): array {
    $uuid = $node->uuid();

    $default_attributes = [
      'title' => $this->randomString(),
    ];
    if (isset($expected_langcode)) {
      $default_attributes['langcode'] = $expected_langcode;
    }
    if ($node->hasField('field_test')) {
      $default_attributes['field_test'] = $this->randomString();
    }
    $attributes = ($options['attributes'] ?? []) + $default_attributes;
    unset($options['attributes']);

    $body = [
      'data' => [
        'id' => $uuid,
        'type' => 'node--' . $node->bundle(),
        'attributes' => $attributes,
      ],
    ];

    if (!isset($options['headers'])) {
      $options['headers'] = [];
    }
    if (isset($expected_langcode)) {
      $options['headers'] += ['Content-Language' => $expected_langcode];
    }

    $headers = $options;
    if (isset($options['headers'])) {
      $headers = $options['headers'];
      unset($options['headers']);
    }

    $individual_url = Url::fromRoute('jsonapi.node--article.individual', [
      'entity' => $uuid,
    ]);
    $response = $this->request('PATCH', $individual_url, [
      'body' => Json::encode($body),
      'auth' => [$this->user->getAccountName(), $this->user->pass_raw],
      'headers' => ['Content-Type' => 'application/vnd.api+json'] + $headers,
    ] + $options);
    $status_code = $response->getStatusCode();

    $output = $this->getDocumentFromResponse($response, $status_code === Response::HTTP_OK);
    if ($status_code === Response::HTTP_OK) {
      $this->assertEquals($attributes['title'], $output['data']['attributes']['title']);
      if ($expected_langcode) {
        $this->assertSame($expected_langcode, $output['data']['attributes']['langcode']);
      }
      $node = $this->reloadNode($node->id());
      if ($node->hasField('field_test')) {
        $this->assertEquals($attributes['field_test'], $output['data']['attributes']['field_test']);
      }
    }
    $this->assertEquals($expected_status, $status_code);

    return $output;
  }

  /**
   * Tests the DELETE method.
   *
   * @covers ::deleteIndividual
   * @covers ::deleteIndividualOrTranslation
   */
  public function testDelete(): void {
    // Create a non-translatable node and verify that DELETE-ing still works as
    // expected.
    $node = $this->drupalCreateNode();
    $this->doTestDeleteRequest($node);
    $this->assertNull($node);

    // Create a translatable node and verify that DELETE-ing still works as
    // expected.
    $node = $this->createTranslatableNode('fr');
    $this->doTestDeleteRequest($node);
    $this->assertNull($node);

    // Test that it is not possible to DELETE the default translation.
    $node = $this->createTranslatableNode('fr');
    $this->doTestDeleteRequest($node, 'fr', [], Response::HTTP_BAD_REQUEST);
    $this->assertNotNull($node);

    // Test that attempting to DELETE a non-existing translation yields a 404.
    $this->createTranslation($node, 'it');
    $this->doTestDeleteRequest($node, 'en', [], Response::HTTP_NOT_FOUND);
    $this->assertNotNull($node);

    // Test that it is possible to DELETE a non-default translation.
    $this->doTestDeleteRequest($node, 'it');
    $this->assertNotNull($node);
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertFalse($node->hasTranslation('it'));

    // Test that if a language query string parameter is provided, it needs to
    // match the "Content-Language" header.
    $options = [
      'query' => ['langCode' => 'fr'],
    ];
    $this->doTestDeleteRequest($node, 'it', $options, Response::HTTP_UNPROCESSABLE_ENTITY);
    $this->assertNotNull($node);
  }

  /**
   * Tests a generic DELETE request.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be processed.
   * @param string|null $expected_langcode
   *   (optional) The expected response language code. Defaults to none.
   * @param array $options
   *   (optional) Additional request options. Defaults to none.
   * @param int $expected_status
   *   (optional) The expected HTTP status. Defaults to "No Content".
   *
   * @return array
   *   The parsed JSON response.
   */
  protected function doTestDeleteRequest(NodeInterface &$node, ?string $expected_langcode = NULL, array $options = [], int $expected_status = Response::HTTP_NO_CONTENT): array {
    $uuid = $node->uuid();

    $headers = $options;
    if (isset($options['headers'])) {
      $headers = $options['headers'];
      unset($options['headers']);
    }
    if (isset($expected_langcode)) {
      $headers += ['Content-Language' => $expected_langcode];
    }

    $individual_url = Url::fromRoute('jsonapi.node--article.individual', [
      'entity' => $uuid,
    ]);
    $response = $this->request('DELETE', $individual_url, [
      'auth' => [$this->user->getAccountName(), $this->user->pass_raw],
      'headers' => ['Content-Type' => 'application/vnd.api+json'] + $headers,
    ] + $options);
    $body = $response->getBody()->__toString();
    $output = $body ? Json::decode($body) : [];

    $status_code = $response->getStatusCode();
    $this->assertEquals($expected_status, $status_code);

    $node = $this->reloadNode($node->id());

    return $output;
  }

  /**
   * Creates a new translatable node.
   *
   * @param string $langcode
   *   The default language.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function createTranslatableNode(string $langcode): NodeInterface {
    $this->createDefaultContent(1, 1, FALSE, FALSE, static::IS_NOT_MULTILINGUAL, FALSE, $langcode);
    $node = end($this->nodes);
    assert($node instanceof NodeInterface);

    $node->set('field_test', $this->randomString());
    $node->set('field_test_ut', $this->randomString());
    $node->save();

    return $node;
  }

  /**
   * Creates a new translation.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The source translation.
   * @param string $langcode
   *   The translation language.
   * @param array $values
   *   (optional) The translation values. Defaults to random ones.
   *
   * @return \Drupal\node\NodeInterface
   *   The newly created translation.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createTranslation(NodeInterface $node, string $langcode, array $values = []): NodeInterface {
    $translation = $node->addTranslation($langcode, $values + [
      'title' => $langcode . ' - ' . $node->getTitle(),
      'field_test' => $langcode . ' - ' . $node->get('field_test')->value,
    ]);
    $translation->setNewRevision(FALSE);
    $translation->save();
    return $translation;
  }

  /**
   * Reloads the specified node.
   *
   * @param string|int $id
   *   A node ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The reloaded node.
   */
  protected function reloadNode(string|int $id): ?NodeInterface {
    $node = NULL;
    try {
      $node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadUnchanged($id);
      assert(!isset($node) || $node instanceof NodeInterface);
    }
    catch (PluginException) {
    }
    return $node;
  }

}
