<?php

namespace Drupal\language\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigCollectionInfo;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigFactoryOverrideBase;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Config\NullStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides language overrides for the configuration factory.
 */
class LanguageConfigFactoryOverride extends ConfigFactoryOverrideBase implements LanguageConfigFactoryOverrideInterface, EventSubscriberInterface {

  use LanguageConfigCollectionNameTrait;

  /**
   * An array of configuration storages keyed by langcode.
   *
   * @var \Drupal\Core\Config\StorageInterface[]
   */
  protected $storages;

  /**
   * The language object used to override configuration data.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $language;

  /**
   * Whether the language is already negotiated.
   *
   * @var bool
   */
  protected bool $isNegotiated = FALSE;

  /**
   * Whether in the process of negotiating language.
   *
   * @var bool
   */
  protected bool $isNegotiating = FALSE;

  public function __construct(
    protected StorageInterface $baseStorage,
    protected EventDispatcherInterface $eventDispatcher,
    protected TypedConfigManagerInterface $typedConfigManager,
    LanguageDefault $default_language,
    protected ?array $defaultLanguageValues,
    protected ?bool $translateEnglish = TRUE,
    #[AutowireServiceClosure(LanguageManagerInterface::class)]
    protected ?\Closure $getLanguageManager = NULL,
  ) {
    // Prior to negotiation the override language should be the default
    // language.
    $this->language = $default_language->get();
    if ($this->defaultLanguageValues === NULL) {
      @trigger_error('Not passing the language.default_values parameter to LanguageConfigFactoryOverride::__construct() is deprecated in drupal:11.3.0 and it will be required in drupal::12.0.0. See https://www.drupal.org/project/drupal/issues/3518992');
      $this->defaultLanguageValues = \Drupal::getContainer()->getParameter('language.default_values');
    }
    if ($this->translateEnglish === NULL) {
      @trigger_error('Not passing the language.translate_english parameter to LanguageConfigFactoryOverride::__construct() is deprecated in drupal:11.3.0 and it will be required in drupal::12.0.0. See https://www.drupal.org/project/drupal/issues/3518992');
      $this->translateEnglish = \Drupal::getContainer()->getParameter('language.translate_english');
    }
    if ($this->getLanguageManager === NULL) {
      // @fixme Set deprecation version.
      @trigger_error('Not passing the getLanguageManager parameter to LanguageConfigFactoryOverride::__construct() is deprecated in drupal:XXXXXXX and it will be required in drupal::12.0.0. See https://www.drupal.org/project/drupal/issues/3518992');
      // @fixme Defer on serializable service closures https://www.drupal.org/i/3544994
      $this->getLanguageManager = fn() => \Drupal::service(LanguageManagerInterface::class);
    }
  }

  /**
   * Checks whether overrides should be loaded.
   */
  protected function shouldSkipOverrides(): bool {
    return $this->defaultLanguageValues['id'] === 'en'
      && !$this->translateEnglish
      && $this->getLanguage()
      && $this->getLanguage()->getId() === 'en';
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    if ($this->getLanguage()) {
      $storage = $this->getStorage($this->getLanguage()->getId());
      return $storage->readMultiple($names);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOverride($langcode, $name) {
    $storage = $this->getStorage($langcode);
    $data = $storage->read($name);

    $override = new LanguageConfigOverride(
      $name,
      $storage,
      $this->typedConfigManager,
      $this->eventDispatcher
    );

    if (!empty($data)) {
      $override->initWithData($data);
    }
    return $override;
  }

  /**
   * {@inheritdoc}
   */
  public function getStorage($langcode) {
    // Skip loading overrides when English is the default language and the
    // passed in langcode is English.
    if (!isset($this->storages[$langcode])) {
      if ($langcode === 'en' && $this->shouldSkipOverrides()) {
        $this->storages[$langcode] = new NullStorage($this->createConfigCollectionName($langcode));
      }
      else {
        $this->storages[$langcode] = $this->baseStorage->createCollection($this->createConfigCollectionName($langcode));
      }
    }
    return $this->storages[$langcode];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return $this->getLanguage() ? $this->getLanguage()->getId() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage() {
    if ($this->isNegotiating) {
      // Recursion while negotiating, return the default language.
      // @todo Once needed, add a proper circular dependency check like in
      //   ServiceContainer.
      return $this->language;
    }
    // Negotiate language if not already done.
    if ($this->isNegotiated) {
      $this->isNegotiating = TRUE;
      $this->language = ($this->getLanguageManager)()->getCurrentLanguage();
      $this->isNegotiating = FALSE;
      $this->isNegotiated = TRUE;
    }
    return $this->language;
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage(?LanguageInterface $language = NULL) {
    // @todo Remove this brittle get / set interface for language overrides with
    //   sth like ::executeWithConfigLanguageOverride().
    $this->language = $language;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function installLanguageOverrides($langcode) {
    /** @var \Drupal\Core\Config\ConfigInstallerInterface $config_installer */
    $config_installer = \Drupal::service('config.installer');
    $config_installer->installCollectionDefaultConfig($this->createConfigCollectionName($langcode));
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    $langcode = $this->getLangcodeFromCollectionName($collection);
    return $this->getOverride($langcode, $name);
  }

  /**
   * {@inheritdoc}
   */
  public function addCollections(ConfigCollectionInfo $collection_info) {
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $collection_info->addCollection($this->createConfigCollectionName($language->getId()), $this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $config_translation = $this->getOverride($language->getId(), $name);
      if (!$config_translation->isNew()) {
        $this->filterOverride($config, $config_translation);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigRename(ConfigRenameEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    $old_name = $event->getOldName();
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $config_translation = $this->getOverride($language->getId(), $old_name);
      if (!$config_translation->isNew()) {
        $saved_config = $config_translation->get();
        $storage = $this->getStorage($language->getId());
        $storage->write($name, $saved_config);
        $config_translation->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onConfigDelete(ConfigCrudEvent $event) {
    $config = $event->getConfig();
    $name = $config->getName();
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $config_translation = $this->getOverride($language->getId(), $name);
      if (!$config_translation->isNew()) {
        $config_translation->delete();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    $metadata = new CacheableMetadata();
    if ($this->getLanguage()) {
      $metadata->setCacheContexts(['languages:language_interface']);
    }
    return $metadata;
  }

}
