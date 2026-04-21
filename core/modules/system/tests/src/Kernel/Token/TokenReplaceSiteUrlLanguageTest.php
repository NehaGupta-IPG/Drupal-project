<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Token;

use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that [site:url] token includes a trailing slash in all languages.
 */
#[Group('system')]
#[Group('language')]
#[RunTestsInSeparateProcesses]
class TokenReplaceSiteUrlLanguageTest extends TokenReplaceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language', 'language_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');

    $this->container->get('entity_type.manager')
      ->getStorage('configurable_language')
      ->create(['id' => 'fr', 'label' => 'French'])
      ->save();

    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => '', 'fr' => 'fr'])
      ->save();

    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * Tests that [site:url] always ends with a trailing slash.
   */
  public function testSiteUrlTokenTrailingSlash(): void {
    $url_options = ['absolute' => TRUE, 'language' => $this->interfaceLanguage];

    $en_replaced = $this->tokenService->replace('[site:url]user/points', [], ['langcode' => 'en']);
    $this->assertEquals(Url::fromRoute('<front>', [], $url_options)->toString() . 'user/points', $en_replaced);

    $french_language = $this->container->get('language_manager')->getLanguage('fr');
    $french_url_options = ['absolute' => TRUE, 'language' => $french_language];
    $fr_replaced = $this->tokenService->replace('[site:url]user/points', [], ['langcode' => 'fr']);
    $this->assertEquals(Url::fromRoute('<front>', [], $french_url_options)->toString() . '/user/points', $fr_replaced);
  }

}
