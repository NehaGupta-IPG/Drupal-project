<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Installer;

use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests translation download behavior during installation with mocked HTTP.
 */
#[Group('Installer')]
#[RunTestsInSeparateProcesses]
class InstallerDownloadTranslationMockedTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * Installer language used for both test scenarios.
   */
  protected string $langcode = 'de';

  /**
   * The mocked translation server pattern used by install_download_translation().
   */
  protected const string SERVER_PATTERN = MockTranslationServerMiddleware::MOCK_SERVER_BASE_URI . '%project-%version.%language.po';

  /**
   * {@inheritdoc}
   */
  protected function prepareEnvironment(): void {
    parent::prepareEnvironment();

    MockTranslationServerMiddleware::reset($this->getPo($this->langcode));

    if ($this->providedData()['has_local_translation']) {
      $translation_file_path = $this->getTranslationFilePath();
      mkdir(dirname($translation_file_path), 0777, TRUE);
      file_put_contents($translation_file_path, $this->getPo($this->langcode));
      $this->assertFileExists($translation_file_path);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSettings(): void {
    parent::prepareSettings();

    $services_path = DRUPAL_ROOT . '/' . $this->siteDirectory . '/services.yml';
    $services = Yaml::decode((string) file_get_contents($services_path));
    $services['services']['testing.mock_translation_server.middleware'] = [
      'class' => MockTranslationServerMiddleware::class,
      'tags' => [['name' => 'http_client_middleware']],
    ];
    file_put_contents($services_path, Yaml::encode($services));
  }

  /**
   * {@inheritdoc}
   */
  protected function installParameters(): array {
    $parameters = parent::installParameters();
    $parameters['parameters']['langcode'] = $this->langcode;
    $parameters['download_translation'] = $this->providedData()['download_translation'];
    $parameters['server_pattern'] = static::SERVER_PATTERN;
    return $parameters;
  }

  /**
   * Tests download vs no-download paths of install_download_translation().
   */
  #[DataProvider('providerInstallScenarios')]
  public function testInstallTranslationDownload(
    bool $has_local_translation,
    bool $download_translation,
    int $expected_head_requests,
    int $expected_get_requests,
    bool $expected_translation_file_exists,
  ): void {
    $this->assertSame($expected_head_requests, MockTranslationServerMiddleware::getHeadRequestCount());
    $this->assertSame($expected_get_requests, MockTranslationServerMiddleware::getGetRequestCount());

    $downloaded_uris = MockTranslationServerMiddleware::getGetRequestUris();
    $this->assertCount($expected_get_requests, $downloaded_uris);

    $translation_file_path = $this->getTranslationFilePath();
    if ($expected_translation_file_exists) {
      $this->assertFileExists($translation_file_path);
      $this->assertEquals(
        $this->getPo($this->langcode),
        file_get_contents($translation_file_path),
      );
    }
    else {
      $this->assertFileDoesNotExist($translation_file_path);
    }
  }

  /**
   * Data provider for translation download scenarios.
   */
  public static function providerInstallScenarios(): array {
    return [
      'downloads translation when local translation file does not exist' => [
        'has_local_translation' => FALSE,
        'download_translation' => TRUE,
        'expected_head_requests' => 1,
        'expected_get_requests' => 1,
        'expected_translation_file_exists' => TRUE,
      ],
      'does not download when local translation file exists, even when downloading is enabled' => [
        'has_local_translation' => TRUE,
        'download_translation' => TRUE,
        'expected_head_requests' => 0,
        'expected_get_requests' => 0,
        'expected_translation_file_exists' => TRUE,
      ],
      'does not download when downloading is disabled, even when local translation file does not exist' => [
        'has_local_translation' => FALSE,
        'download_translation' => FALSE,
        'expected_head_requests' => 0,
        'expected_get_requests' => 0,
        'expected_translation_file_exists' => FALSE,
      ],
      'does not download when downloading is disabled and local translation file exists' => [
        'has_local_translation' => TRUE,
        'download_translation' => FALSE,
        'expected_head_requests' => 0,
        'expected_get_requests' => 0,
        'expected_translation_file_exists' => TRUE,
      ],
    ];
  }

  /**
   * Get the path of the translation file that may or may not be downloaded in this test.
   */
  protected function getTranslationFilePath(): string {
    return "$this->root/$this->siteDirectory/files/translations/drupal-{$this->getInstallerTranslationVersion()}.$this->langcode.po";
  }

  /**
   * Builds the installer translation version token used in file names.
   */
  protected static function getInstallerTranslationVersion(): string {
    $version = \Drupal::VERSION;
    if (preg_match('/^(\d+\.\d+\.).*-dev$/', $version, $matches)) {
      return $matches[1] . 'x';
    }
    return $version;
  }

  /**
   * Returns a minimal .po payload for installer translation checks.
   */
  protected function getPo(string $langcode): string {
    return <<<PO
msgid ""
msgstr ""

msgid "Save and continue"
msgstr "Save and continue $langcode"
PO;
  }

}
