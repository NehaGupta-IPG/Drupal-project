<?php

declare(strict_types = 1);

namespace Drupal\user;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\CallableResolver;
use Drupal\user\Permissions\PermissionsRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Base class for permissions parser plugins.
 */
abstract class PermissionsParserPluginBase extends PluginBase implements ContainerFactoryPluginInterface, PermissionsParserInterface {

  use StringTranslationTrait;

  protected const PROCESSED_KEY = '_processed';

  protected $callableResolver;

  /**
   * Constructs a PermissionsParserPluginBase object.
   *
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $stringTranslation,
    CallableResolver $callableResolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $stringTranslation;
    $this->callableResolver = $callableResolver;
  }

  /**
   * {@inheritdoc}
   */
  public abstract function parse(array &$ary, string $provider, PermissionsRepositoryInterface $permissionObj): int;

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * A wrapper around t() that deals with different types of input.
   *
   * @param string $string
   *   Possibly a string containing the English text to translate.
   *   If it's an object or array, that's returned.
   *   If it's not an object, array, or string, it's cast to a string.
   *
   * @see \Drupal\Core\StringTranslation\TranslationInterface::translate()
   *   The other parameters and the return value are the same.
   */
  protected function tWrapper($string, $options = [], $context = []) {
    if (is_object($string) || is_array($string)) {
      return $string;
    }

    if (!is_string($string)) {
      $string = '' . $string;
    }

    return $this->t($string, $options, $context);
  }

}
