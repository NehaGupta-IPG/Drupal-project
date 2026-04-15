<?php

declare(strict_types=1);

namespace Drupal\Tests\image\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the validation of server configuration for image style access.
 */
#[Group('image')]
#[RunTestsInSeparateProcesses]
class ImageStyleValidationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['image'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['administer image styles']));
  }

  /**
   * Tests image style validation from the image styles list.
   */
  public function testImageStyleValidation(): void {
    $this->drupalGet('admin/config/media/image-styles');
    $page = $this->getSession()->getPage();

    // The Validate link is inside a dropbutton. Find it and make it visible
    // by clicking the dropbutton toggle.
    $validate_link = $page->find('css', '[data-image-style-validate-public]');
    $this->assertNotNull($validate_link, 'The Validate link exists in the operations.');

    // Open the dropbutton that contains this link.
    $dropbutton = $validate_link->getParent()->getParent()->getParent();
    $toggle = $dropbutton->find('css', '.dropbutton-toggle button');
    if ($toggle) {
      $toggle->click();
      $this->assertSession()->waitForElementVisible('css', '[data-image-style-validate-public]');
    }

    // Click the Validate link.
    $validate_link->click();

    // Wait for the success message.
    $success_message_partial = 'Server is configured properly';
    $result = $this->assertSession()->waitForText($success_message_partial);
    $this->assertNotNull($result, 'The validation success message did not appear.');

    // Ensure the failure message is NOT present.
    $this->assertSession()->pageTextNotContains('Server is not configured properly');
  }

}
