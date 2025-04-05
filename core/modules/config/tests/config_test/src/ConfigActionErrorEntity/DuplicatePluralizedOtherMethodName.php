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
   * A test action that can be pluralized.
   */
  #[ActionMethod(pluralize: 'testMethod2')]
  public function testMethod() {
  }

  /**
   * The plural action for testMethod().
   */
  #[ActionMethod()]
  public function testMethod2() {
  }

}
