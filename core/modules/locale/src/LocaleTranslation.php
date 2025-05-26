<?php

namespace Drupal\locale;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * String translator using the locale module.
 *
 * Full featured translation system using locale's string storage and
 * database caching.
 */
class LocaleTranslation implements TranslatorInterface, DestructableInterface {

  use DependencySerializationTrait;

  /**
   * Cached translations.
   *
   * @var array
   *   Array of \Drupal\locale\LocaleLookup objects indexed by language code
   *   and context.
   */
  protected $translations = [];

  /**
   * The translate english configuration value.
   *
   * @var bool
   */
  protected $translateEnglish;

  /**
   * Constructs a translator using a string storage.
   *
   * @param \Drupal\locale\StringStorageInterface $storage
   *   Storage to use when looking for new translations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(protected StringStorageInterface $storage, protected CacheBackendInterface $cache, protected LockBackendInterface $lock, protected ConfigFactoryInterface $configFactory, protected LanguageManagerInterface $languageManager, protected RequestStack $requestStack) {
  }

  /**
   * {@inheritdoc}
   */
  public function getStringTranslation($langcode, $string, $context) {
    // If the language is not suitable for locale module, just return.
    if ($langcode == LanguageInterface::LANGCODE_SYSTEM || ($langcode == 'en' && !$this->canTranslateEnglish())) {
      return FALSE;
    }
    // Strings are cached by langcode, context and roles, using instances of the
    // LocaleLookup class to handle string lookup and caching.
    if (!isset($this->translations[$langcode][$context])) {
      $this->translations[$langcode][$context] = new LocaleLookup($langcode, $context, $this->storage, $this->cache, $this->lock, $this->configFactory, $this->languageManager, $this->requestStack);
    }
    $translation = $this->translations[$langcode][$context]->get($string);
    // If the translation is TRUE, no translation exists, but that string needs
    // to be stored in the persistent cache for performance reasons (so for
    // example, we don't have hundreds of queries to locale tables on each
    // request). That cache is persisted when the request ends, and the lookup
    // service is destroyed.
    return $translation === TRUE ? FALSE : $translation;
  }

  /**
   * Gets translate english configuration value.
   *
   * @return bool
   *   TRUE if english should be translated, FALSE if not.
   */
  protected function canTranslateEnglish() {
    if (!isset($this->translateEnglish)) {
      $this->translateEnglish = $this->configFactory->get('locale.settings')->get('translate_english');
    }
    return $this->translateEnglish;
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    unset($this->translateEnglish);
    $this->translations = [];
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    foreach ($this->translations as $context) {
      foreach ($context as $lookup) {
        if ($lookup instanceof DestructableInterface) {
          $lookup->destruct();
        }
      }
    }
  }

}
