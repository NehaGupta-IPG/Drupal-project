<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\Kernel;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\treegrid\TreegridCsrfToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the CSRF token placeholder that treegrid consumers render.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridCsrfTokenTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'treegrid',
  ];

  /**
   * An authenticated user gets a token valid for the request header check.
   */
  public function testRenderedTokenValidatesForAuthenticatedUser(): void {
    $this->setUpCurrentUser();
    $output = $this->renderPlaceholderInAttribute();

    $token = $this->extractAttribute($output);
    $this->assertNotSame('', $token);
    $this->assertTrue(
      $this->container->get('csrf_token')->validate($token, CsrfRequestHeaderAccessCheck::TOKEN_KEY),
    );
  }

  /**
   * The build varies by whether the user is authenticated.
   */
  public function testBuildHasAuthenticatedRolesCacheContext(): void {
    $build = [];
    TreegridCsrfToken::addPlaceholder($build);

    $this->assertContains('user.roles:authenticated', $build['#cache']['contexts']);
  }

  /**
   * An anonymous user gets an empty value and no CSRF seed in their session.
   */
  public function testAnonymousUserGetsEmptyValueAndNoSeed(): void {
    $this->setCurrentUser(new AnonymousUserSession());
    $output = $this->renderPlaceholderInAttribute();

    $this->assertSame('', $this->extractAttribute($output));
    $this->assertNull($this->container->get('session_manager.metadata_bag')->getCsrfTokenSeed());
  }

  /**
   * Renders a template that prints the placeholder inside an attribute.
   */
  private function renderPlaceholderInAttribute(): string {
    $build = [
      '#type' => 'inline_template',
      '#template' => '<div data-treegrid-csrf-token="{{ token }}"></div>',
    ];
    $build['#context']['token'] = TreegridCsrfToken::addPlaceholder($build);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * Reads the token attribute's value out of rendered output.
   */
  private function extractAttribute(string $output): string {
    $this->assertSame(1, preg_match('/data-treegrid-csrf-token="([^"]*)"/', $output, $matches), $output);
    return $matches[1];
  }

}
