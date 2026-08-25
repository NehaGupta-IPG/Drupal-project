<?php

declare(strict_types=1);

namespace Drupal\Tests;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Mink;
use Behat\Mink\Selector\SelectorsHandler;
use Behat\Mink\Session;
use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Test\Form\KernelTestFormBuilder;
use Drupal\Core\Test\Session\KernelTestSessionManager;
use Drupal\Core\Test\Session\KernelTestSessionMiddleware;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * Provides UI helper methods using the HTTP kernel to make requests.
 *
 * This is for use by Kernel tests, with the following limitations:
 * - There is no logged in user. Use \Drupal\Tests\user\Traits\UserCreationTrait
 *   to set a current user.
 * - There is no active theme. To render forms or place blocks, a test must
 *   first install a theme and then set it as active.
 * - Session semantics differ from normal page requests. To enable session
 *   persistence, see self::alterSessionServices(), and to set the current user
 *   use self::setCurrentUserWithSession().
 * - Page caching modules will not work. See \Drupal\Tests\Traits\Core\Cache\PageCachePolicyTrait
 *   for how to set them up.
 * - To submit a form, see self::alterFormBuilder(). To submit a form as an
 *   authenticated user, session persistence with self::alterSessionServices()
 *   is also needed and the user must be logged in with
 *   self::setCurrentUserWithSession.
 */
trait HttpKernelUiHelperTrait {

  use BrowserHtmlDebugTrait;

  /**
   * Mink session manager.
   *
   * This is lazily initialized by the first call to self::drupalGet().
   */
  protected ?Mink $mink;

  /**
   * Sets up services to enable sessions with Kernel test HTTP requests.
   *
   * This should be called from an implementation of
   * \Drupal\Core\DependencyInjection\ServiceModifierInterface::alter().
   *
   * If using sessions, setting the current user must be done with
   * self::setCurrentUserWithSession().
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container to alter.
   */
  public function alterSessionServices(ContainerBuilder $container): void {
    // Alter session services that normally skip session creation on the CLI.
    $service_definition = $container->getDefinition('http_middleware.session');
    $service_definition->setClass(KernelTestSessionMiddleware::class);

    $service_definition = $container->getDefinition('session_manager');
    $service_definition->setClass(KernelTestSessionManager::class);
  }

  /**
   * Sets up form services to enable forms with Kernel test HTTP requests.
   *
   * This should be called from an implementation of
   * \Drupal\Core\DependencyInjection\ServiceModifierInterface::alter().
   *
   * @todo This can be deprecated and removed when
   * https://www.drupal.org/project/drupal/issues/2505339 is fixed.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container to alter.
   */
  public function alterFormBuilder(ContainerBuilder $container): void {
    // Alter the form builder.
    $service_definition = $container->getDefinition('form_builder');
    $service_definition->setClass(KernelTestFormBuilder::class);
  }

  /**
   * Switch the current logged in user in both the system and the session.
   *
   * This should be used instead of
   * \Drupal\Tests\user\Traits\UserCreationTrait::setCurrentUser() if session
   * support is required.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account object.
   *
   * @see self::alterSessionServices()
   */
  public function setCurrentUserWithSession(AccountInterface $account): void {
    // Reset the Mink browser session.
    $this->getSession()->reset();

    // Destroy the PHP session if there is one.
    if (isset($_SESSION)) {
      $this->container->get('session_manager')->destroy();
    }

    $this->container->get('current_user')->setAccount($account);

    if ($account->id()) {
      // Set the account ID into the session. This is the same as
      // user_login_finalize().
      $this->container->get('session')->set('uid', $account->id());
    }
  }

  /**
   * Retrieves a Drupal path.
   *
   * Requests are sent to the HTTP kernel.
   *
   * @param \Drupal\Core\Url|string $path
   *   The Drupal path to load into Mink controlled browser, as a string or a
   *   Url object. Relative paths (without a leading '/') are made absolute by
   *   prepending '/'. Query strings starting with '?' are resolved relative to
   *   the current page URL.
   * @param array $options
   *   (optional) Options to be forwarded to the URL generator. The 'absolute'
   *   option is not supported.
   * @param string[] $headers
   *   An array containing additional HTTP request headers, the array keys are
   *   the header names and the array values the header values. This is useful
   *   to set for example the "Accept-Language" header for requesting the page
   *   in a different language. Note that Mink's BrowserKitDriver normalizes
   *   header names to uppercase, while Symfony's HTTP classes normalize to
   *   lower case, which may cause a header to not be found with certain APIs.
   *
   * @return string
   *   The retrieved HTML string.
   *
   * @see \Drupal\Tests\BrowserTestBase::getHttpClient()
   */
  protected function drupalGet($path, array $options = [], array $headers = []): string {
    $session = $this->getSession();

    // Ensure relative paths are made absolute by prepending '/'. Query strings
    // (starting with '?') are left as-is so they resolve relative to the
    // current page URL.
    if (is_string($path) && !str_starts_with($path, '?') && !str_starts_with($path, '/')) {
      $path = '/' . $path;
    }

    if (is_object($path) || $options) {
      $path = $this->buildUrl($path, $options);
    }

    foreach ($headers as $header_name => $header_value) {
      assert(is_string($header_name));
      assert(!is_null($header_name));

      $session->setRequestHeader($header_name, $header_value);
    }

    $session->visit($path);

    $this->resetStatics();

    $out = $session->getPage()->getContent();
    $this->content = $out;

    if ($this->htmlOutputEnabled) {
      $html_output = 'GET request to: ' . $path;
      $html_output .= '<hr />' . $out;
      $html_output .= $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }

    // If $this->content exists, set it for the benefit of all the methods in
    // \Drupal\KernelTests\AssertContentTrait.
    if (property_exists($this, 'content')) {
      $this->content = $out;
    }

    return $out;
  }

