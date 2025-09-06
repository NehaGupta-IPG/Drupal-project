<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Mailer\EmailRenderer;
use Drupal\Core\Mailer\EmailRendererInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mailer_email_test\HtmlTestEmail;
use Drupal\mailer_email_test\MinimalTestEmail;
use Drupal\mailer_email_test\ScalarsTestEmail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;

/**
 * Tests alter hooks for emails.
 */
#[Group('mailer')]
#[CoversClass(EmailRenderer::class)]
class EmailHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailer', 'system', 'mailer_email_test'];

  /**
   * Tests hook_email_params_alter().
   */
  public function testAlterParams(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_params_alter.set_attributes', [
      'text' => 'emotional support alpaca',
      'number' => 42,
      'status' => FALSE,
    ]);

    $instance = new ScalarsTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => ScalarsTestEmail::class,
    ]);

    $email = $emailRenderer->render($instance, ScalarsTestEmail::createParams('from params', 0, TRUE), 'en');
    $this->assertSame('Drupal\mailer_email_test\ScalarsTestEmail::getBody(text: emotional support alpaca, number: 42, status: false)', $email->getTextBody());
    $this->assertSame('Scalar Params Test Email', $email->getSubject());
  }

  /**
   * Tests hook_email_params_alter().
   */
  public function testLangcodeAlter(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_params_alter.set_langcode', 'fr');

    $instance = new MinimalTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => MinimalTestEmail::class,
    ]);

    $email = $emailRenderer->render($instance, MinimalTestEmail::createParams(), 'en');
    $this->assertSame('Drupal\mailer_email_test\MinimalTestEmail::getBody(id: mailer.test, module: mailer, key: test, langcode: fr)', $email->getTextBody());
    $this->assertSame('Minimal Test Email (mailer.test)', $email->getSubject());
  }

  /**
   * Tests hook_email_build_alter().
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
      '  <p><em>All</em> <strong>colors</strong> <u>are</u> <span class="rainbow">beautiful</span></p><p><small>ACME Inc.</small></p>',
      '</div>',
      '',
    ];
    $this->assertSame(implode("\n", $lines), $email->getHtmlBody());
    $this->assertSame('HTML Email (mailer.test)', $email->getSubject());
  }

  /**
   * Tests hook_email_alter().
   */
  public function testEmailAlter(): void {
    $emailRenderer = \Drupal::service(EmailRendererInterface::class);
    assert($emailRenderer instanceof EmailRendererInterface);

    \Drupal::state()->set('email_alter.set_tags', ['mailer.test']);
    \Drupal::state()->set('email_alter.set_metadata', [
      'X-Drupal-Module' => 'mailer',
      'X-Drupal-Key' => 'test',
    ]);

    $instance = new MinimalTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => MinimalTestEmail::class,
    ]);

    $email = $emailRenderer->render($instance, MinimalTestEmail::createParams(), 'en');
    $headers = [...$email->getHeaders()->all()];
    $this->assertContainsEquals(new TagHeader('mailer.test'), $headers);
    $this->assertContainsEquals(new MetadataHeader('X-Drupal-Module', 'mailer'), $headers);
    $this->assertContainsEquals(new MetadataHeader('X-Drupal-Key', 'test'), $headers);
  }

  /**
   * Tests hook_email_alter().
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

    $instance = new HtmlTestEmail([], 'mailer.test', [
      'id' => 'mailer.test',
      'provider' => 'mailer',
      'label' => 'Test Email',
      'module' => 'mailer',
      'key' => 'test',
      'class' => HtmlTestEmail::class,
    ]);

    $emailRenderer->render($instance, HtmlTestEmail::createParams(), 'en');
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
