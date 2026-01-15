<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Mailer\DefaultEmail;
use Drupal\Core\Mailer\EmailManager;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests resolving email plugin arguments.
 */
#[CoversClass(DefaultEmail::class)]
#[Group('mailer')]
#[RunTestsInSeparateProcesses]
class DefaultEmailTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'mailer', 'system', 'mailer_email_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('filter');
  }

  /**
   * Test default plugin with subject only.
   */
  public function testEmptyBody(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = $emailManager->createInstance('mailer_email_test.default');
    $params = DefaultEmail::createParams(new TranslatableMarkup('Test subject'));
    $email = $emailRenderer->render($instance, $params, 'en');
    $this->assertSame('Test subject', $email->getSubject());
  }

  /**
   * Test default plugin with single line of text.
   */
  public function testSingleLine(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = $emailManager->createInstance('mailer_email_test.default');
    $params = DefaultEmail::createParams(
      new TranslatableMarkup('Test subject'),
      new TranslatableMarkup('Single line of text')
    );
    $email = $emailRenderer->render($instance, $params, 'en');

    $lines = [
      '<div class="email email--mailer_email_test--default" lang="en">',
      '  <p>Single line of text</p>',
      '',
      '</div>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $email->getHtmlBody());
    $this->assertSame('Test subject', $email->getSubject());
  }

  /**
   * Test default plugin with multiple lines of text.
   */
  public function testMultiline(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = $emailManager->createInstance('mailer_email_test.default');
    $params = DefaultEmail::createParams(new TranslatableMarkup('Test subject'), [
      new TranslatableMarkup('First line of text'),
      new TranslatableMarkup('Second line of text'),
    ]);
    $email = $emailRenderer->render($instance, $params, 'en');

    $lines = [
      '<div class="email email--mailer_email_test--default" lang="en">',
      '  <p>First line of text</p>',
      '<p>Second line of text</p>',
      '',
      '</div>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $email->getHtmlBody());
    $this->assertSame('Test subject', $email->getSubject());
  }

}
