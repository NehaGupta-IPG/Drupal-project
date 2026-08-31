<?php

namespace Drupal\Core\Extension;

/**
 * Interface for classes that parses Drupal's info.yml files.
 */
interface InfoParserInterface {

  /**
   * Parses Drupal module, theme and profile .info.yml files.
   *
   * Info files are NOT for placing arbitrary theme and module-specific
   * settings. Use Config::get() and Config::set()->save() for that. Info files
   * are formatted as YAML. If the 'version' key is set to 'VERSION' in any info
   * file, then the value will be substituted with the current version of Drupal
   * core.
   *
   * Information stored in all .info.yml files:
   * - name: The real name of the module for display purposes. (Required)
   * - description: A brief description of the module.
   * - type: whether it is for a module or theme. (Required)
   * - core_version_requirement: Specifies the compatible version or versions of
   *   Drupal core. For example, "9.3 || 9.4" means compatibility with Drupal
   *   9.3 and 9.4; ">=9" means compatible with Drupal 9, 10 and later versions,
   *   "<=9" means compatible with Drupal 8 and 9. (Required)
   * - lifecycle: [experimental|stable|deprecated|obsolete]. A description of
   *   the current phase in the lifecycle of the module, theme or profile.
   *
   * Information stored in a module .info.yml file:
   * - dependencies: An array of dependency strings. Each is in the form
   *   'project:module (versions)'; with the following meanings:
   *   - project: (optional) Project shortname, recommended to ensure
   *     uniqueness, if the module is part of a project hosted on drupal.org.
   *     If omitted, also omit the : that follows. The project name is currently
   *     ignored by Drupal core but is used for automated testing.
   *   - module: (required) Module shortname within the project.
   *   - versions: (optional) Version information, consisting of one or more
   *     comma-separated operator/value pairs or simply version numbers, which
   *     can contain "x" as a wildcard. Examples: (>=8.22, <8.28), (8.x-3.x).
   * - package: The name of the package of modules this module belongs to.
   *
   * See node.info.yml for an example of a module .info.yml file.
   *
   * Information stored in a theme .info.yml file:
   * - name: The name of the theme.
   * - type: The type of the theme; typically 'theme'.
   * - description: A description of the theme.
   * - package: The package name to group themes in the admin UI.
   * - alt text: Alt text for the screenshot image, providing a description for
   *   accessibility.
   * - version: The version of the theme.
   * - core_version_requirement: The required core version.
   * - base theme: Name of a base theme, if applicable.
   * - engine: Theme engine; typically 'twig'.
   * - regions: Defines regions for the theme.
   * - libraries: Specifies the names of CSS and JavaScript libraries to be
   *   loaded globally by the theme.
   * - libraries-override: Overrides for libraries defined by modules or other themes.
   * - libraries-extend: Extensions for libraries defined by modules or other themes.
   * - regions_hidden: Regions that are hidden.
   *
   * See olivero.info.yml for an example of a theme .info.yml file.
   *
   * For information stored in a profile .info.yml file see
   * install_profile_info().
   *
   * @param string $filename
   *   The file we are parsing. Accepts file with relative or absolute path.
   *
   * @return array
   *   The info array.
   *
   * @throws \Drupal\Core\Extension\InfoParserException
   *   Exception thrown if there is a parsing error or the .info.yml file does
   *   not contain a required key.
   *
   * @see install_profile_info()
   */
  public function parse($filename);

}
