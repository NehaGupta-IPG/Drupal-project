<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Attribute\ViewsField;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler for multilingual only boolean fields.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("language_boolean")]
class LanguageBoolean extends Boolean {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    // No point in displaying the language field on monolingual sites,
    // as only one language value is available.
    return $this->languageManager->isMultilingual() && parent::access($account);
  }

}
