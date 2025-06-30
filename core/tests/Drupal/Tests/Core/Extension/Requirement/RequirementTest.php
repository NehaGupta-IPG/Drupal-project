<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Extension\Requirement;

use Drupal\Core\Extension\Requirement\Requirement;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Extension\Requirement\Requirement
 *
 * @group Extension
 */
class RequirementTest extends UnitTestCase {

  /**
   * Tests array access BC layer.
   */
  public function testArrayAccess(): void {
    $requirement = new Requirement(
      title: 'Alice in Wonderland',
      value: 'small',
      description: 'It\'s no use going back to yesterday, because I was a different person then.',
    );

    // Test array access.
    $this->assertEquals('Alice in Wonderland', $requirement['title']);
    $this->assertEquals('It\'s no use going back to yesterday, because I was a different person then.', $requirement['description']);
    $this->assertEquals('small', $requirement['value']);
    $this->assertEquals(RequirementSeverity::OK, $requirement['severity']);
  }

}
