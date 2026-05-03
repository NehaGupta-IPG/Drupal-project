<?php

declare(strict_types=1);

namespace Drupal\comment\Hook;

use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Help hook implementations for comment.
 */
class CommentHelpHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.comment':
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Comment module allows users to comment on site content, set commenting defaults and permissions, and moderate comments. For more information, see the <a href=":comment">online documentation for the Comment module</a>.', [':comment' => 'https://www.drupal.org/documentation/modules/comment']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Enabling commenting') . '</dt>';
        $output .= '<dd>' . $this->t('Comment functionality can be enabled for any entity sub-type (for example, a <a href=":content-type">content type</a>) by adding a <em>Comments</em> field on its <em>Manage fields page</em>. Adding or removing commenting for an entity through the user interface requires the <a href=":field_ui">Field UI</a> module to be installed, even though the commenting functionality works without it. For more information on fields and entities, see the <a href=":field">Field module help page</a>.', [
          ':content-type' => $this->moduleHandler->moduleExists('node') ? Url::fromRoute('entity.node_type.collection')->toString() : '#',
          ':field' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
          ':field_ui' => $this->moduleHandler->moduleExists('field_ui') ? Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString() : '#',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring commenting settings') . '</dt>';
        $output .= '<dd>' . $this->t('Commenting settings can be configured by editing the <em>Comments</em> field on the <em>Manage fields page</em> of an entity type if the <em>Field UI module</em> is installed. Configuration includes the label of the comments field, the number of comments to be displayed, and whether they are shown in threaded list. Commenting can be configured as: <em>Open</em> to allow new comments, <em>Closed</em> to view existing comments, but prevent new comments, or <em>Hidden</em> to hide existing comments and prevent new comments. Changing this configuration for an entity type will not change existing entity items.') . '</dd>';
        $output .= '<dt>' . $this->t('Overriding default settings') . '</dt>';
        $output .= '<dd>' . $this->t('Users with the appropriate permissions can override the default commenting settings of an entity type when they create an item of that type.') . '</dd>';
        $output .= '<dt>' . $this->t('Adding comment types') . '</dt>';
        $output .= '<dd>' . $this->t('Additional <em>comment types</em> can be created per entity sub-type and added on the <a href=":field">Comment types page</a>. If there are multiple comment types available you can select the appropriate one after adding a <em>Comments field</em>.', [
          ':field' => Url::fromRoute('entity.comment_type.collection')->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Approving and managing comments') . '</dt>';
        $output .= '<dd>' . $this->t('Comments from users who have the <em>Skip comment approval</em> permission are published immediately. All other comments are placed in the <a href=":comment-approval">Unapproved comments</a> queue, until a user who has permission to <em>Administer comments and comment settings</em> publishes or deletes them. Published comments can be bulk managed on the <a href=":admin-comment">Published comments</a> administration page. When a comment has no replies, it remains editable by its author, as long as the author has <em>Edit own comments</em> permission.', [
          ':comment-approval' => Url::fromRoute('comment.admin_approval')->toString(),
          ':admin-comment' => Url::fromRoute('comment.admin')->toString(),
        ]) . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'entity.comment_type.collection':
        $output = '<p>' . $this->t('This page provides a list of all comment types on the site and allows you to manage the fields, form and display settings for each.') . '</p>';
        return $output;
    }
    return NULL;
  }

}
