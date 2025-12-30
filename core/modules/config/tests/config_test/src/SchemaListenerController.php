<?php

declare(strict_types=1);

namespace Drupal\config_test;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\SchemaIncompleteException;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for testing \Drupal\Core\Config\Development\ConfigSchemaChecker.
 */
class SchemaListenerController extends ControllerBase {

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Tests the BrowserTestBase tests can use strict schema checking.
   */
  public function test() {
    try {
      $this->configFactory->getEditable('config_schema_test.schemaless')->set('foo', 'bar')->save();
    }
    catch (SchemaIncompleteException $e) {
      return [
        '#markup' => $e->getMessage(),
      ];
    }
  }

}
