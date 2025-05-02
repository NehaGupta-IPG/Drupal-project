<?php

declare(strict_types=1);

namespace Drupal\TestSite;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\node\Entity\Node;
use Drupal\comment\Entity\Comment;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Setup file used by TestSiteInstallTestScript.
 *
 * @see \Drupal\KernelTests\Scripts\TestSiteApplicationTest
 */
class TestSiteOliveroInstallTestScript implements TestSetupInterface, TestPreinstallInterface {

  /**
   * Generates PHP process to generate a theme from core's starterkit theme.
   *
   * @return \Symfony\Component\Process\Process
   *   The PHP process
   */
  private function generateThemeFromStarterkit(string $site_directory) : Process {
    $phpBinaryFinder = new PhpExecutableFinder();
    $phpBinaryPath = $phpBinaryFinder->find();

    $install_command = [
      $phpBinaryPath,
      'core/scripts/drupal',
      'generate-theme',
      'test_custom_theme',
      '--name=Test custom starterkit theme',
      '--description=Custom theme generated from a starterkit theme',
      '--starterkit=olivero',
      '--path=' . $site_directory . '/themes',
    ];
    $process = new Process($install_command, NULL, [
      'HTTP_USER_AGENT' => 'Drupal command line',
    ]);
    $process->setTimeout(60);
    return $process;
  }

  /**
   * {@inheritdoc}
   */
  public function setup() {

    // Install required module for the Olivero front page.
    $module_installer = \Drupal::service('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->install(['olivero_test']);
    $module_installer->install(['nightwatch_theme_install_utility']);

    // Install Olivero and set it as the default theme.
    $theme_installer = \Drupal::service('theme_installer');
    assert($theme_installer instanceof ThemeInstallerInterface);
    $theme_installer->install(['olivero'], TRUE);
    $system_theme_config = \Drupal::configFactory()->getEditable('system.theme');
    $system_theme_config->set('default', 'olivero')->save();

    // Create an article that will have no comments
    $article_no_comments = Node::create(['type' => 'article']);
    $article_no_comments->set('title', 'Article without comments');
    // Enable comments
    $article_no_comments->set('comment', 2);
    $article_no_comments->save();

    // Create an article that will have comments
    $article_with_comments = Node::create(['type' => 'article']);
    $article_with_comments->set('title', 'Article with comments');
    // Enable comments
    $article_with_comments->set('comment', 2);
    $article_with_comments->save();

    $values = [
      // These values are for the entity that you're creating the comment for,
      // not the comment itself.
      'entity_type' => 'node',
      'entity_id'   => 2,
      'field_name'  => 'comment',
      'uid' => 1,
      // These values are for the comment itself.
      'comment_type' => 'comment',
      'subject' => 'A comment',
      'comment_body' => 'Body of comment',
      // Whether the comment is 'approved' or not.
      'status' => 1,
    ];
    // Create comment entities out of our field values
    $comment1 = Comment::create($values);
    $comment1->save();

    $comment2 = Comment::create($values);
    $comment2->save();
  }

  /**
   * {@inheritdoc}
   */
  public function preinstall($db_prefix, $site_directory) {
    $process = $this->generateThemeFromStarterkit($site_directory);
    $process->mustRun();
  }

}
