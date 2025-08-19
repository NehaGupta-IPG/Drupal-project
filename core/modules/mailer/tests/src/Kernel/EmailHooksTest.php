<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Mailer\EmailDefinition;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;

/**
 * Tests alter hooks for emails.
 *
 * @group mailer
 * @coversDefaultClass \Drupal\Core\Mailer\EmailRenderer
 */
class EmailHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * @covers ::render
   */
  public function testAlterAttributes(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_params_alter.set_attributes', [
      'text' => 'emotional support alpaca',
      'number' => 42,
      'status' => FALSE,
    ]);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::scalars',
        'text' => 'from defaults',
      ],
    ]);

    $email = $emailRenderer->render($definition, ['text' => 'from params'], 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::scalars(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * @covers ::render
   */
  public function testParamsAlter(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_params_alter.set_attributes', [
      'text' => 'emotional support alpaca',
      'number' => 42,
      'status' => FALSE,
    ]);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::scalars',
      ],
    ]);

    $email = $emailRenderer->render($definition, [], 'en');
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::scalars(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * @covers ::render
   */
  public function testControllerAlter(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_params_alter.set_controller', 'Drupal\mailer_email_test\EmailTestController::minimal');

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
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::minimal(id: mailer.test, module: mailer, key: test, langcode: en)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * @covers ::render
   */
  public function testLangcodeAlter(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_params_alter.set_langcode', 'fr');

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
    $this->assertSame('Drupal\mailer_email_test\EmailTestController::minimal(id: mailer.test, module: mailer, key: test, langcode: fr)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * @covers ::render
   */
  public function testBuildAlter(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_build_alter.set_elements', [
      'footer' => [
        '#weight' => 999,
        '#markup' => '<p><small>ACME Inc.</small></p>',
      ],
    ]);

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
      '  <p><em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span></p><p><small>ACME Inc.</small></p>',
      '</div>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $email->getHtmlBody());
    $this->assertSame('HTML Email (mailer.test)', $email->getSubject());
  }

  /**
   * @covers ::render
   */
  public function testEmailAlter(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_alter.set_tags', ['mailer.test']);
    \Drupal::state()->set('email_alter.set_metadata', [
      'X-Drupal-Module' => 'mailer',
      'X-Drupal-Key' => 'test',
    ]);

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
    $headers = [...$email->getHeaders()->all()];
    $this->assertContainsEquals(new TagHeader('mailer.test'), $headers);
    $this->assertContainsEquals(new MetadataHeader('X-Drupal-Module', 'mailer'), $headers);
    $this->assertContainsEquals(new MetadataHeader('X-Drupal-Key', 'test'), $headers);
  }

  /**
   * @covers ::render
   */
  public function testEmailBuildAlterMetadata(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_build_alter.set_elements', [
      '#attached' => [
        'library' => [
          'core/drupal.message',
        ],
        'drupalSettings' => [
          'test' => 'setting',
        ],
      ],
    ]);
    \Drupal::state()->set('email_alter.capture_render_metadata', TRUE);

    $definition = new EmailDefinition([
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'key' => 'test',
      'defaults' => [
        EmailDefinition::CONTROLLER_NAME => 'Drupal\mailer_email_test\EmailTestController::html',
      ],
    ]);

    $emailRenderer->render($definition, [], 'en');
    $metadata = \Drupal::state()->get('email_alter.render_metadata');
    assert($metadata instanceof BubbleableMetadata);
    $this->assertEquals([
      'library' => [
        'core/drupal.message',
      ],
      'drupalSettings' => [
        'test' => 'setting',
      ],
    ], $metadata->getAttachments());
  }

}
