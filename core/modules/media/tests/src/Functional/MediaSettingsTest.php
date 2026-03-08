<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Functional;

use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Testing the media settings.
 */
#[Group('media')]
#[RunTestsInSeparateProcesses]
class MediaSettingsTest extends MediaFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->createUser([
      'administer site configuration',
      'administer media',
    ]));
  }

  /**
   * Tests that the media settings form stores a `null` iFrame domain.
   */
  public function testSettingsForm(): void {
    $assert_session = $this->assertSession();

    $this->assertNull($this->config('media.settings')->get('iframe_domain'));
    $this->drupalGet(Url::fromRoute('media.settings'));
    $assert_session->fieldExists('iframe_domain');

    // Explicitly submitting an empty string does not result in the
    // `iframe_domain` property getting set to the empty string: it is converted
    // to `null` to comply with the config schema.
    // @see \Drupal\media\Form\MediaSettingsForm::submitForm()
    $this->submitForm([
      'iframe_domain' => '',
    ], 'Save configuration');
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $this->assertNull($this->config('media.settings')->get('iframe_domain'));

    // Check that the correct value for the "X-Frame-Options" header is set when
    // using an alternate IFRAME domain.
    $iframe_domain = 'http://example.com';
    $this->config('media.settings')->set('iframe_domain', $iframe_domain)->save();
    $this->drupalGet('<front>');
    $assert_session->responseHeaderEquals('X-Frame-Options', 'ALLOW-FROM: ' . $iframe_domain);
  }

}
