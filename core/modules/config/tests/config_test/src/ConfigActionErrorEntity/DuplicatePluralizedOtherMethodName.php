<?php

declare(strict_types=1);

namespace Drupal\config_test\ConfigActionErrorEntity;

use Drupal\config_test\Entity\ConfigTest;
use Drupal\Core\Config\Action\Attribute\ActionMethod;

/**
 * Test entity class.
 */
class DuplicatePluralizedOtherMethodName extends ConfigTest {

  /**
   * Provides an action method that can be pluralized.
   */
  #[ActionMethod(pluralize: 'testMethod2')]
  public function testMethod() {
  }

  /**
   * Provides the plural action for testMethod().
   */
  #[ActionMethod()]
  public function testMethod2() {
  }

}
