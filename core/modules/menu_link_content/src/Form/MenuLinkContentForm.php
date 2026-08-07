<?php

namespace Drupal\menu_link_content\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to add/update content menu links.
 *
 * @internal
 */
class MenuLinkContentForm extends ContentEntityForm {

  /**
   * The content menu link.
   *
   * @var \Drupal\menu_link_content\MenuLinkContentInterface
   */
  protected $entity;

  /**
   * Constructs a MenuLinkContentForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $menuParentSelector
   *   The menu parent form selector service.
   * @param \Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   The path validator.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    protected MenuParentFormSelectorInterface $menuParentSelector,
    protected PathValidatorInterface $pathValidator,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('menu.parent_form_selector'),
      $container->get('path.validator'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $parent_id = $this->entity->getParentId() ?: $this->getRequest()->query->get('parent');
    $default = $this->entity->getMenuName() . ':' . $parent_id;
    $id = $this->entity->isNew() ? '' : $this->entity->getPluginId();
    $menu_id = $this->entity->getMenuName();
    $menu = $this->entityTypeManager->getStorage('menu')->load($menu_id);
    if ($menu instanceof MenuInterface && $this->entity->isNew()) {
      $form['menu_parent'] = $this->menuParentSelector->parentSelectElement($default, $id, [
        $menu_id => $menu->label(),
      ]);
    }
    else {
      $form['menu_parent'] = $this->menuParentSelector->parentSelectElement($default, $id);
    }
    $form['menu_parent']['#weight'] = 10;
    $form['menu_parent']['#title'] = $this->t('Parent link');
    $form['menu_parent']['#description'] = $this->t('The maximum depth for a link and all its children is fixed. Some menu links may not be available as parents if selecting them would exceed this limit.');
    $form['menu_parent']['#attributes']['class'][] = 'menu-title-select';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $element = parent::actions($form, $form_state);
    $element['submit']['#button_type'] = 'primary';
    $element['delete']['#access'] = $this->entity->access('delete');

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    [$menu_name, $parent] = explode(':', $form_state->getValue('menu_parent'), 2);

    $entity->parent->value = $parent;
    $entity->menu_name->value = $menu_name;
    $entity->enabled->value = (!$form_state->isValueEmpty(['enabled', 'value']));
    $entity->expanded->value = (!$form_state->isValueEmpty(['expanded', 'value']));
    $entity->link->uri = $this->reformatInternalLinkIfEnteredAsExternal($entity->link->uri);

    return $entity;
  }

  /**
   * Reformat an internal link if it was entered as an external one.
   *
   * @param string $uri
   *   The URI to possibly reformat.
   *
   * @return string
   *   The possibly reformatted link.
   */
  protected function reformatInternalLinkIfEnteredAsExternal(string $uri): string {
    // Schemes such as mailto: and tel: are external but carry no host for
    // UrlHelper::externalIsLocal() to compare, which would make it throw
    // an exception.
    $parts = UrlHelper::isExternal($uri) ? parse_url($uri) : FALSE;
    if (!$parts || empty($parts['host'])) {
      return $uri;
    }

    $request = $this->getRequest();
    $base_path = $request->getBasePath();
    $path = $parts['path'] ?? '';
    // A URL pointing at the site root may be entered without its trailing
    // slash, leaving no path for the base path to be matched against below.
    if ($path === '' || $path === $base_path) {
      $path .= '/';
    }

    // The trailing slash anchors the base path so that a partial match, such
    // as /subdirectory against /subdir, is not mistaken for a local URL. Only
    // the host and the path take part in the comparison, so passing a URL
    // rebuilt from those two is enough.
    $base_url = $request->getSchemeAndHttpHost() . $base_path . '/';
    if (!UrlHelper::externalIsLocal('//' . $parts['host'] . $path, $base_url)) {
      return $uri;
    }

    // The entered URL points at this site, so drop the base path and keep the
    // rest.
    return 'internal:' . substr($path, strlen($base_path))
      . (isset($parts['query']) ? '?' . $parts['query'] : '')
      . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // The entity is rebuilt in parent::submit().
    $menu_link = $this->entity;
    $menu_link->save();

    $this->messenger()->addStatus($this->t('The menu link has been saved.'));

    $form_state->setRedirectUrl($menu_link->toUrl('canonical'));
  }

}
