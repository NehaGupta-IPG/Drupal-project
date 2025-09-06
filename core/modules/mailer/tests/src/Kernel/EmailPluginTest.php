<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Mailer\EmailRenderer;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mailer_email_test\EntityTestEmail;
use Drupal\mailer_email_test\GenericTestEmail;
use Drupal\mailer_email_test\MinimalTestEmail;
use Drupal\mailer_email_test\ScalarsTestEmail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests resolving email plugin arguments.
 */
#[Group('mailer')]
#[CoversClass(EmailRenderer::class)]
class EmailPluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * Test parameters available to email plugin methods by default.
   */
  public function testMinimal(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = new MinimalTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => MinimalTestEmail::class,
    ]);

    $email = $emailRenderer->render($instance, MinimalTestEmail::createParams(), 'en');
    $this->assertSame('Drupal\mailer_email_test\MinimalTestEmail::getBody(id: mailer.test, module: mailer, key: test, langcode: en)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * Test params cannot override the arguments derived from the definition.
   */
  public function testParamsCannotOverrideDefinition(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = new MinimalTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => MinimalTestEmail::class,
    ]);

    $params = ['id' => 'mailer.bogus', 'module' => 'bogus', 'key' => 'bogus', 'langcode' => 'es'];
    $email = $emailRenderer->render($instance, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\MinimalTestEmail::getBody(id: mailer.test, module: mailer, key: test, langcode: en)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * Test argument resolution for email plugin methods with scalar arguments.
   */
  public function testScalars(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = new ScalarsTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => ScalarsTestEmail::class,
    ]);

    $params = ScalarsTestEmail::createParams('emotional support alpaca', 42, FALSE);
    $email = $emailRenderer->render($instance, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\ScalarsTestEmail::getBody(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * Test argument resolution for email plugin methods with object arguments.
   */
  public function testEntity(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = new EntityTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => EntityTestEmail::class,
    ]);

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
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $instance = new GenericTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => GenericTestEmail::class,
    ]);

    $email = $emailRenderer->render($instance, [], 'en');
    $this->assertSame('["id","key","langcode","module","params"]', $email->getTextBody());
    $this->assertSame('Generic Test Email (mailer.test)', $email->getSubject());
  }

}
