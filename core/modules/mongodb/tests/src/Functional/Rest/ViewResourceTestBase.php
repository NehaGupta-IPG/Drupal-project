<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Functional\Rest;

use Drupal\mongodb\modules\views\View;
use Drupal\Tests\views\Functional\Rest\ViewResourceTestBase as CoreViewResourceTestBase;

/**
 * Testing the MongoDB override of the views entity.
 *
 * @group rest
 */
abstract class ViewResourceTestBase extends CoreViewResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mongodb', 'views', 'views_ui'];

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $view = View::create([
      'id' => 'test_rest',
      'label' => 'Test REST',
    ]);
    $view->save();
    return $view;
  }

}
