<?php

namespace Drupal\views\Plugin\Menu\Form;

use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\Form\MenuLinkDefaultForm;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to edit Views menu links.
 *
 * This provides the feature to edit the title and description, in contrast to
 * the default menu link form.
 *
 * @see \Drupal\views\Plugin\Menu\ViewsMenuLink
 */
class ViewsMenuLinkForm extends MenuLinkDefaultForm {

  /**
   * The edited views menu link.
   *
   * @var \Drupal\views\Plugin\Menu\ViewsMenuLink
   */
  protected $menuLink;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new \Drupal\Core\Menu\Form\MenuLinkDefaultForm.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link manager.
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_selector
   *   The menu parent form selector service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(MenuLinkManagerInterface $menu_link_manager, MenuParentFormSelectorInterface $menu_parent_selector, TranslationInterface $string_translation, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($menu_link_manager, $menu_parent_selector, $string_translation, $module_handler);

    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.menu.link'),
      $container->get('menu.parent_form_selector'),
      $container->get('string_translation'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $metadata = $this->menuLink->getMetaData();
    $view_id = $metadata['view_id'];
    $display_id = $metadata['display_id'];
    $view = $this->entityTypeManager->getStorage('view')->load($view_id);

    $config_name = $view->getConfigDependencyName();
    $config_path = 'display.' . $display_id . '.display_options.menu';

    // Get settings in the original language.
    $options = \Drupal::service('config.factory')->getEditable($config_name)
      ->get($config_path);

    // Put the title field first.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      // @todo Ensure that the view is not loaded with a localized title.
      //   https://www.drupal.org/node/2309507
      '#default_value' => $options['title'],
      '#weight' => -10,
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Shown when hovering over the menu link.'),
      // @todo Ensure that the view is not loaded with a localized description.
      //   https://www.drupal.org/node/2309507
      '#default_value' => $options['description'],
      '#weight' => -5,
    ];

    $form += parent::buildConfigurationForm($form, $form_state);

    $form['info']['#weight'] = -8;
    $form['path']['#weight'] = -7;

    $view = $this->menuLink->loadView();
    $id = $view->storage->id();
    $label = $view->storage->label();
    if ($this->moduleHandler->moduleExists('views_ui')) {
      $message = $this->t('This link is provided by the Views module. The path can be changed by editing the view <a href=":url">@label</a>', [
        ':url' => Url::fromRoute('entity.view.edit_form', ['view' => $id])->toString(),
        '@label' => $label,
      ]);
    }
    else {
      $message = $this->t('This link is provided by the Views module from view %label.', ['%label' => $label]);
    }
    $form['info']['#title'] = $message;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(array &$form, FormStateInterface $form_state) {
    $definition = parent::extractFormValues($form, $form_state);
    $definition['title'] = $form_state->getValue('title');
    $definition['description'] = $form_state->getValue('description');

    return $definition;
  }

}
