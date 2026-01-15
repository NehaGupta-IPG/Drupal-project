<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Mailer\EmailRenderer;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mailer_email_test\HtmlTestEmail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests rendering email templates.
 */
#[CoversClass(EmailRenderer::class)]
#[Group('mailer')]
#[RunTestsInSeparateProcesses]
class EmailTemplateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * Test default email template.
   */
  public function testDefaultTemplate(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = new HtmlTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => HtmlTestEmail::class,
    ]);

    $email = $emailRenderer->render($instance, HtmlTestEmail::createParams(), 'en');
    $lines = [
      '<div class="email email--mailer--test" lang="en">',
      '  <p><em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span></p>',
      '</div>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $email->getHtmlBody());
    $this->assertSame('HTML Email (mailer.test)', $email->getSubject());
  }

}
