<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Mailer\EmailFactory;
use Drupal\Core\Mailer\EmailFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mime\Email;

/**
 * Test email factory service.
 */
#[Group('mailer')]
#[CoversClass(EmailFactory::class)]
class EmailFactoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'mailer_email_test'];

  /**
   * Test the email factory create method.
   */
  public function testEmailFactoryService(): void {
    $factory = \Drupal::service(EmailFactoryInterface::class);
    assert($factory instanceof EmailFactoryInterface);
    $email = $factory->create('mailer_email_test.default')
      ->langcode('en')
      ->build();
    $this->assertInstanceOf(Email::class, $email);
  }

}
