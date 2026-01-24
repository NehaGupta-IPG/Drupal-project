<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi\Functional;

use Drupal\Component\Serialization\Json;

/**
 * JSON:API integration test for HOOK_entity_query_alter().
 *
 * @group jsonapi
 *
 * @internal
 */
class JsonApiFunctionalEntityQueryAlterTest extends JsonApiFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi_test_entity_query_alter',
    'page_cache',
  ];

  /**
   * Test that the entity query alterer works as expected.
   */
  public function testEntityQueryAlter(): void {
    $this->createDefaultContent(2, 2, TRUE, TRUE, static::IS_NOT_MULTILINGUAL, FALSE);
    // A HEAD request allows a client to verify that JSON:API is installed
    // and no major error is introduced by the query alterer.
    $this->httpClient->request('HEAD', $this->buildUrl('/jsonapi/node/article'));
    $this->assertSession()->statusCodeEquals(200);

    // A GET request with no custom_nid query parameter should return all nodes.
    $collection_output = Json::decode($this->drupalGet('/jsonapi/node/article'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(2, $collection_output['data']);

    // A GET request with custom_nid = 1 should return only the node with nid 1.
    $collection_output = Json::decode($this->drupalGet('/jsonapi/node/article', [
      'query' => [
        'custom_nid' => 1,
      ],
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(1, $collection_output['data']);
    $this->assertEquals(1, $collection_output['data'][0]['attributes']['drupal_internal__nid']);

    // A GET request with custom_nid = 2 should return only the node with nid 2.
    $collection_output = Json::decode($this->drupalGet('/jsonapi/node/article', [
      'query' => [
        'custom_nid' => 2,
      ],
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertCount(1, $collection_output['data']);
    $this->assertEquals(2, $collection_output['data'][0]['attributes']['drupal_internal__nid']);
  }

}