  /**
   * Follows a link by complete name.
   *
   * Will click the first link found with this link text unless $index is
   * specified.
   *
   * If the link is not found, an assertion will fail, halting the test.
   *
   * @param string|\Stringable $label
   *   Text between the anchor tags.
   * @param int $index
   *   (optional) The index number for cases where multiple links have the same
   *   text. Defaults to 0.
   */
  protected function clickLink(string|\Stringable $label, int $index = 0): void {
    $label = (string) $label;
    $links = $this->getSession()->getPage()->findAll('named', ['link', $label]);
    $this->assertArrayHasKey($index, $links, 'The link ' . $label . ' was not found on the page.');

    // Use static::drupalGet() rather than the click() method on the element,
    // because that will not produce HTML debug output.
    $this->drupalGet($links[$index]->getAttribute('href'));
  }

  /**
   * Fills and submits a form.
   *
   * This is not named 'submitForm' as that prevents Kernel test classes from
   * behaving as form classes.
   *
   * @param array $edit
   *   Field data in an associative array. Changes the current input fields
   *   (where possible) to the values indicated.
   *
   *   A checkbox can be set to TRUE to be checked and should be set to FALSE to
   *   be unchecked.
   * @param string $submit
   *   Value of the submit button whose click is to be emulated. For example,
   *   'Save'. The processing of the request depends on this value. For example,
   *   a form may have one button with the value 'Save' and another button with
   *   the value 'Delete', and execute different code depending on which one is
   *   clicked.
   * @param string $form_html_id
   *   (optional) HTML ID of the form to be submitted. On some pages
   *   there are many identical forms, so just using the value of the submit
   *   button is not enough. For example: 'trigger-node-presave-assign-form'.
   *   Note that this is not the Drupal $form_id, but rather the HTML ID of the
   *   form, which is typically the same thing but with hyphens replacing the
   *   underscores.
   */
  protected function drupalSubmitForm(array $edit, $submit, $form_html_id = NULL): void {
    $assert_session = $this->assertSession();
    $submit_button = $assert_session->buttonExists($submit);
    // Check if button has a form attribute set.
    if ($form_id = $submit_button->getAttribute('form')) {
      $form = $assert_session->elementExists('xpath', "//form[@id='$form_id']");
      $action = $form->getAttribute('action');
    }
    // Get the form.
    elseif (isset($form_html_id)) {
      $form = $assert_session->elementExists('xpath', "//form[@id='$form_html_id']");
      $submit_button = $assert_session->buttonExists($submit, $form);
      $action = $form->getAttribute('action');
    }
    else {
      $form = $assert_session->elementExists('xpath', './ancestor::form', $submit_button);
      $action = $form->getAttribute('action');
    }

    // Edit the form values.
    foreach ($edit as $name => $value) {
      $field = $assert_session->fieldExists($name, $form);

      $value = match ($field->getAttribute('type')) {
        // Provide support for the values '1' and '0' for checkboxes instead of
        // TRUE and FALSE.
        // @todo Get rid of supporting 1/0 by converting all tests cases using
        // this to boolean values.
        'checkbox' => (bool) $value,
        // Mink only allows strings for text, number and radio button values.
        'text', 'number', 'radio' => (string) $value,
        default => $value,
      };

      $field->setValue($value);
    }

    // Submit form.
    $submit_button->press();

    $this->resetStatics();

    // Log only for WebDriverTestBase tests because for tests using
    // DrupalTestBrowser we log with ::getResponseLogHandler.
    if ($this->htmlOutputEnabled) {
      $out = $this->getSession()->getPage()->getContent();
      $html_output = 'POST request to: ' . $action .
        '<hr />Ending URL: ' . $this->getSession()->getCurrentUrl();
      $html_output .= '<hr />' . $out;
      $html_output .= $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
  }

  /**
   * Resets variables which would normally be fresh in a new request.
   *
   * This must be called after each request to the HttpKernelBrowser browser.
   */
  protected function resetStatics(): void {
    Html::resetSeenIds();
    \Drupal::service('entity.memory_cache')->deleteAll();
  }

  /**
   * Builds a URL from a system path or a URL object.
   *
   * @param string|\Drupal\Core\Url $path
   *   A system path or a URL object.
   * @param array $options
   *   Options to be passed to Url::fromUri(). If $path is a Url object, any
   *   options it has will take priority over this parameter.
   *
   * @return string
   *   A URL string.
   */
  protected function buildUrl(Url|string $path, array $options = []): string {
    assert(empty($options['absolute']), 'Absolute URLs are not usable with drupalGet().');

    if ($path instanceof Url) {
      if ($options) {
        $url_options = $path->getOptions();
        $options = $url_options + $options;
        $path->setOptions($options);
      }
      return $path->toString();
    }

    $uri = $path === '<front>' ? 'base:/' : 'base:/' . $path;
    return Url::fromUri($uri, $options)->toString();
  }

  /**
   * Returns Mink session.
   *
   * The lazily initializes Mink the first time it is called.
   *
   * @param string $name
   *   (optional) Name of the session. Defaults to the active session.
   *
   * @return \Behat\Mink\Session
   *   The active Mink session object.
   */
  public function getSession($name = NULL): Session {
    // Lazily initialize the Mink session. We do this because unlike Browser
    // tests where there should definitely be requests made, this is not
    // necessarily the case with Kernel tests.
    if (!isset($this->mink)) {
      $this->initMink();
      // Set up the browser test output file.
      $this->initBrowserOutputFile();
    }

    return $this->mink->getSession($name);
  }

  /**
   * Initializes Mink sessions.
   *
   * Helper for static::getSession().
   *
   * This should be called after changes to the service container, such as
   * installing modules, as otherwise it will contain a reference to a stale
   * container.
   */
  protected function initMink(): void {
    $driver = $this->getDefaultDriverInstance();

    $selectors_handler = new SelectorsHandler([
      'hidden_field_selector' => new HiddenFieldSelector(),
    ]);
    $session = new Session($driver, $selectors_handler);
    $this->mink = new Mink();
    $this->mink->registerSession('default', $session);
    $this->mink->setDefaultSessionName('default');
  }

  /**
   * Gets an instance of the default Mink driver.
   *
   * @return \Behat\Mink\Driver\DriverInterface
   *   Instance of default Mink driver.
   *
   * @throws \InvalidArgumentException
   *   When provided default Mink driver class can't be instantiated.
   */
  protected function getDefaultDriverInstance(): DriverInterface {
    $http_kernel = $this->container->get('http_kernel');
    $browserkit_client = new HttpKernelBrowser($http_kernel);
    return new BrowserKitDriver($browserkit_client);
  }

  /**
   * Returns WebAssert object.
   *
   * @param string $name
   *   (optional) Name of the session. Defaults to the active session.
   *
   * @return \Drupal\Tests\WebAssert
   *   A new web-assert option for asserting the presence of elements with.
   */
  public function assertSession($name = NULL): WebAssert {
    $this->addToAssertionCount(1);
    return new WebAssert($this->getSession($name));
  }

  /**
   * Performs an xpath search on the contents of the internal browser.
   *
   * The search is relative to the root element (HTML tag normally) of the page.
   *
   * This method is identical to \Drupal\Tests\BrowserTestBase::xpath() and
   * should be used when converting Browser tests to Kernel tests, as
   * \Drupal\KernelTests\AssertContentTrait::xpath() which Kernel tests use does
   * not have the same return type.
   *
   * @param string $xpath
   *   The xpath string to use in the search.
   * @param array $arguments
   *   An array of arguments with keys in the form ':name' matching the
   *   placeholders in the query. The values may be either strings or numeric
   *   values.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   *   The list of elements matching the xpath expression.
   */
  protected function getNodeElementsByXpath($xpath, array $arguments = []): array {
    $xpath = $this->assertSession()->buildXPathQuery($xpath, $arguments);
    return $this->getSession()->getPage()->findAll('xpath', $xpath);
  }

  /**
   * Gets the current URL from the browser.
   *
   * @see \Drupal\Tests\UiHelperTrait::getUrl
   */
  protected function getUrl(): string {
    return $this->getSession()->getCurrentUrl();
  }

  /**
   * Rebuilds the container.
   *
   * Provided for compatibility with traits that call $this->rebuildContainer(),
   * such as those shared with functional tests.
   *
   * @see \Drupal\Core\Test\FunctionalTestSetupTrait::rebuildContainer
   */
  protected function rebuildContainer(): void {
    \Drupal::service('kernel')->rebuildContainer();
    $this->container = \Drupal::getContainer();
  }

}
