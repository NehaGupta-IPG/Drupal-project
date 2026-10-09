<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Mailer\EmailManager;
use Drupal\Core\Mailer\EmailPluginDefault;
use Drupal\Core\Mailer\EmailPluginInterface;
use Drupal\Core\Test\MailerCaptureTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Mime\Address;

/**
 * Tests resolving email plugin arguments.
 */
#[CoversClass(EmailPluginDefault::class)]
#[Group('mailer')]
#[RunTestsInSeparateProcesses]
class EmailPluginDefaultTest extends KernelTestBase {

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
   * Test default plugin with html body only.
   */
  public function testHtmlBody(): void {
    $emailManager = \Drupal::service(EmailManager::class);
    assert($emailManager instanceof EmailManager);

    $email = $emailManager->createInstance('mailer_email_test.with_html_body');
    assert($email instanceof EmailPluginInterface);
    $email
      ->subject('Email with html body')
      ->setParameters([
        'name' => 'Jane',
        'bill_amount' => '$9',
      ]);
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $lines = [
      '<p>Hello Jane,</p>',
      '<p>Your bill for this month is <strong>$9</strong>.</p>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $sentEmail->getHtmlBody());
    $this->assertSame('Email with html body', $sentEmail->getSubject());
  }

  /**
   * Test default plugin with text body only.
   */
  public function testTextBody(): void {
    $emailManager = \Drupal::service(EmailManager::class);
    assert($emailManager instanceof EmailManager);

    $email = $emailManager->createInstance('mailer_email_test.with_text_body');
    assert($email instanceof EmailPluginInterface);
    $email
      ->subject('Email with text body')
      ->setParameters([
        'name' => 'Zoë',
        'bill_amount' => '€99',
      ]);
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $lines = [
      'Hello Zoë,',
      '',
      'Your bill for this month is €99.',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $sentEmail->getTextBody());
    $this->assertSame('Email with text body', $sentEmail->getSubject());
  }

  /**
   * Test default plugin with both bodies.
   */
  public function testBothBodies(): void {
    $emailManager = \Drupal::service(EmailManager::class);
    assert($emailManager instanceof EmailManager);

    $email = $emailManager->createInstance('mailer_email_test.with_both_body');
    assert($email instanceof EmailPluginInterface);
    $email
      ->subject('Email with both body')
      ->setParameters([
        'name' => '佐藤',
        'bill_amount' => '¥500',
      ]);
    $this->assertTrue($emailManager->send($email->to('foobar@example.com')));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $htmlLines = [
      '<p>Hello 佐藤,</p>',
      '<p>Your bill for this month is <strong>¥500</strong>.</p>',
      '',
    ];
    $textLines = [
      'Hello 佐藤,',
      '',
      'Your bill for this month is ¥500.',
      '',
    ];
    $this->assertSame(implode("\n", $htmlLines), $sentEmail->getHtmlBody());
    $this->assertSame(implode("\n", $textLines), $sentEmail->getTextBody());
    $this->assertSame('Email with both body', $sentEmail->getSubject());
  }

  /**
   * Test default plugin with headers from YAML declaration.
   */
  public function testDefaultHeadersFromYamlDeclaration(): void {
    $emailManager = \Drupal::service(EmailManager::class);
    assert($emailManager instanceof EmailManager);

    $email = $emailManager->createInstance('mailer_email_test.with_default_headers');
    assert($email instanceof EmailPluginInterface);

    $this->assertTrue($emailManager->send($email));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $this->assertSame('Default subject from YAML', $sentEmail->getSubject());

    $expectedTo = [
      new Address('simple@example.com'),
      new Address('jane.smith@example.org', 'Jane Smith'),
      new Address('user.name+tag+sorting@example.com'),
    ];
    $expectedCc = [
      new Address('admin@localhost', 'Admin from YAML'),
    ];
    $expectedBcc = [
      new Address('user-@example.org'),
    ];
    $this->assertEquals($expectedTo, $sentEmail->getTo());
    $this->assertEquals($expectedCc, $sentEmail->getCc());
    $this->assertEquals($expectedBcc, $sentEmail->getBcc());
  }

  /**
   * Test default plugin with headers from YAML declaration overridden.
   */
  public function testDefaultHeadersFromYamlOverridden(): void {
    $emailManager = \Drupal::service(EmailManager::class);
    assert($emailManager instanceof EmailManager);

    $email = $emailManager->createInstance('mailer_email_test.with_default_headers');
    assert($email instanceof EmailPluginInterface);

    // Override subject, leave alone to, replace cc and clear bcc.
    $email
      ->subject('Subject from call site')
      ->cc('Admin override <admin@localhost>')
      ->bcc();

    $this->assertTrue($emailManager->send($email));

    $sentEmails = $this->getEmails();
    $this->assertCount(1, $sentEmails);

    $sentEmail = $sentEmails[0];
    $this->assertSame('Subject from call site', $sentEmail->getSubject());

    $expectedTo = [
      new Address('simple@example.com'),
      new Address('jane.smith@example.org', 'Jane Smith'),
      new Address('user.name+tag+sorting@example.com'),
    ];
    $expectedCc = [
      new Address('admin@localhost', 'Admin override'),
    ];
    $this->assertEquals($expectedTo, $sentEmail->getTo());
    $this->assertEquals($expectedCc, $sentEmail->getCc());
    $this->assertEquals([], $sentEmail->getBcc());
  }

}
