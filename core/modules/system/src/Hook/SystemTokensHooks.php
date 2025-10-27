<?php

namespace Drupal\system\Hook;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for system.
 */
class SystemTokensHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $types['site'] = [
      'name' => $this->t("Site information"),
      'description' => $this->t("Tokens for site-wide settings and other global information."),
    ];
    $types['site-logo'] = [
      'name' => $this->t('Site logo'),
      'description' => $this->t('Tokens related to the site logo.'),
      'needs-data' => 'site',
    ];
    $types['site-logo-properties'] = [
      'name' => $this->t('Site logo properties'),
      'description' => $this->t('Tokens for site logo properties.'),
      'needs-data' => 'site-logo',
    ];
    $types['date'] = ['name' => $this->t("Dates"), 'description' => $this->t("Tokens related to times and dates.")];
    // Site-wide global tokens.
    $site['name'] = ['name' => $this->t("Name"), 'description' => $this->t("The name of the site.")];
    $site['slogan'] = ['name' => $this->t("Slogan"), 'description' => $this->t("The slogan of the site.")];
    $site['mail'] = [
      'name' => $this->t("Email"),
      'description' => $this->t("The administrative email address for the site."),
    ];
    $site['base-url'] = [
      'name' => $this->t("Base URL"),
      'description' => $this->t("The base URL of the site, currently: @base_url", [
        '@base_url' => \Drupal::service('router.request_context')->getCompleteBaseUrl(),
      ]),
    ];
    $site['base-path'] = [
      'name' => $this->t("Base path"),
      'description' => $this->t("The base path of the site, currently: @base_path", [
        '@base_path' => \Drupal::request()->getBasePath(),
      ]),
    ];
    $site['url'] = [
      'name' => $this->t("URL"),
      'description' => $this->t("The URL of the site's front page with the language prefix, if it exists."),
    ];
    $site['url-brief'] = [
      'name' => $this->t("URL (brief)"),
      'description' => $this->t("The URL of the site's front page without the protocol."),
    ];
    $site['login-url'] = [
      'name' => $this->t("Login page"),
      'description' => $this->t("The URL of the site's login page."),
    ];

    // The [site:logo] token renders the active theme logo. Chained tokens can
    // be used for rendering specific theme logos or logo properties (URLs)
    // instead.
    $site['logo'] = [
      'name' => $this->t('Logo'),
      'description' => $this->t('The logo of the active theme. Note that the theme may be different when this is used in the administrative interface.'),
      'type' => 'site-logo',
    ];

    // Tokens for a specific theme logo.
    $site_logo['active-theme'] = [
      'name' => $this->t('Active theme'),
      'description' => $this->t('The logo of the active theme. Note that the theme may be different when this is used in the administrative interface.'),
      'type' => 'site-logo-properties',
    ];
    $site_logo['default-theme'] = [
      'name' => $this->t('Default theme'),
      'description' => $this->t('The logo that is configured for the default theme.'),
      'type' => 'site-logo-properties',
    ];
    // Obtain a list of installed themes and make tokens from them.
    $themes = \Drupal::service('theme_handler')->listInfo();
    foreach ($themes as $theme => $info) {
      if (empty($info->info['hidden'])) {
        $site_logo['theme-' . $theme] = [
          'name' => $this->t('@theme', ['@theme' => $info->info['name']]),
          'description' => $this->t('The logo that is configured for the %theme theme.', [
            '%theme' => $info->info['name'],
          ]),
          'type' => 'site-logo-properties',
        ];
      }
    }

    // Tokens for individual properties for logos.
    $site_logo_properties['url'] = [
      'name' => $this->t('URL'),
      'description' => $this->t('The URL of the logo.'),
    ];

    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    // Date related tokens.
    $request_time = \Drupal::time()->getRequestTime();
    $date['short'] = [
      'name' => $this->t("Short format"),
      'description' => $this->t("The current date in 'short' format. (%date)", [
        '%date' => $date_formatter->format($request_time, 'short'),
      ]),
    ];
    $date['medium'] = [
      'name' => $this->t("Medium format"),
      'description' => $this->t("The current date in 'medium' format. (%date)", [
        '%date' => $date_formatter->format($request_time, 'medium'),
      ]),
    ];
    $date['long'] = [
      'name' => $this->t("Long format"),
      'description' => $this->t("The current date in 'long' format. (%date)", [
        '%date' => $date_formatter->format($request_time, 'long'),
      ]),
    ];
    $date['custom'] = [
      'name' => $this->t("Custom format"),
      'description' => $this->t('The current date in a custom format. See <a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters">the PHP documentation</a> for details.'),
    ];
    $date['since'] = [
      'name' => $this->t("Time-since"),
      'description' => $this->t("The current date in 'time-since' format. (%date)", [
        '%date' => $date_formatter->formatTimeDiffSince($request_time - 360),
      ]),
    ];
    $date['raw'] = [
      'name' => $this->t("Raw timestamp"),
      'description' => $this->t("The current date in UNIX timestamp format (%date)", [
        '%date' => $request_time,
      ]),
    ];
    return [
      'types' => $types,
      'tokens' => [
        'site' => $site,
        'date' => $date,
        'site-logo' => $site_logo,
        'site-logo-properties' => $site_logo_properties,
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $token_service = \Drupal::token();
    $url_options = ['absolute' => TRUE];
    if (isset($options['langcode'])) {
      $url_options['language'] = \Drupal::languageManager()->getLanguage($options['langcode']);
      $langcode = $options['langcode'];
    }
    else {
      $langcode = NULL;
    }
    $replacements = [];
    if ($type == 'site') {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'name':
            $config = \Drupal::config('system.site');
            $bubbleable_metadata->addCacheableDependency($config);
            $site_name = $config->get('name');
            $replacements[$original] = $site_name;
            break;

          case 'slogan':
            $config = \Drupal::config('system.site');
            $bubbleable_metadata->addCacheableDependency($config);
            $slogan = $config->get('slogan');
            $build = ['#markup' => $slogan];
            // @todo Fix in https://www.drupal.org/node/2577827
            $replacements[$original] = \Drupal::service('renderer')->renderInIsolation($build);
            break;

          case 'mail':
            $config = \Drupal::config('system.site');
            $bubbleable_metadata->addCacheableDependency($config);
            $replacements[$original] = $config->get('mail');
            break;

          case 'base-url':
            $bubbleable_metadata->addCacheContexts(['url.site']);
            $replacements[$original] = \Drupal::service('router.request_context')->getCompleteBaseUrl();
            break;

          case 'base-path':
            $bubbleable_metadata->addCacheContexts(['url.site']);
            $replacements[$original] = \Drupal::request()->getBasePath();
            break;

          case 'url':
            /** @var \Drupal\Core\GeneratedUrl $result */
            $result = Url::fromRoute('<front>', [], $url_options)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;

          case 'url-brief':
            /** @var \Drupal\Core\GeneratedUrl $result */
            $result = Url::fromRoute('<front>', [], $url_options)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = preg_replace(['!^https?://!', '!/$!'], '', $result->getGeneratedUrl());
            break;

          case 'login-url':
            /** @var \Drupal\Core\GeneratedUrl $result */
            $result = Url::fromRoute('user.page', [], $url_options)->toString(TRUE);
            $bubbleable_metadata->addCacheableDependency($result);
            $replacements[$original] = $result->getGeneratedUrl();
            break;

          case 'logo':
            $replacements[$original] = $this->getLogoRendered('active-theme', $bubbleable_metadata);
            break;
        }
      }
      if ($logo_tokens = $token_service->findWithPrefix($tokens, 'logo')) {
        $replacements += $token_service->generate('site-logo', $logo_tokens, [], $options, $bubbleable_metadata);
      }
    }
    elseif ($type == 'site-logo') {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'active-theme':
          case 'default-theme':
            $replacements[$original] = $this->getLogoRendered($name, $bubbleable_metadata);
            break;
        }
      }

      if ($logo_tokens = $token_service->findWithPrefix($tokens, 'active-theme')) {
        $replacements += $token_service->generate('site-logo-properties', $logo_tokens, ['theme' => 'active-theme'], $options, $bubbleable_metadata);
      }
      if ($logo_tokens = $token_service->findWithPrefix($tokens, 'default-theme')) {
        $replacements += $token_service->generate('site-logo-properties', $logo_tokens, ['theme' => 'default-theme'], $options, $bubbleable_metadata);
      }

      // List installed themes.
      $themes = \Drupal::service('theme_handler')->listInfo();
      $installed_themes = array_keys($themes);

      // Detect direct tokens ([site:logo:theme-olivero]).
      foreach ($tokens as $name => $original) {
        // Strip 'theme-' to get the theme name, but do not strip
        // 'active-theme'.
        $name = str_starts_with($name, 'theme-') ? substr($name, 6) : $name;
        if (in_array($name, $installed_themes)) {
          $replacements[$original] = $this->getLogoRendered($name, $bubbleable_metadata);
        }
      }

      // Detect chained tokens ([site:logo:theme-olivero:?]).
      foreach ($installed_themes as $installed_theme) {
        if ($created_tokens = $token_service->findWithPrefix($tokens, 'theme-' . $installed_theme)) {
          $replacements += $token_service->generate('site-logo-properties', $created_tokens, ['theme' => $installed_theme], $options, $bubbleable_metadata);
        }
      }
    }
    elseif ($type == 'site-logo-properties' && !empty($data['theme'])) {
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'url':
          case 'url:absolute':
            $logo_path = $this->getLogoPath($data['theme'], $bubbleable_metadata);
            $replacements[$original] = $logo_path ? Url::fromUserInput($logo_path, ['absolute' => TRUE])->toString() : '';
            break;

          case 'url:relative':
            $logo_path = $this->getLogoPath($data['theme'], $bubbleable_metadata);
            $replacements[$original] = $logo_path ? Url::fromUserInput($logo_path, ['absolute' => FALSE])->toString() : '';
            break;
        }
      }
    }
    elseif ($type == 'date') {
      if (empty($data['date'])) {
        $date = \Drupal::time()->getRequestTime();
        // We depend on the current request time, so the tokens are not
        // cacheable at all.
        $bubbleable_metadata->setCacheMaxAge(0);
      }
      else {
        $date = $data['date'];
      }
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'short':
          case 'medium':
          case 'long':
            $date_format = DateFormat::load($name);
            $bubbleable_metadata->addCacheableDependency($date_format);
            $replacements[$original] = \Drupal::service('date.formatter')->format($date, $name, '', NULL, $langcode);
            break;

          case 'since':
            $replacements[$original] = \Drupal::service('date.formatter')->formatTimeDiffSince($date, ['langcode' => $langcode]);
            $bubbleable_metadata->setCacheMaxAge(0);
            break;

          case 'raw':
            $replacements[$original] = $date;
            break;
        }
      }
      if ($created_tokens = $token_service->findWithPrefix($tokens, 'custom')) {
        foreach ($created_tokens as $name => $original) {
          $replacements[$original] = \Drupal::service('date.formatter')->format($date, 'custom', $name, NULL, $langcode);
        }
      }
    }
    return $replacements;
  }

  /**
   * Returns the path of the logo for the given theme.
   *
   * @param string $theme
   *   The theme to get the logo path for.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The bubbleable metadata to alter in order to cache the token.
   *
   * @return string|null
   *   The logo path, or NULL if the "logo.url" setting does not exist for the
   *   theme.
   */
  protected function getLogoPath(string $theme, BubbleableMetadata $bubbleable_metadata) {
    if ($theme === 'default-theme') {
      // Add the cache dependency.
      $system_theme_config = \Drupal::config('system.theme');
      $theme = $system_theme_config->get('default');
      $bubbleable_metadata->addCacheableDependency($system_theme_config);
    }
    elseif ($theme === 'active-theme') {
      $theme = \Drupal::service('theme.manager')->getActiveTheme()->getName();
      // Needs caching per theme, because it can change.
      $bubbleable_metadata->addCacheContexts(['theme']);
    }

    // Because theme_get_setting() can return either the global theme logo or a
    // theme specific logo, this token depends on both configurations.
    $global_theme_config = \Drupal::config('system.theme.global');
    $theme_config = \Drupal::config($theme . '.settings');
    $bubbleable_metadata->addCacheableDependency($global_theme_config);
    $bubbleable_metadata->addCacheableDependency($theme_config);

    return \Drupal::service(ThemeSettingsProvider::class)->getSetting('logo.url', $theme);
  }

  /**
   * Returns an <img> tag for the logo of the given theme.
   *
   * @param string $theme
   *   The theme to get the image tag for.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   The bubbleable metadata to alter in order to cache the token.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   An HTML <img> tag for the logo or an empty string if no logo is
   *   configured for the theme.
   */
  protected function getLogoRendered(string $theme, BubbleableMetadata $bubbleable_metadata) {
    $logo_path = $this->getLogoPath($theme, $bubbleable_metadata);
    // No active / default theme logo is configured, simply return an empty
    // string:
    if ($logo_path === NULL) {
      return '';
    }
    $build = [
      '#theme' => 'image',
      '#uri' => $logo_path,
    ];
    return \Drupal::service('renderer')->renderInIsolation($build);
  }

}
