<?php

declare(strict_types=1);

namespace Drupal\Tests\contact\Unit;

use Drupal\contact\Hook\ContactHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for Contact hooks.
 */
#[Group('contact')]
class ContactTest extends UnitTestCase {

  /**
   * Test contact_local_tasks_render_alter doesn't throw warnings.
   */
  public function testLocalTasksRenderAlter(): void {
    require_once $this->root . '/core/modules/contact/contact.module';
    $data = [];
    $contactLocalTasksRenderAlter = new ContactHooks();
    $contactLocalTasksRenderAlter->localTasksRenderAlter($data, 'entity.user.canonical');
    $this->assertTrue(TRUE, 'No warning thrown');
  }

}
