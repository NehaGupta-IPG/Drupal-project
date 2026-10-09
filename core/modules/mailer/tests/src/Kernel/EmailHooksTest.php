<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Mailer\EmailManager;
use Drupal\Core\Mailer\EmailPluginInterface;
use Drupal\Core\Test\MailerCaptureTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;

/**
 * Tests alter hooks for emails.
 */
#[CoversClass(EmailManager::class)]
#[Group('mailer')]
#[RunTestsInSeparateProcesses]
class EmailHooksTest extends KernelTestBase {

  use MailerCaptureTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'mailer_capture',
    'mailer_email_test',
    'mailer',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('system.site')
      ->set('langcode', 'en')
      ->set('mail', 'site-mail@example.com')
      ->set('name', 'Example Site')
      ->save();
  }

  /**
   * Tests hook_email_pre_build_alter_alter().
   */
  public function testEmailPreBuildAlter(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    \Drupal::state()->set('email_pre_build_alter.set_params', [
      'text' => 'emotional support alpaca',
      'number' => 42,
      'status' => FALSE,
    ]);

    $email = $emailManager->createInstance('mailer_email_test.scalars');
    assert($email instanceof EmailPluginInterface);
    $email->setParameters([
      'text' => 'from params',
      'int' => 0,
      'status' => TRUE,
    ]);
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $this->assertSame('Drupal\mailer_email_test\Plugin\Email\ScalarsTestEmail::textBody(text: emotional support alpaca, number: 42, status: false)', $sentEmail->getTextBody());
    $this->assertSame('Scalar Params Test Email', $sentEmail->getSubject());
  }

  /**
   * Tests hook_email_pre_build_alter_alter().
   */
  public function testLangcodeAlter(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    \Drupal::state()->set('email_pre_build_alter.set_langcode', 'fr');

    $email = $emailManager->createInstance('mailer_email_test.minimal');
    assert($email instanceof EmailPluginInterface);
    $email->langcode('en');
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $this->assertSame('Drupal\mailer_email_test\Plugin\Email\MinimalTestEmail::textBody(id: mailer_email_test.minimal, langcode: fr)', $sentEmail->getTextBody());
    $this->assertSame('Minimal Test Email (mailer_email_test.minimal)', $sentEmail->getSubject());
  }

  /**
   * Tests hook_email_pre_render_alter_alter().
   */
  public function testPreRenderAlter(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    \Drupal::state()->set('email_pre_render_alter.set_elements', [
      'footer' => [
        '#weight' => 999,
        '#markup' => '<p><small>ACME Inc.</small></p>',
      ],
    ]);

    $email = $emailManager->createInstance('mailer_email_test.html');
    assert($email instanceof EmailPluginInterface);
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $lines = [
      '<p><em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span></p><p><small>ACME Inc.</small></p>',
    ];
    $this->assertSame(implode("\n", $lines), $sentEmail->getHtmlBody());
    $this->assertSame('HTML Email (mailer_email_test.html)', $sentEmail->getSubject());
  }

  /**
   * Tests hook_email_pre_send_alter().
   */
  public function testEmailPreSendAlter(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    \Drupal::state()->set('email_pre_send_alter.set_tags', ['mailer.test']);
    \Drupal::state()->set('email_pre_send_alter.set_metadata', [
      'X-Drupal-Module' => 'mailer',
      'X-Drupal-Key' => 'test',
    ]);

    $email = $emailManager->createInstance('mailer_email_test.minimal');
    assert($email instanceof EmailPluginInterface);
    $email->langcode('en');
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $headers = [...$sentEmail->getHeaders()->all()];
    $this->assertContainsEquals(new TagHeader('mailer.test'), $headers);
    $this->assertContainsEquals(new MetadataHeader('X-Drupal-Module', 'mailer'), $headers);
    $this->assertContainsEquals(new MetadataHeader('X-Drupal-Key', 'test'), $headers);
  }

  /**
   * Tests hook_email_alter().
   */
  public function testEmailBuildAlterMetadata(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    \Drupal::state()->set('email_pre_render_alter.set_elements', [
      '#attached' => [
        'library' => [
          'core/drupal.message',
        ],
        'drupalSettings' => [
          'test' => 'setting',
        ],
      ],
    ]);
    \Drupal::state()->set('email_post_render_alter.capture_assets', TRUE);

    $email = $emailManager->createInstance('mailer_email_test.html');
    assert($email instanceof EmailPluginInterface);
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $assets = \Drupal::state()->get('email_post_render_alter.assets');
    assert($assets instanceof AttachedAssets);
    $this->assertEquals(['core/drupal.message'], $assets->getLibraries());
    $this->assertEquals(['test' => 'setting'], $assets->getSettings());
  }

}
