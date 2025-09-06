<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Mailer\EmailManager;
use Drupal\Core\Mailer\EmailRenderer;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mailer_email_test\EntityTestEmail;
use Drupal\mailer_email_test\MinimalTestEmail;
use Drupal\mailer_email_test\ScalarsTestEmail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests resolving email plugin arguments.
 */
#[Group('mailer')]
#[CoversClass(EmailRenderer::class)]
class EmailManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * Test minimal.
   */
  public function testMinimal(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = $emailManager->createInstance('mailer_email_test.minimal');
    $email = $emailRenderer->render($instance, MinimalTestEmail::createParams(), 'en');
    $this->assertSame('Drupal\mailer_email_test\MinimalTestEmail::getBody(id: mailer_email_test.minimal, module: mailer_email_test, key: minimal, langcode: en)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer_email_test.minimal)', $email->getSubject());
  }

  /**
   * Test scalars.
   */
  public function testScalars(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = $emailManager->createInstance('mailer_email_test.scalars');

    $params = ScalarsTestEmail::createParams('emotional support alpaca', 42, FALSE);
    $email = $emailRenderer->render($instance, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\ScalarsTestEmail::getBody(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * Test argument resolution for email plugin with scalar arguments.
   */
  public function testEntity(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = $emailManager->createInstance('mailer_email_test.entity');

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('id')
      ->willReturn(1);
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('alpaca');
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('animal');
    $params = EntityTestEmail::createParams($entity);
    $email = $emailRenderer->render($instance, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\EntityTestEmail::getBody(id: 1, bundle: alpaca, type: animal)', $email->getTextBody());
    $this->assertSame('Entity Test Email', $email->getSubject());
  }

  /**
   * Test minimum arguments for generic email plugin.
   */
  public function testGenericMinimal(): void {
    $emailManager = \Drupal::service('plugin.manager.email');
    assert($emailManager instanceof EmailManager);

    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = $emailManager->createInstance('mailer_email_test.generic');

    $email = $emailRenderer->render($instance, MinimalTestEmail::createParams(), 'en');
    $this->assertSame('["id","key","langcode","module","params"]', $email->getTextBody());
    $this->assertSame('Generic Test Email (mailer_email_test.generic)', $email->getSubject());
  }

}
