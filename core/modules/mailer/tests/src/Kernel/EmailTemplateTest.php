<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Mailer\EmailDefinition;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests rendering email templates.
 *
 * @group mailer
 * @coversDefaultClass \Drupal\Core\Mailer\EmailRenderer
 */
class EmailTemplateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * Test default email template.
   *
   * @covers ::render
   */
  public function testDefaultTemplate(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::html',
      ],
    ]);

    $email = $emailRenderer->render($definition, [], 'en');
    $lines = [
      '<div class="email email--mailer--test" lang="en">',
      '  <em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span>',
      '</div>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $email->getHtmlBody());
    $this->assertSame('HTML Email (mailer.test)', $email->getSubject());
  }

  /**
   * Test template rendering without controller.
   *
   * @covers ::render
   */
  public function testCustomTemplate(): void {
    \Drupal::service(ThemeInstallerInterface::class)->install(['mailer_theme_test']);
    $this->config('system.theme')->set('default', 'mailer_theme_test')->save();

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.custom-template',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'custom_template',
    ]);

    $params = ['text' => 'emotional support alpaca', 'status' => TRUE];
    $email = $emailRenderer->render($definition, $params, 'en');
    $lines = [
      '<div class="status-enabled" lang="en">',
      '  <p>emotional support alpaca</p>',
      '</div>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $email->getHtmlBody());
  }

}
