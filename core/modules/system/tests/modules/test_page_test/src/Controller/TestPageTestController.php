<?php

declare(strict_types=1);

namespace Drupal\test_page_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Entity\Role;

/**
 * Controller routines for test_page_test routes.
 */
class TestPageTestController {

  use StringTranslationTrait;

  /**
   * Returns a test page and sets the title.
   */
  #[Route(
    path: '/test-page',
    name: 'test_page_test.test_page',
    title: new TranslatableMarkup('Test front page'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function testPage() {
    $link_text = $this->t('Visually identical test links');
    return [
      '#title' => $this->t('Test page'),
      '#markup' => $this->t('Test page text.') . "<a href=\"/user/login\">$link_text</a><a href=\"/user/register\">$link_text</a>",
      '#attached' => [
        'drupalSettings' => [
          'test-setting' => 'azAZ09();.,\\\/-_{}',
        ],
      ],
    ];
  }

  /**
   * Returns a test page and with the call to the dump() function.
   */
  #[Route(
    path: '/test-page-var-dump',
    name: 'test_page_test.test_page_var_dump',
    title: new TranslatableMarkup('Test front page with var dump'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function testPageVarDump() {
    $role = Role::create(['id' => 'test_role', 'label' => 'Test role']);
    dump($role);
    return [
      '#title' => $this->t('Test page with var dump'),
      '#markup' => $this->t('Test page text.'),
    ];
  }

}
