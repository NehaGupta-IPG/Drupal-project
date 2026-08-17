<?php

declare(strict_types=1);

namespace Drupal\csrf_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Just a test controller for test routes.
 */
class TestController {

  /**
   * Just a test method for the test routes.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function testMethod() {
    return new Response('Sometimes it is hard to think of test content!');
  }

  /**
   * Writes configuration.
   */
  #[Route(
    path: '/csrf/unprotected-config-write',
    name: 'csrf_test.unprotected_config_write',
    methods: ['GET'],
    requirements: ['_access' => 'TRUE'],
  )]
  public function writeConfig(): Response {
    \Drupal::configFactory()->getEditable('system.site')->set('name', 'Updated by GET request')->save();
    return new Response('Configuration updated.');
  }

  /**
   * Writes an entity.
   */
  #[Route(
    path: '/csrf/unprotected-entity-write',
    name: 'csrf_test.unprotected_entity_write',
    methods: ['GET'],
    requirements: ['_access' => 'TRUE'],
  )]
  public function writeEntity(): Response {
    \Drupal::entityTypeManager()->getStorage('entity_test')->create(['name' => 'Created by GET request'])->save();
    return new Response('Entity created.');
  }

}
