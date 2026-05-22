<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Functional\Rest;

use Drupal\Tests\rest\Functional\AnonResourceTestTrait;

/**
 * Testing the MongoDB override of the views entity.
 *
 * @group rest
 */
class ViewJsonAnonTest extends ViewResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $format = 'json';

  /**
   * {@inheritdoc}
   */
  protected static $mimeType = 'application/json';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}
