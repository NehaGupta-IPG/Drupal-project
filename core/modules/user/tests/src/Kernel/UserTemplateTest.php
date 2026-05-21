<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Component\Utility\Unicode;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests template output for user module.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserTemplateTest extends KernelTestBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'user_access_test'];

  /**
   * The renderer.
   */
  protected RendererInterface $renderer;

  /**
   * A user for testing.
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->renderer = $this->container->get('renderer');
    $this->user = User::create([
      'uid' => 2,
      'name' => $this->randomString(),
    ]);
  }

  /**
   * Tests whether the user can view user label.
   */
  public function testUsernameTemplateLabel(): void {
    $build = [
      '#theme' => 'username',
      '#account' => $this->user,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));
    $this->assertEscaped($this->user->getDisplayName());
  }

  /**
   * Tests whether the anonymous user name is always visible.
   *
   * Access control for label does not apply to non entities.
   */
  public function testUsernameTemplateLabelAnonymous(): void {
    $account = new UserSession(['name' => $this->randomString()]);
    $build = [
      '#theme' => 'username',
      '#account' => $account,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));
    $this->assertEscaped($account->getDisplayName());
  }

  /**
   * Tests whether the user cannot view user label.
   */
  public function testUsernameTemplateLabelAccessDenied(): void {
    $this->user
      // 'no_view_label' username disallows label viewing.
      ->setUsername('no_view_label')
      ->save();
    $build = [
      '#theme' => 'username',
      '#account' => $this->user,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));
    $this->assertEscaped((string) $this->t('- Restricted access -'), 'Account does not have permission to view user name.');
  }

  /**
   * Tests whether long user names are truncated.
   */
  public function testUsernameTemplateTruncated(): void {
    $username = $this->randomString(32);
    $username_truncated = Unicode::truncate($username, 15, FALSE, TRUE);
    $this->user
      ->setUsername($username)
      ->save();
    $build = [
      '#theme' => 'username',
      '#account' => $this->user,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));
    $this->assertEscaped($username_truncated, 'Truncated user name found.');
    $this->assertNoText($username, 'Full user name not found.');
  }

  /**
   * Tests whether long user names are truncated for anonymous accounts.
   */
  public function testUsernameTemplateTruncatedAnonymous(): void {
    $username = $this->randomString(32);
    $username_truncated = Unicode::truncate($username, 15, FALSE, TRUE);
    $account = new UserSession(['name' => $username]);
    $build = [
      '#theme' => 'username',
      '#account' => $account,
    ];
    $this->setRawContent($this->renderer->renderRoot($build));
    $this->assertEscaped($username_truncated, 'Anonymous truncated user name found.');
    $this->assertNoText($username, 'Anonymous full user name not found.');
  }

  /**
   * Tests the user name template is cached, and tags are present for user.
   */
  public function testUsernameTemplateCache(): void {
    // Save the user once so it generates cache tags for itself.
    $this->user->save();
    $this->user->testAccessAddCacheTags = [$this->randomMachineName()];

    $build['#cache']['keys'] = [$this->randomMachineName()];
    $result_a = $this->renderUserName($build, $this->user);
    $result_b = $this->renderUserName($build, $this->user);
    $this->assertEquals($result_a, $result_b, 'User name not updated since it was cached.');

    $result_a = $this->renderUserName([], $this->user);
    $result_b = $this->renderUserName([], $this->user);
    $this->assertNotEquals($result_a, $result_b, 'User name updated since it was not cached.');
  }

  /**
   * Render a user name with a random string.
   *
   * @param array $build
   *   The base element to build.
   * @param \Drupal\user\UserInterface $user
   *   A user entity.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered user name template.
   */
  protected function renderUserName(array $build, UserInterface $user): MarkupInterface {
    // Do not save the user, otherwise cache tags are invalidated.
    $build['#theme'] = 'username';
    $user->setUsername($this->randomMachineName());
    $build['#account'] = $user;
    $result = $this->renderer->renderRoot($build);

    // Ensure cache metadata from user entity and access control added.
    $expected_cache_tags = array_merge(
      $user->testAccessAddCacheTags,
      $user->getCacheTags()
    );
    $this->assertEqualsCanonicalizing($expected_cache_tags, $build['#cache']['tags'], 'Cache tags found for user');
    return $result;
  }

}
