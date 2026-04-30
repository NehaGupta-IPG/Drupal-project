<?php

namespace Drupal\system\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for system.
 */
class SystemTokensHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected DateFormatterInterface $dateFormatter,
    protected RendererInterface $renderer,
    protected RequestStack $requestStack,
    protected RequestContext $requestContext,
    protected LanguageManagerInterface $languageManager,
    protected Token $token,
    protected TimeInterface $time,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $request = $this->requestStack->getCurrentRequest();
    $types['site'] = [
      'name' => $this->t("Site information"),
      'description' => $this->t("Tokens for site-wide settings and other global information."),
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
        '@base_url' => $this->requestContext->getCompleteBaseUrl(),
      ]),
    ];
    $site['base-path'] = [
      'name' => $this->t("Base path"),
      'description' => $this->t("The base path of the site, currently: @base_path", [
        '@base_path' => $request?->getBasePath(),
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
    $date_formatter = $this->dateFormatter;
    // Date related tokens.
    $request_time = $this->time->getRequestTime();
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
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $url_options = ['absolute' => TRUE];
    if (isset($options['langcode'])) {
      $url_options['language'] = $this->languageManager->getLanguage($options['langcode']);
      $langcode = $options['langcode'];
    }
    else {
      $langcode = NULL;
    }
    $replacements = [];
    if ($type == 'site') {
      $config = $this->configFactory->get('system.site');
      $request = $this->requestStack->getCurrentRequest();
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'name':
            $bubbleable_metadata->addCacheableDependency($config);
            $replacements[$original] = $config->get('name');
            break;

          case 'slogan':
            $bubbleable_metadata->addCacheableDependency($config);
            $build = ['#markup' => $config->get('slogan')];
            // @todo Fix in https://www.drupal.org/node/2577827
            $replacements[$original] = $this->renderer->renderInIsolation($build);
            break;

          case 'mail':
            $bubbleable_metadata->addCacheableDependency($config);
            $replacements[$original] = $config->get('mail');
            break;

          case 'base-url':
            $bubbleable_metadata->addCacheContexts(['url.site']);
            $replacements[$original] = $this->requestContext->getCompleteBaseUrl();
            break;

          case 'base-path':
            $bubbleable_metadata->addCacheContexts(['url.site']);
            $replacements[$original] = $request?->getBasePath();
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
        }
      }
    }
    elseif ($type == 'date') {
      if (empty($data['date'])) {
        $date = $this->time->getRequestTime();
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
            $replacements[$original] = $this->dateFormatter->format($date, $name, '', NULL, $langcode);
            break;

          case 'since':
            $replacements[$original] = $this->dateFormatter->formatTimeDiffSince($date, ['langcode' => $langcode]);
            $bubbleable_metadata->setCacheMaxAge(0);
            break;

          case 'raw':
            $replacements[$original] = $date;
            break;
        }
      }
      if ($created_tokens = $this->token->findWithPrefix($tokens, 'custom')) {
        foreach ($created_tokens as $name => $original) {
          $replacements[$original] = $this->dateFormatter->format($date, 'custom', $name, NULL, $langcode);
        }
      }
    }
    return $replacements;
  }

}
