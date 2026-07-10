<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Breadcrumb\Breadcrumb.
 */
#[CoversClass(Breadcrumb::class)]
#[Group('Breadcrumb')]
class BreadcrumbTest extends UnitTestCase {

  /**
   * Tests set links.
   */
  public function testSetLinks(): void {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->setLinks([new Link('Home', Url::fromRoute('<front>'))]);
    $links = $breadcrumb->getLinks();
    $this->assertCount(1, $links);
  }

}
