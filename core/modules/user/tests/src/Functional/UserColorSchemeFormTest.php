<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Core\EventSubscriber\ColorSchemeCookieSubscriber;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the color scheme element on the account form.
 *
 * The element is offered or disabled depending on whether the site theme and
 * the administration theme declare 'supports color scheme', and on whether the
 * account being edited may use the administration theme. Saving the form hands
 * the new preference to the cookie that carries it to the client.
 *
 * @see \Drupal\user\AccountForm::colorSchemeElementProperties()
 * @see \Drupal\user\Hook\ColorSchemeHooks::userUpdate()
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserColorSchemeFormTest extends BrowserTestBase {

  /**
   * A theme that declares 'supports color scheme'.
   */
  const SUPPORTING_THEME = 'test_color_scheme_theme';

  /**
   * A theme that does not declare 'supports color scheme'.
   */
  const PLAIN_THEME = 'stark';

  /**
   * Description used when both themes honor the preference.
   */
  const DESCRIPTION_BOTH = 'System follows your operating system or browser setting. Applies wherever your site’s theme supports it.';

  /**
   * Description used when only the site theme honors the preference.
   */
  const DESCRIPTION_SITE_ONLY = 'System follows your operating system or browser setting. The site follows this setting, administrative pages do not.';

  /**
   * Description used when only the administration theme honors the preference.
   */
  const DESCRIPTION_ADMIN_ONLY = 'System follows your operating system or browser setting. Administrative pages follow this setting, the rest of the site does not.';

  /**
   * Description used when the account may not use the administration theme.
   */
  const DESCRIPTION_NO_ADMIN_ACCESS = 'This setting has no effect on your account. Only administration pages support it, and this account does not use the administration theme.';

  /**
   * Description used when neither theme honors the preference.
   */
  const DESCRIPTION_NONE = 'This setting has no effect. Neither the site nor the administration pages support it.';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('theme_installer')->install([self::SUPPORTING_THEME]);
  }

  /**
   * Tests the decision tree on the account edit form.
   *
   * @param string $default_theme
   *   The theme to configure as the site theme.
   * @param string $admin_theme
   *   The theme to configure as the administration theme.
   * @param bool $may_use_admin_theme
   *   TRUE to give the edited account the 'view the administration theme'
   *   permission.
   * @param bool $expected_disabled
   *   TRUE if the element is expected to be disabled.
   * @param string $expected_description
   *   The description the element is expected to carry.
   */
  #[DataProvider('providerDecisionTree')]
  public function testDecisionTree(string $default_theme, string $admin_theme, bool $may_use_admin_theme, bool $expected_disabled, string $expected_description): void {
    $this->setThemes($default_theme, $admin_theme);

    $account = $this->drupalCreateUser($may_use_admin_theme ? ['view the administration theme'] : []);
    $this->drupalLogin($account);

    $this->drupalGet($account->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertColorSchemeElement($expected_disabled, $expected_description);
  }

  /**
   * Provides test cases for ::testDecisionTree().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function providerDecisionTree(): array {
    return [
      'neither theme supports' => [
        self::PLAIN_THEME, self::PLAIN_THEME, TRUE, TRUE, self::DESCRIPTION_NONE,
      ],
      'both themes support' => [
        self::SUPPORTING_THEME, self::SUPPORTING_THEME, TRUE, FALSE, self::DESCRIPTION_BOTH,
      ],
      'site theme supports' => [
        self::SUPPORTING_THEME, self::PLAIN_THEME, TRUE, FALSE, self::DESCRIPTION_SITE_ONLY,
      ],
      // The administration theme is irrelevant while the site theme honors the
      // preference, so the permission does not change the outcome.
      'site theme supports, account may not use admin theme' => [
        self::SUPPORTING_THEME, self::PLAIN_THEME, FALSE, FALSE, self::DESCRIPTION_SITE_ONLY,
      ],
      'admin theme supports, account may use it' => [
        self::PLAIN_THEME, self::SUPPORTING_THEME, TRUE, FALSE, self::DESCRIPTION_ADMIN_ONLY,
      ],
      'admin theme supports, account may not use it' => [
        self::PLAIN_THEME, self::SUPPORTING_THEME, FALSE, TRUE, self::DESCRIPTION_NO_ADMIN_ACCESS,
      ],
    ];
  }

  /**
   * Tests that an empty administration theme falls back to the site theme.
   *
   * @param string $default_theme
   *   The theme to configure as the site theme.
   * @param bool $expected_disabled
   *   TRUE if the element is expected to be disabled.
   * @param string $expected_description
   *   The description the element is expected to carry.
   */
  #[DataProvider('providerEmptyAdminTheme')]
  public function testEmptyAdminTheme(string $default_theme, bool $expected_disabled, string $expected_description): void {
    // An empty 'admin' value means administration pages use the site theme, so
    // only the 'neither' and 'both' outcomes can occur.
    $this->setThemes($default_theme, '');

    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $this->drupalGet($account->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertColorSchemeElement($expected_disabled, $expected_description);
  }

  /**
   * Provides test cases for ::testEmptyAdminTheme().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function providerEmptyAdminTheme(): array {
    return [
      'site theme supports' => [self::SUPPORTING_THEME, FALSE, self::DESCRIPTION_BOTH],
      'site theme does not support' => [self::PLAIN_THEME, TRUE, self::DESCRIPTION_NONE],
    ];
  }

  /**
   * Tests that the permission is checked for the account being edited.
   */
  public function testPermissionAppliesToEditedAccount(): void {
    $this->setThemes(self::PLAIN_THEME, self::SUPPORTING_THEME);

    // The account filling in the form may use the administration theme, the
    // account being edited may not.
    $this->drupalLogin($this->drupalCreateUser([
      'administer users',
      'view the administration theme',
    ]));
    $this->drupalGet($this->drupalCreateUser()->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertColorSchemeElement(TRUE, self::DESCRIPTION_NO_ADMIN_ACCESS);

    // The reverse: the account being edited may use the administration theme,
    // the account filling in the form may not.
    $this->drupalLogin($this->drupalCreateUser(['administer users']));
    $edited = $this->drupalCreateUser(['view the administration theme']);
    $this->drupalGet($edited->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertColorSchemeElement(FALSE, self::DESCRIPTION_ADMIN_ONLY);
  }

  /**
   * Tests that a disabled element keeps the stored preference.
   */
  public function testDisabledElementKeepsStoredValue(): void {
    $this->setThemes(self::PLAIN_THEME, self::PLAIN_THEME);

    $account = $this->drupalCreateUser();
    $account->set('color_scheme', 'dark')->save();
    $this->drupalLogin($account);

    $this->drupalGet($account->toUrl('edit-form'));
    $this->assertColorSchemeElement(TRUE, self::DESCRIPTION_NONE);

    // The element is disabled, not removed, so the radio for the stored value
    // is still checked and saving the form does not change the preference.
    $this->assertSession()->elementsCount('xpath', '//input[@name="color_scheme" and @value="dark" and @checked]', 1);
    $this->submitForm([], 'Save');
    $this->assertSession()->statusCodeEquals(200);

    \Drupal::entityTypeManager()->getStorage('user')->resetCache([$account->id()]);
    $this->assertSame('dark', User::load($account->id())->get('color_scheme')->value);
  }

  /**
   * Tests that saving the form writes the preference to the cookie.
   *
   * The current user is built from the session at the start of the request, so
   * it still holds the previous preference while the save is processed.
   * hook_user_update() hands the saved value over, so the response to the save
   * already carries it and the page that follows the save uses it.
   *
   * @see \Drupal\user\Hook\ColorSchemeHooks::userUpdate()
   * @see \Drupal\Core\EventSubscriber\ColorSchemeCookieSubscriber::onResponse()
   */
  public function testSavingWritesTheCookie(): void {
    // A disabled element submits nothing, so the element has to be offered.
    $this->setThemes(self::SUPPORTING_THEME, self::SUPPORTING_THEME);

    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    // The account starts out on 'auto', which is what every page renders, so
    // there is nothing to carry to the client yet.
    $this->assertNull($this->getColorSchemeCookie());

    foreach (['dark', 'light'] as $color_scheme) {
      $this->drupalGet($account->toUrl('edit-form'));
      $this->submitForm(['color_scheme' => $color_scheme], 'Save');
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSame($color_scheme, $this->getColorSchemeCookie());
    }

    // 'auto' is expressed by the absence of the cookie, so saving it removes
    // the cookie again.
    $this->drupalGet($account->toUrl('edit-form'));
    $this->submitForm(['color_scheme' => 'auto'], 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertNull($this->getColorSchemeCookie());
  }

  /**
   * Tests the registration form, where the account has no roles yet.
   */
  public function testRegistrationForm(): void {
    $this->setThemes(self::PLAIN_THEME, self::SUPPORTING_THEME);
    $this->config('user.settings')
      ->set('register', UserInterface::REGISTER_VISITORS)
      ->save();

    $this->drupalGet('user/register');
    $this->assertSession()->statusCodeEquals(200);
    // The account does not exist yet, so the roles it will hold are unknown. A
    // permission check would evaluate the anonymous role, which says nothing
    // about the account being created, so the element is offered.
    $this->assertColorSchemeElement(FALSE, self::DESCRIPTION_ADMIN_ONLY);
  }

  /**
   * Returns the color scheme cookie the browser holds.
   *
   * @return string|null
   *   The value of the cookie, or NULL if the browser holds no such cookie.
   */
  protected function getColorSchemeCookie(): ?string {
    return $this->getSession()->getCookie(ColorSchemeCookieSubscriber::COOKIE_NAME);
  }

  /**
   * Configures the site theme and the administration theme.
   *
   * @param string $default_theme
   *   The theme to configure as the site theme.
   * @param string $admin_theme
   *   The theme to configure as the administration theme. An empty string
   *   means administration pages use the site theme.
   */
  protected function setThemes(string $default_theme, string $admin_theme): void {
    $this->config('system.theme')
      ->set('default', $default_theme)
      ->set('admin', $admin_theme)
      ->save();
  }

  /**
   * Asserts the state and the description of the color scheme element.
   *
   * @param bool $disabled
   *   TRUE if the element is expected to be disabled.
   * @param string $description
   *   The description the element is expected to carry. The descriptions of
   *   the other outcomes are asserted to be absent.
   */
  protected function assertColorSchemeElement(bool $disabled, string $description): void {
    $this->assertSession()->elementsCount('xpath', '//input[@name="color_scheme"]', 3);
    $this->assertSession()->elementsCount('xpath', '//input[@name="color_scheme" and @disabled]', $disabled ? 3 : 0);
    $this->assertSession()->pageTextContains($description);

    $others = [
      self::DESCRIPTION_BOTH,
      self::DESCRIPTION_SITE_ONLY,
      self::DESCRIPTION_ADMIN_ONLY,
      self::DESCRIPTION_NO_ADMIN_ACCESS,
      self::DESCRIPTION_NONE,
    ];
    foreach (array_diff($others, [$description]) as $other) {
      $this->assertSession()->pageTextNotContains($other);
    }
  }

}
