<?php

declare(strict_types=1);

namespace Drupal\module_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\module_autoload_test\SomeClass;

/**
 * Controller routines for module_test routes.
 */
class ModuleTestController {

  /**
   * Returns dynamically invoked hook results for the 'module_test' module.
   *
   * @return array
   *   Renderable array.
   */
  #[Route(
    path: '/module-test/hook-dynamic-loading-invoke',
    name: 'module_test.dynamic_invoke',
    title: new TranslatableMarkup('Test hook dynamic loading (invoke)'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function hookDynamicLoadingInvoke() {
    $result = \Drupal::moduleHandler()->invoke('module_test', 'test_hook');
    return $result['module_test'];
  }

  /**
   * Returns dynamically invoked hook results for all modules.
   *
   * @return array
   *   Renderable array.
   */
  #[Route(
    path: '/module-test/hook-dynamic-loading-invoke-all',
    name: 'module_test.dynamic_invoke_all',
    title: new TranslatableMarkup('Test hook dynamic loading (invoke_all)'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function hookDynamicLoadingInvokeAll() {
    $result = \Drupal::moduleHandler()->invokeAll('test_hook');
    return $result['module_test'];
  }

  /**
   * Returns the result of an autoloaded class's public method.
   *
   * @return array
   *   Renderable array.
   */
  #[Route(
    path: '/module-test/class-loading',
    name: 'module_test.class_loading',
    title: new TranslatableMarkup('Test loading a class from another module'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function testClassLoading() {
    $markup = NULL;
    if (class_exists('Drupal\module_autoload_test\SomeClass')) {
      $obj = new SomeClass();
      $markup = $obj->testMethod();
    }
    return ['#markup' => $markup];
  }

}
