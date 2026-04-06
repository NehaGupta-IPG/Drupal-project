<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Traits;

use Behat\Mink\Element\NodeElement;

/**
 * Provides methods for checking update module output.
 */
trait UpdateTestAssertionsTrait {

  /**
   * Finds an update page element by label.
   *
   * @param string $label
   *   The label for the update, for example "Recommended version:" or
   *   "Latest version:".
   * @param int $index
   *   (optional) The index of the element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The update element.
   */
  protected function findUpdateElementByLabel($label, int $index = 0): NodeElement {
    $update_elements = $this->getSession()->getPage()
      ->findAll('css', $this->updateTableLocator . " .project-update__version:contains(\"$label\")");
    $this->assertGreaterThanOrEqual($index, count($update_elements));
    return $update_elements[$index];
  }

  /**
   * Asserts that a core compatibility message is correct for an update.
   *
   * @param string $version
   *   The version of the update.
   * @param string $expected_range
   *   The expected core compatibility range.
   * @param string $expected_release_title
   *   The expected release title.
   * @param bool $is_compatible
   *   If the update is compatible with the installed version of Drupal.
   *
   * @internal
   */
  protected function assertCoreCompatibilityMessage(string $version, string $expected_range, string $expected_release_title, bool $is_compatible = TRUE): void {
    $update_element = $this->findUpdateElementByLabel($expected_release_title);
    $this->assertTrue($update_element->hasLink($version));
    $compatibility_details = $update_element->find('css', '.project-update__compatibility-details details');
    $this->assertStringContainsString("Requires Drupal core: $expected_range", $compatibility_details->getText());
    $details_summary_element = $compatibility_details->find('css', 'summary');
    if ($is_compatible) {
      // If an update is compatible with the installed version of Drupal core,
      // the details element should be closed by default.
      $this->assertFalse($compatibility_details->hasAttribute('open'));
      $this->assertSame('Compatible', $details_summary_element->getText());
    }
    else {
      // If an update is not compatible with the installed version of Drupal
      // core, the details element should be open by default.
      $this->assertTrue($compatibility_details->hasAttribute('open'));
      $this->assertSame('Not compatible', $details_summary_element->getText());
    }
    $this->assertFalse($update_element->hasLink('Download'));
  }

  /**
   * Asserts that an update version has the correct links.
   *
   * @param string $label
   *   The label for the update.
   * @param string $version
   *   The project version.
   * @param int $index
   *   (optional) The index of the link.
   */
  protected function assertVersionUpdateLinks($label, string $version, int $index = 0) {
    $update_element = $this->findUpdateElementByLabel($label, $index);
    // In the release notes URL the periods are replaced with dashes.
    $url_version = str_replace('.', '-', $version);

    $this->assertEquals($update_element->findLink($version)->getAttribute('href'), "https://example.com/{$this->updateProject}-$url_version-release");
    $this->assertStringNotContainsString("https://example.com/{$this->updateProject}-$version.tar.gz", $update_element->getOuterHtml());
    $this->assertEquals($update_element->findLink('Release notes')->getAttribute('href'), "https://example.com/{$this->updateProject}-$url_version-release");
  }

  /**
   * Asserts the expected security updates are displayed correctly on the page.
   *
   * @param string $project_path_part
   *   The project path part needed for the release link.
   * @param string[] $expected_security_releases
   *   The security releases, if any, that the status report should recommend.
   * @param string $expected_update_message_type
   *   The type of update message expected.
   * @param string $update_element_css_locator
   *   The CSS locator for the page element that contains the security updates.
   */
  protected function assertSecurityUpdates($project_path_part, array $expected_security_releases, string $expected_update_message_type, $update_element_css_locator) {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->standardTests();
    $assert_session->elementTextNotContains('css', $update_element_css_locator, 'Not supported');
    $all_security_release_urls = array_map(function (NodeElement $link) {
      return $link->getAttribute('href');
    }, $page->findAll('css', "$update_element_css_locator .version-security a[href$='-release']"));
    if ($expected_security_releases) {
      $expected_release_urls = [];
      if ($expected_update_message_type === static::SECURITY_UPDATE_REQUIRED) {
        $assert_session->elementTextNotContains('css', $update_element_css_locator, 'Update available');
        $assert_session->elementTextContains('css', $update_element_css_locator, 'Security update required!');
        // Verify that the error icon is found.
        $assert_session->responseContains('error.svg');
      }
      else {
        $assert_session->elementTextContains('css', $update_element_css_locator, 'Update available');
        $assert_session->elementTextNotContains('css', $update_element_css_locator, 'Security update required!');
      }
      $assert_session->elementTextNotContains('css', $update_element_css_locator, 'Up to date');
      foreach ($expected_security_releases as $expected_security_release) {
        $expected_url_version = str_replace('.', '-', $expected_security_release);
        $release_url = "https://example.com/$project_path_part-$expected_url_version-release";
        $assert_session->responseNotContains("https://example.com/$project_path_part-$expected_url_version.tar.gz");
        $expected_release_urls[] = $release_url;
        // Ensure the expected links are security links.
        $this->assertContains($release_url, $all_security_release_urls, "Release $release_url is a security release link.");
        $assert_session->linkByHrefExists($release_url);
      }
      // Ensure no other links are shown as security releases.
      $this->assertEquals([], array_diff($all_security_release_urls, $expected_release_urls));
    }
    else {
      // Ensure there were no security links.
      $this->assertEquals([], $all_security_release_urls);
      $assert_session->pageTextNotContains('Security update required!');
      if ($expected_update_message_type === static::UPDATE_AVAILABLE) {
        $assert_session->elementTextContains('css', $update_element_css_locator, 'Update available');
        $assert_session->elementTextNotContains('css', $update_element_css_locator, 'Up to date');
      }
      elseif ($expected_update_message_type === static::UPDATE_NONE) {
        $assert_session->elementTextNotContains('css', $update_element_css_locator, 'Update available');
        $assert_session->elementTextContains('css', $update_element_css_locator, 'Up to date');
      }
      else {
        $this->fail('Unexpected value for $expected_update_message_type: ' . $expected_update_message_type);
      }
    }
  }

  /**
   * Asserts that the update table text contains the specified text.
   *
   * @param string $text
   *   The expected text.
   *
   * @see \Behat\Mink\WebAssert::elementTextContains()
   */
  protected function assertUpdateTableTextContains(string $text) {
    $this->assertSession()
      ->elementTextContains('css', $this->updateTableLocator, $text);
  }

  /**
   * Asserts that the update table text does not contain the specified text.
   *
   * @param string $text
   *   The expected text.
   */
  protected function assertUpdateTableTextNotContains(string $text) {
    $this->assertSession()->elementTextNotContains('css', $this->updateTableLocator, $text);
  }

  /**
   * Asserts that the update table element HTML contains the specified text.
   *
   * @param string $text
   *   The expected text.
   *
   * @see \Behat\Mink\WebAssert::elementContains()
   */
  protected function assertUpdateTableElementContains(string $text) {
    $this->assertSession()
      ->elementContains('css', $this->updateTableLocator, $text);
  }

}
