# Olivero Starterkit Theme

## How to use the Olivero Starterkit

To generate a new theme from Olivero using the starterkit/theme-generation
script, run the following from Drupal's installation root:

```sh
php core/scripts/drupal generate-theme new_theme_name --starterkit olivero
```

Additionally, you can create the theme's human-readable name and it description
with two optional arguments:

```sh
php core/scripts/drupal generate-theme new_theme_name
  --starterkit olivero
  --name "New Theme Name"
  --description "Custom theme generated from Drupal's Olivero theme"
```

This script will copy over all the files from the Olivero theme, and replace
instances of Olivero's machine name and label with the strings you provide.

## Customizing CSS

Your new theme should look and function identically to Olivero out of the box,
but you may wish to change the styles to suit your needs. Olivero's styles are
written using PostCSS, which is installed and configured by Drupal core, and
allows CSS authors to write modern CSS while still supporting browsers that have
not fully implemented the newest methodologies.

As part of the `generate-theme` command, the necessary package.json dependencies
and scripts files are copied over for you. Simply install the dependencies and
then run either the `build:css` command to compile the assets once or the
`watch:css` command to re-compile the assets every time a .pcss.css file is
changed.

```js
yarn install // install the dependencies

yarn build:css // compile PostCSS once

yarn watch:css // compile PostCSS per save
```

## Reporting Starterkit Bugs

Should you encounter a bug while generating a new theme,
[create a new issue](https://www.drupal.org/node/add/project-issue/drupal), and
be sure to select the correct version of Drupal Core, as well as "Olivero theme"
as the component.

## Additional Information

**Starterkit is for generating new themes** that include
reasonably un-opinionated templates and styles that eliminate much of the
the initial work required to create a theme.

Starterkit is the recommended approach for creating new themes. For more
information, consult the
[Starterkit documentation on Drupal.org](https://www.drupal.org/docs/core-modules-and-themes/core-themes/starterkit-theme).
