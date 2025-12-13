<?php

declare(strict_types=1);

namespace Drupal\link\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\link\EntityLinkSuggestions;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for link field widget autocomplete route.
 *
 * @internal
 */
class LinkAutocompleteController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Constructs a LinkAutocompleteController.
   *
   * @param \Drupal\link\EntityLinkSuggestions $entityLinkSuggestions
   *   The entity link suggestions service.
   */
  public function __construct(
    protected readonly EntityLinkSuggestions $entityLinkSuggestions,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('link.entity_link_suggestions')
    );
  }

  /**
   * Generates entity link suggestions for use by an autocomplete.
   *
   * Like other autocomplete functions, this function inspects the 'q' query
   * parameter for the string to use to search for suggestions.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the autocomplete suggestions.
   */
  public function suggestions(Request $request): JsonResponse {
    $input = $request->query->get('q') ?? '';
    $host_entity_type_id = $request->query->get('hostEntityTypeId');
    $host_entity_langcode = $request->query->get('hostEntityLangcode');

    $suggestions = $this->entityLinkSuggestions->getSuggestionsForRequest(
      $input,
      $host_entity_type_id,
      $host_entity_langcode,
      $this->languageManager()
    );

    // If no suggestions were found, add a special suggestion that has the
    // same path as the given string so users can select it and use it anyway.
    // This typically occurs when entering external links.
    if (empty($suggestions) && !empty($input)) {
      $suggestions = [
        [
          'description' => $this->t('No content suggestions found. This URL will be used as is.'),
          'group' => $this->t('No results'),
          'label' => Html::escape($input),
          'href' => UrlHelper::isValid($input) ? $input : '',
        ],
      ];
    }

    // Note that we intentionally:
    // - do not use \Drupal\Core\Cache\CacheableJsonResponse because caching it
    //   on the server side is wasteful, hence there is no need for cacheability
    //   metadata.
    // - mark the response as private, because the suggestions include only the
    //   ones accessible by the current user.
    return (new JsonResponse(['suggestions' => $suggestions]))
      // Do not allow any intermediary to cache the response, only the end user.
      ->setPrivate()
      // Allow the end user to cache it for up to 5 minutes.
      ->setMaxAge(300);
  }

}
