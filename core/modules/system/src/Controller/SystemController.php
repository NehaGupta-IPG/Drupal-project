<?php

namespace Drupal\system\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\Extension\ModuleDependencyMessageTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Theme\ThemeAccessCheck;
use Drupal\Core\Url;
use Drupal\system\SystemManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for System routes.
 */
class SystemController extends ControllerBase {

  use ModuleDependencyMessageTrait;

  /**
   * System Manager Service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * The theme access checker service.
   *
   * @var \Drupal\Core\Theme\ThemeAccessCheck|null
   */
  protected $themeAccess;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|null
   */
  protected $formBuilder;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The theme extension list.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList|null
   */
  protected ?ThemeExtensionList $themeExtensionList;

  /**
   * Constructs a new SystemController.
   *
   * @param \Drupal\system\SystemManager $systemManager
   *   System manager service.
   * @param \Drupal\Core\Theme\ThemeAccessCheck|null $theme_access
   *   The theme access checker service.
   * @param \Drupal\Core\Form\FormBuilderInterface|null $form_builder
   *   The form builder.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   * @param \Drupal\Core\Extension\ThemeExtensionList|null $theme_extension_list
   *   The theme extension list.
   */
  public function __construct(SystemManager $systemManager, ?ThemeAccessCheck $theme_access, ?FormBuilderInterface $form_builder, MenuLinkTreeInterface $menu_link_tree, ModuleExtensionList $module_extension_list, ?ThemeExtensionList $theme_extension_list = NULL) {
    $this->systemManager = $systemManager;
    // @todo deprecate $theme_access.
    $this->themeAccess = $theme_access;
    // @todo deprecate $form_builder.
    $this->formBuilder = $form_builder;
    $this->menuLinkTree = $menu_link_tree;
    $this->moduleExtensionList = $module_extension_list;
    // @todo deprecate $theme_extension_list.
    $this->themeExtensionList = $theme_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('system.manager'),
      NULL,
      NULL,
      $container->get('menu.link_tree'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * Provide the administration overview page.
   *
   * This will render child links two levels below the specified link ID,
   * grouped by the child links one level below.
   *
   * @param string $link_id
   *   The ID of the administrative path link for which to display child links.
   *
   * @return array
   *   A renderable array of the administration overview page.
   */
  public function overview($link_id) {
    // Check for status report errors.
    if ($this->currentUser()->hasPermission('administer site configuration') && $this->systemManager->checkRequirements()) {
      $this->messenger()->addError($this->t('One or more problems were detected with your Drupal installation. Check the <a href=":status">status report</a> for more information.', [':status' => Url::fromRoute('system.status')->toString()]));
    }
    // Load all menu links below it.
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link_id)->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load(NULL, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $tree_access_cacheability = new CacheableMetadata();
    $blocks = [];
    foreach ($tree as $key => $element) {
      $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($element->access));

      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        continue;
      }

      $link = $element->link;
      $block['title'] = $link->getTitle();
      $block['description'] = $link->getDescription();
      $block['content'] = [
        '#theme' => 'admin_block_content',
        '#content' => $this->systemManager->getAdminBlock($link),
      ];

      if (!empty($block['content']['#content'])) {
        $blocks[$key] = $block;
      }
    }

    if ($blocks) {
      ksort($blocks);
      $build = [
        '#theme' => 'admin_page',
        '#blocks' => $blocks,
      ];
      $tree_access_cacheability->applyTo($build);
      return $build;
    }
    else {
      $build = [
        '#markup' => $this->t('You do not have any administrative items.'),
      ];
      $tree_access_cacheability->applyTo($build);
      return $build;
    }
  }

  /**
   * Sets whether the admin menu is in compact mode or not.
   *
   * @param string $mode
   *   Valid values are 'on' and 'off'.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the front page.
   */
  public function compactPage($mode) {
    user_cookie_save(['admin_compact_mode' => ($mode == 'on')]);
    return $this->redirect('<front>');
  }

  /**
   * Provides a single block from the administration menu as a page.
   */
  public function systemAdminMenuBlockPage() {
    return $this->systemManager->getBlockContents();
  }

  /**
   * Returns a theme listing which excludes obsolete themes.
   *
   * @return array
   *   A render array of the theme listing page.
   */
  public function themesPage() {
    // @todo deprecate this.
    $controller = \Drupal::service(ControllerResolverInterface::class)
      ->getControllerFromDefinition(ThemeController::class . '::themesPage');
    return $controller();
  }

}
