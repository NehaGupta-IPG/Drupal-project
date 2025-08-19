<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Mailer\EmailDefinition;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests resolving email controller arguments.
 *
 * @group mailer
 * @coversDefaultClass \Drupal\Core\Mailer\EmailRenderer
 */
class EmailControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * Test parameters available to controller methods by default.
   *
   * @covers ::build
   */
  public function testMinimal(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::minimal',
      ],
    ]);

    $email = $emailRenderer->render($definition, [], 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::minimal(id: mailer.test, module: mailer, key: test, langcode: en)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * Test params cannot override the arguments derived from the definition.
   *
   * @covers ::build
   */
  public function testParamsCannotOverrideDefinition(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::minimal',
      ],
    ]);

    $params = ['id' => 'mailer.bogus', 'module' => 'bogus', 'key' => 'bogus', 'langcode' => 'es'];
    $email = $emailRenderer->render($definition, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::minimal(id: mailer.test, module: mailer, key: test, langcode: en)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * Test argument resolution for controller with scalar arguments.
   *
   * @covers ::build
   */
  public function testScalars(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::scalars',
      ],
    ]);

    $params = ['text' => 'emotional support alpaca', 'number' => 42, 'status' => FALSE];
    $email = $emailRenderer->render($definition, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::scalars(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * Test default argument resolution for controller with scalars.
   *
   * @covers ::build
   */
  public function testScalarsFromDefaults(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::scalars',
        'text' => 'emotional support alpaca',
        'number' => 42,
        'status' => FALSE,
      ],
    ]);

    $email = $emailRenderer->render($definition, [], 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::scalars(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * Test mixed argument resolution for controller with scalars.
   *
   * @covers ::build
   */
  public function testScalarsMixedDefaults(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::scalars',
        'text' => 'from defaults',
        'number' => 42,
        'status' => TRUE,
      ],
    ]);

    $params = ['text' => 'emotional support alpaca', 'status' => FALSE];
    $email = $emailRenderer->render($definition, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::scalars(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * Test argument resolution for controller with signature mismatch.
   *
   * @covers ::build
   */
  public function testScalarsMissingArguments(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::scalars',
      ],
    ]);

    $params = ['text' => 'emotional support alpaca', 'status' => FALSE];
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Callable "Drupal\mailer_email_test\EmailTestController::scalars" requires a value for the "$number" argument.');
    $emailRenderer->render($definition, $params, 'en');
  }

  /**
   * Test argument resolution for controller with scalar arguments.
   *
   * @covers ::build
   */
  public function testEntity(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::entity',
      ],
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
    $params = ['entity' => $entity];
    $email = $emailRenderer->render($definition, $params, 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::entity(id: 1, bundle: alpaca, type: animal)', $email->getTextBody());
    $this->assertSame('Entity Test Email', $email->getSubject());
  }

  /**
   * Test minimum arguments for generic controller.
   *
   * @covers ::build
   */
  public function testGenericMinimal(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::generic',
      ],
    ]);

    $email = $emailRenderer->render($definition, [], 'en');
    $this->assertSame('["_controller","id","key","langcode","module","params"]', $email->getTextBody());
    $this->assertSame('Generic Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * Test mixed argument resolution for generic controller.
   *
   * @covers ::build
   */
  public function testGenericMixedDefaults(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::generic',
        'text' => 'emotional support alpaca',
      ],
    ]);

    $params = ['number' => 42, 'status' => TRUE];
    $email = $emailRenderer->render($definition, $params, 'en');
    $this->assertSame('["_controller","id","key","langcode","module","number","params","status","text"]', $email->getTextBody());
    $this->assertSame('Generic Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * Test exception on missing controller.
   *
   * @covers ::build
   */
  public function testMissingController(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::missing',
      ],
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The controller for Email "mailer.test" is not callable.');
    $emailRenderer->render($definition, [], 'en');
  }

}
