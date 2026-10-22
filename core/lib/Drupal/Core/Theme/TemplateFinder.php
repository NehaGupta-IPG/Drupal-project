<?php

namespace Drupal\Core\Theme;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Searches the file system for templates the theme engine should load.
 */
class TemplateFinder {

  public function __construct(
    protected readonly ThemeHandlerInterface $themeHandler,
    protected readonly ThemeManagerInterface  $themeManager,
    protected readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Allows themes and/or theme engines to easily discover overridden templates.
   *
   * @param array $cache
   *   The existing cache of theme hooks to test against.
   * @param string $extension
   *   The extension that these templates will have.
   * @param string $path
   *   The path to search.
   */
  public function scan(array $cache, string $extension, string $path): array {
    $implementations = [];

    // Collect paths to all sub-themes grouped by base themes. These will be
    // used for filtering. This allows base themes to have sub-themes in its
    // folder hierarchy without affecting the base themes template discovery.
    $themePaths = [];
    foreach ($this->themeHandler->listInfo() as $themeInfo) {
      if (!empty($themeInfo->base_theme)) {
        $themePaths[$themeInfo->base_theme][$themeInfo->getName()] = $themeInfo->getPath();
      }
    }
    foreach ($themePaths as $baseTheme => $subthemes) {
      foreach ($subthemes as $subtheme => $subthemePath) {
        if (isset($themePaths[$subtheme])) {
          $themePaths[$baseTheme] = array_merge($themePaths[$baseTheme], $themePaths[$subtheme]);
        }
      }
    }
    $theme = $this->themeManager->getActiveTheme()->getName();
    $subthemePaths = $themePaths[$theme] ?? [];

    // Escape the periods in the extension.
    $regex = '/' . str_replace('.', '\.', $extension) . '$/';
    // Get a listing of all template files in the path to search.
    $files = [];
    if (is_dir($path)) {
      $files = $this->fileSystem->scanDirectory($path, $regex, ['key' => 'filename']);
    }

    // Find templates that implement registered theme hooks and include that in
    // what is returned so that the registry knows that the theme has this
    // implementation.
    foreach ($files as $template => $file) {
      // Ignore sub-theme templates for the current theme.
      if (!str_starts_with($file->uri, str_replace($subthemePaths, '', $file->uri))) {
        continue;
      }
      // Remove the extension from the filename.
      $template = str_replace($extension, '', $template);
      // Transform - in filenames to _ to match function naming scheme
      // for the purposes of searching.
      $hook = strtr($template, '-', '_');
      if (isset($cache[$hook])) {
        $implementations[$hook] = [
          'template' => $template,
          'path' => dirname($file->uri),
        ];
      }

      // Match templates based on the 'template' filename.
      foreach ($cache as $hook => $info) {
        if (isset($info['template'])) {
          if ($template === $info['template']) {
            $implementations[$hook] = [
              'template' => $template,
              'path' => dirname($file->uri),
            ];
          }
        }
      }
    }

    // Find templates that implement possible "suggestion" variants of registered
    // theme hooks and add those as new registered theme hooks. See
    // hook_theme_suggestions_alter() for more information about suggestions and
    // the use of 'pattern' and 'base hook'.
    $patterns = array_keys($files);
    foreach ($cache as $hook => $info) {
      $pattern = $info['pattern'] ?? ($hook . '__');
      if (!isset($info['base hook']) && !empty($pattern)) {
        // Transform _ in pattern to - to match file naming scheme
        // for the purposes of searching.
        $pattern = strtr($pattern, '_', '-');

        $matches = preg_grep('/^' . $pattern . '/', $patterns);
        if ($matches) {
          foreach ($matches as $match) {
            $file = $match;
            // Remove the extension from the filename.
            $file = str_replace($extension, '', $file);
            // Put the underscores back in for the hook name and register this
            // pattern.
            $arg_name = isset($info['variables']) ? 'variables' : 'render element';
            $implementations[strtr($file, '-', '_')] = [
              'template' => $file,
              'path' => dirname($files[$match]->uri),
              $arg_name => $info[$arg_name],
              'base hook' => $hook,
            ];
          }
        }
      }
    }
    return $implementations;
  }

}
