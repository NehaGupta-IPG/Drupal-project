<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Tests\BrowserTestBase;

/**
 * @covers \Drupal\system\Form\SiteInformationForm
 * @group system
 */
class SiteInformationFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user, log in admin user, and create one node.
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer site configuration',
    ]));
  }

  /**
   * Tests validation of the site information form.
   */
  public function testValidation(): void {
    $this->drupalGet('/admin/config/system/site-information');

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $page->fillField('site_frontpage', 'user/login');
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains("Either the path '" . 'user/login' . "' is invalid or you do not have access to it.");

    $page->fillField('site_403', 'not/allowed');
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains("Either the path '" . 'not/allowed' . "' is invalid or you do not have access to it.");

    $page->fillField('site_404', 'not/real');
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains("Either the path '" . 'not/allowed' . "' is invalid or you do not have access to it.");
  }

}
