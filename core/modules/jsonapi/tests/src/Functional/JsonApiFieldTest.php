<?php

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Makes assertions about the JSON:API behavior for certain fields.
 *
 * @group jsonapi
 */
class JsonApiFieldTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'entity_test',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    FieldStorageConfig::create([
      'field_name' => 'links',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'settings' => [],
      'cardinality' => 1,
    ])->save();
    $field_config = FieldConfig::create([
      'field_name' => 'links',
      'label' => 'Links',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'required' => FALSE,
      'settings' => [],
      'description' => '',
    ]);
    $field_config->save();
  }

  /**
   * Tests getting a resource with a field called links.
   */
  public function testLinksField(): void {
    $entity = EntityTest::create([
      'name' => 'Foo',
    ]);
    $entity->save();

    $url = Url::fromRoute('jsonapi.entity_test--entity_test.individual', ['entity' => $entity->uuid()]);
    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
    ]));

    $response = $this->drupalGet($url, [], ['Accept' => 'application/vnd.api+json']);
    dump("Status code is " . $this->getSession()->getStatusCode());
    dump(Json::decode($response));
    $this->assertSession()->statusCodeEquals(200);

    $body = Json::decode($response);
    $this->assertNull($body['data']['attributes']['links']);
  }

}
