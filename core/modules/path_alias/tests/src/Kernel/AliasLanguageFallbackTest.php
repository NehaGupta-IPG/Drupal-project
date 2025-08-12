<?php

declare(strict_types=1);

namespace Drupal\Tests\path_alias\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\path_alias\AliasRepositoryInterface;
use Drupal\Tests\language\Kernel\LanguageTestBase;
use Drupal\Tests\path_alias\Traits\PathAliasLanguageFallbackTestTrait;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Tests path alias language fallback functionality.
 *
 * @coversDefaultClass \Drupal\path_alias\AliasRepository
 *
 * @group path_alias
 */
class AliasLanguageFallbackTest extends LanguageTestBase {

  use PathAliasTestTrait,
    PathAliasLanguageFallbackTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'language',
    'path_alias',
    'path_alias_language_fallback_test',
  ];

  /**
   * The alias repository.
   *
   * @var \Drupal\path_alias\AliasRepositoryInterface
   */
  protected AliasRepositoryInterface $aliasRepository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');

    $language = ConfigurableLanguage::createFromLangcode('af');
    $language->save();

    $this->aliasRepository = $this->container->get('path_alias.repository');
  }

  /**
   * Ensure aliases can be looked up with extended language fallbacks.
   *
   * @covers ::lookupByAlias
   * @see PathAliasLanguageFallbackTestHooks::language_fallback_candidates_path_alias_alter()
   * @see PathAliasLanguageFallbackTestHooks::query_path_alias_language_fallback_alter()
   */
  public function testLookupByAlias(): void {
    // Create an alias for a path in Afrikaans.
    $test_source = '/user/1';
    $test_alias = '/users/my-test-path';
    $this->createPathAlias($test_source, $test_alias, 'af');
    $this->assertNull($this->aliasRepository->lookupByAlias($test_alias, 'en'), 'No path alias is found if we specify English to the alias repository and Afrikaans is not a valid fallback candidate for English.');

    // Enable configured fallback from English to Afrikaans.
    $this->setPathAliasFallbackLanguage('af');
    $en_lookup_result = $this->aliasRepository->lookupByAlias($test_alias, 'en');
    $this->assertEquals($test_source, $en_lookup_result['path'] ?? NULL, 'The Afrikaans path alias is returned if we specify English to the alias repository and Afrikaans is a valid fallback candidate for English.');
    // Test that standard path lookup still works.
    $af_lookup_result = $this->aliasRepository->lookupByAlias($test_alias, 'af');
    $this->assertEquals($test_source, $af_lookup_result['path'] ?? NULL, 'Directly looking up the path alias in Afrikaans still works when Afrikaans is a fallback language.');

    // Create an identical alias in English, for a different source path.
    $en_source_path = '/user/2';
    $this->createPathAlias($en_source_path, $test_alias, 'en');
    $en_lookup_result = $this->aliasRepository->lookupByAlias($test_alias, 'en');
    $this->assertEquals($en_source_path, $en_lookup_result['path'] ?? NULL, 'The more specific English path alias is returned if we specify English to the alias repository.');

    // Check that no alias is found when none exists.
    $this->assertNull($this->aliasRepository->lookupByAlias('/this-alias-does-not-exist', 'en'), 'No alias is found when none exists.');
  }

  /**
   * Ensure looking up aliases for paths works with extended language fallbacks.
   *
   * @covers ::lookupBySystemPath
   * @see PathAliasLanguageFallbackTestHooks::language_fallback_candidates_path_alias_alter()
   * @see PathAliasLanguageFallbackTestHooks::query_path_alias_language_fallback_alter()
   */
  public function testLookupBySystemPath(): void {
    // Create an alias for a path in Afrikaans.
    $test_source = '/user/login';
    $test_alias = '/test-login-alias';
    $this->createPathAlias($test_source, $test_alias, 'af');
    $this->assertNull($this->aliasRepository->lookupBySystemPath($test_source, 'en'), 'No path alias is found if we specify English to the alias repository and Afrikaans is not a valid fallback candidate for English.');

    // Enable configured fallback from English to Afrikaans.
    $this->setPathAliasFallbackLanguage('af');
    $en_lookup_result = $this->aliasRepository->lookupBySystemPath($test_source, 'en');
    $this->assertEquals($test_alias, $en_lookup_result['alias'] ?? NULL, 'The Afrikaans path alias is returned if we specify English to the alias repository and Afrikaans is a valid fallback candidate for English.');
    // Test that standard path lookup still works.
    $af_lookup_result = $this->aliasRepository->lookupBySystemPath($test_source, 'af');
    $this->assertEquals($test_alias, $af_lookup_result['alias'] ?? NULL, 'Directly looking up the system path for an alias in Afrikaans still works when Afrikaans is a fallback language.');

    // Create an identical alias in English, for a different source path.
    $en_test_alias = '/test-english-login-alias';
    $this->createPathAlias($test_source, $en_test_alias, 'en');
    $en_lookup_result = $this->aliasRepository->lookupBySystemPath($test_source, 'en');
    $this->assertEquals($en_test_alias, $en_lookup_result['alias'] ?? NULL, 'The more specific English path alias is returned if we specify English to the alias repository.');

    // Check that no alias is found when none exists.
    $this->assertNull($this->aliasRepository->lookupBySystemPath('/this-alias-does-not-exist', 'en'), 'No alias is found when none exists.');
  }

}
