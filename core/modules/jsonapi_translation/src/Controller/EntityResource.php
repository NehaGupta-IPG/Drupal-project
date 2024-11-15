<?php

declare(strict_types=1);

namespace Drupal\jsonapi_translation\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\jsonapi\Controller\EntityResource as JsonApiEntityResource;
use Drupal\jsonapi\JsonApiResource\Data;
use Drupal\jsonapi\JsonApiResource\IncludedData;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\JsonApiResource\TopLevelDataInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Process all entity requests taking translatability into account.
 *
 * This replaces the parent controller. To provide a stable HTTP API this does
 * not rely on Drupal's native language negotiation system, which is highly
 * configurable and does not allow to rely on fixed URL patterns. Instead this
 * always relies on the following methods to specify a resource's desired
 * language:
 * - GET requests may specify the preferred resource language via the
 *   "Accept-Language" header, when accessing entities via the canonical URL.
 *   Alternatively it is possible to use a "langCode" query string parameter to
 *   specify the desired resource language. In the former case a missing
 *   translation will trigger a fallback to an existing translation, in the
 *   latter a "404 Not found" will be returned.
 * - POST, PATCH, DELETE requests may use the two following ways to specify a
 *   resource language:
 *   - The "langCode" query string parameter described above.
 *   - The "Content-Language" request header.
 *   These are functionally identical and a missing translation will always
 *   result in a "404 Not found" response.
 *
 * @internal JSON:API Translation maintains no PHP API. The API is the HTTP API.
 *   This class may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 */
final class EntityResource extends JsonApiEntityResource {

  const PARAM_LANGCODE = 'langCode';
  const HEADER_CONTENT_LANGUAGE = 'Content-Language';
  const ATTR_TRANSLATION_RESOURCE = 'jsonapi_translation_resource';
  const ATTR_TRANSLATION_LANGUAGE = 'jsonapi_translation_language';

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Injects the language manager.
   */
  public function setLanguageManager(LanguageManagerInterface $language_manager): void {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   If the requested resource translation does not exist.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   If there was a language mismatch or the entity does not support
   *   translation.
   */
  public function getIndividual(EntityInterface $entity, Request $request) {
    $resource_type = $this->resourceTypeRepository->get($entity->getEntityTypeId(), $entity->bundle());

    // If the entity type is not translatable or no resource language was
    // specified, we can safely fall back to the original behavior.
    $resource_language = $this->getResourceLanguage($request);
    if (!$resource_type->isTranslatable()) {
      if (!$resource_language) {
        return parent::getIndividual($entity, $request);
      }
      else {
        throw new BadRequestHttpException('The request specified a preferred language, but the requested resource type does not support translation.');
      }
    }

    $entity = $this->getDefaultTranslation($entity);

    // If a resource language is explicitly provided, a resource translation was
    // unambiguously specified. Otherwise we rely on the "Accept-Language" header
    // for the fallback logic.
    if ($resource_language) {
      $translation = $this->getResourceTranslation($entity, $request);
      if (!$translation) {
        throw new NotFoundHttpException(sprintf('The "%s" translation of the specified resource does not exist.', $resource_language->getId()));
      }
    }
    else {
      $translation = $this->getPreferredResourceTranslation($entity, $request);
    }

    $request->attributes->set(static::ATTR_TRANSLATION_RESOURCE, $translation);

    $response = parent::getIndividual($translation, $request);

    // If a translation resource was accessed via the "Accept-Language" header,
    // we inform the client about its canonical URL.
    if (!$resource_language) {
      $query = $request->query->all();
      $query[static::PARAM_LANGCODE] = $translation->language()->getId();
      $url = static::getRequestLink($request, $query)
        ->setAbsolute()
        ->toString(TRUE);
      $response->addCacheableDependency($url);
      $response->headers->set('Content-Location', $url->getGeneratedUrl());

      // @todo Internal page cache does not support the "Accept-Language" header
      //   currently. Remove this once it does. See https://www.drupal.org/project/drupal/issues/2430335.
      \Drupal::service('page_cache_kill_switch')->trigger();
    }

    // Add the response cache contexts.
    $dependency = new CacheableMetadata();
    $dependency->setCacheContexts([
      $resource_language ? 'url.query_args:' . static::PARAM_LANGCODE : 'headers:Accept-Language',
    ]);
    $response->addCacheableDependency($dependency);

    return $response;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   If there was a language mismatch or the entity does not support
   *   translation.
   */
  public function createIndividual(ResourceType $resource_type, Request $request) {
    // Language-aware resources may have their language set via the specified
    // resource language, however this is only allowed when the language data
    // attribute is omitted. No inconsistency between language specified via
    // request metadata and via request payload is allowed.
    if ($this->isResourceLanguageAware($resource_type)) {
      $resource_language = $this->getResourceLanguage($request);
      if ($resource_language) {
        $body = $this->getRequestBody($request);
        $langcode_key = $this->entityTypeManager
          ->getDefinition($resource_type->getEntityTypeId())
          ->getKey('langcode');
        $field_name = $resource_type->getPublicName($langcode_key);

        if (!isset($body['data']['attributes'][$field_name])) {
          $parsed_entity = $this->getEntityFromRequest($resource_type, $request);
          assert($parsed_entity instanceof ContentEntityInterface);
          $parsed_entity->set($langcode_key, $resource_language);
        }
        else {
          $this->checkResourceLanguage($resource_type, $request);
        }
      }
    }

    return parent::createIndividual($resource_type, $request);
  }

  /**
   * Creates an individual entity translation.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the request to be served.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If the resource translation is not specified.
   * @throws \Symfony\Component\HttpKernel\Exception\ConflictHttpException
   *   If the specified resource translation already exists.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   If there was a language mismatch or the entity does not support
   *   translation.
   */
  public function createIndividualTranslation(ResourceType $resource_type, EntityInterface $entity, Request $request) {
    if (!$this->isResourceTypeTranslatable($resource_type)) {
      throw new UnprocessableEntityHttpException('The specified resource does not support translation.');
    }

    $resource_language = $this->getResourceLanguage($request);
    if (!$resource_language) {
      throw new BadRequestHttpException('No resource translation language was specified.');
    }
    $resource_langcode = $resource_language->getId();

    $entity = $this->getDefaultTranslation($entity);
    $translation = $this->getResourceTranslation($entity, $request);
    if ($translation) {
      throw new ConflictHttpException(sprintf('The "%s" resource translation already exists.', $resource_langcode));
    }

    // Check translatability.
    $this->checkResourceLanguage($resource_type, $request);
    $this->checkEntityTranslatability($entity);
    $this->checkFieldTranslatability($resource_type, $request, $entity);

    // Creating a new translation just means PATCH-ing an existing entity, after
    // adding the translation values.
    $translation = $entity->addTranslation($resource_langcode);
    $request->attributes->set(static::ATTR_TRANSLATION_RESOURCE, $translation);
    $response = parent::patchIndividual($resource_type, $translation, $request);
    $response->setStatusCode(Response::HTTP_CREATED);

    // Let the client know about the new translation's canonical URL.
    $query = [
      static::PARAM_LANGCODE => $translation->language()->getId(),
    ];
    $url = static::getRequestLink($request, $query)
      ->setAbsolute()
      ->toString();
    $response->headers->set('Location', $url);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function patchIndividual(ResourceType $resource_type, EntityInterface $entity, Request $request) {
    $translation = $entity;

    if ($this->isResourceTypeTranslatable($resource_type)) {
      $entity = $this->getDefaultTranslation($entity);
      $translation = $this->getResourceTranslation($entity, $request);

      // If a resource translation is specified, check its translatability.
      if ($translation) {
        $this->checkResourceLanguage($resource_type, $request);
        if (!$translation->isDefaultTranslation()) {
          $this->checkEntityTranslatability($entity);
          $this->checkFieldTranslatability($resource_type, $request, $translation);
        }
      }
      // Otherwise simply check that no invalid resource translation language
      // was specified.
      else {
        $resource_language = $this->getResourceLanguage($request);
        if ($resource_language) {
          throw new NotFoundHttpException(sprintf('The "%s" translation of the specified resource does not exist.', $resource_language->getId()));
        }
        $translation = $entity;
      }

      $request->attributes->set(static::ATTR_TRANSLATION_RESOURCE, $translation);
    }

    return parent::patchIndividual($resource_type, $translation, $request);
  }

  /**
   * Deletes an individual entity translation.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the request to be served.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The loaded entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function deleteIndividualOrTranslation(ResourceType $resource_type, EntityInterface $entity, Request $request) {
    if ($this->isResourceTypeTranslatable($resource_type)) {
      $entity = $this->getDefaultTranslation($entity);
      $translation = $this->getResourceTranslation($entity, $request);

      if ($translation) {
        if ($translation->isDefaultTranslation()) {
          throw new BadRequestHttpException('Deleting the default translation is not supported.');
        }
        $this->checkEntityTranslatability($entity);
      }
      else {
        $resource_language = $this->getResourceLanguage($request);
        if (!$resource_language) {
          return parent::deleteIndividual($entity);
        }
        else {
          throw new NotFoundHttpException(sprintf('The "%s" translation of the specified resource does not exist.', $resource_language->getId()));
        }
      }
    }
    else {
      return parent::deleteIndividual($entity);
    }

    $entity->removeTranslation($translation->language()->getId());
    $entity->save();

    return new ResourceResponse(NULL, Response::HTTP_NO_CONTENT);
  }

  /**
   * Returns the default translation of the specified entity type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   A content entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity's default translation.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   If the entity does not support translation.
   */
  protected function getDefaultTranslation(EntityInterface $entity): ContentEntityInterface {
    if ($entity instanceof ContentEntityInterface) {
      return $entity->getUntranslated();
    }
    throw new UnprocessableEntityHttpException('The specified resource does not support translation.');
  }

  /**
   * Checks that request metadata and data specify language consistently.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the request to be served.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   If the there is a language mismatch or the resource is not language
   *   aware.
   */
  protected function checkResourceLanguage(ResourceType $resource_type, Request $request): void {
    $resource_language = $this->getResourceLanguage($request);
    if (!$resource_language) {
      return;
    }
    if (!$this->isResourceLanguageAware($resource_type)) {
      throw new UnprocessableEntityHttpException('The request specified a preferred language, but the requested resource type does not support translation.');
    }
    $resource_langcode = $resource_language->getId();
    $parsed_entity = $this->getEntityFromRequest($resource_type, $request);
    $parsed_langcode = $parsed_entity->language()->getId();
    if ($resource_langcode !== $parsed_langcode) {
      $message = 'Translation resource language mismatch: "%s" (request metadata) vs "%s" (request payload).';
      throw new UnprocessableEntityHttpException(sprintf($message, $resource_langcode, $parsed_langcode));
    }
  }

  /**
   * Checks whether an entity is translatable.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A content entity.
   */
  protected function checkEntityTranslatability(ContentEntityInterface $entity): void {
    if (!$entity->isTranslatable()) {
      throw new UnprocessableEntityHttpException('Translation is not enabled for the specified resource.');
    }
  }

  /**
   * Checks that no untranslatable field is affected by the request.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the request to be served.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The loaded entity.
   */
  protected function checkFieldTranslatability(ResourceType $resource_type, Request $request, ContentEntityInterface $entity): void {
    $field_names = $this->getRequestFieldNames($resource_type, $request);

    $untranslatable_field_names = [];
    foreach ($field_names as $field_name) {
      $field_definition = $entity->getFieldDefinition($field_name);
      if ($field_definition && !$field_definition->isTranslatable()) {
        $untranslatable_field_names[$field_name] = $field_name;
      }
    }

    if ($untranslatable_field_names) {
      $untranslatable_field_names = '"' . implode('", "', $untranslatable_field_names) . '"';
      throw new UnprocessableEntityHttpException(sprintf('The following fields are not translatable: %s.', $untranslatable_field_names));
    }
  }

  /**
   * Returns the specified resource language.
   *
   * Retrieves the resource language from the request, either from the
   * "lang_code" parameter or from the "Content-Language" header.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   A language object or NULL if the request has no language information.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   If both the "Accept-Language" header and at least one between the
   *   "lang_code" parameter and the "Content-Language" header were specified.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   If there is a mismatch between the values of the "lang_code" parameter
   *   and the "Content-Language" header, or if an invalid language was
   *   specified.
   */
  protected function getResourceLanguage(Request $request): ?LanguageInterface {
    $language = $this->getRequestAttribute($request, static::ATTR_TRANSLATION_LANGUAGE, function (Request $request) {
      $param_langcode = $request->query->get(static::PARAM_LANGCODE);
      $content_language_header = $request->headers->get(static::HEADER_CONTENT_LANGUAGE);

      if (!$param_langcode && !$content_language_header) {
        return NULL;
      }

      if ($content_language_header && $request->isMethodCacheable()) {
        throw new BadRequestHttpException('Specifying the "Content-Language" header is not supported in cacheable requests.');
      }
      if ($request->headers->get('Accept-Language')) {
        throw new BadRequestHttpException('Specifying both a request language and the "Accept-Language" header is not supported.');
      }
      if ($param_langcode && $content_language_header && $param_langcode !== $content_language_header) {
        $message = 'Translation resource language mismatch: "%s" ("%s" query string parameter) vs "%s" ("%s" header).';
        throw new UnprocessableEntityHttpException(sprintf($message, $param_langcode, static::PARAM_LANGCODE, $content_language_header, static::HEADER_CONTENT_LANGUAGE));
      }

      $langcode = $param_langcode ?: $content_language_header;
      $languages = $this->languageManager->getLanguages();
      $language = $languages[$langcode] ?? NULL;

      if ($language) {
        return $language;
      }

      throw new UnprocessableEntityHttpException(sprintf('The specified language ("%s") is invalid or has not been configured.', $langcode));
    });
    assert($language === NULL || $language instanceof LanguageInterface);
    return $language;
  }

  /**
   * Returns the resource translation best fitting the specified request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A content entity object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   A translation of the specified content entity object. The default
   *   translation if no translation matches the request preference.
   */
  protected function getPreferredResourceTranslation(ContentEntityInterface $entity, Request $request): ContentEntityInterface {
    $default_langcode = $entity->getUntranslated()->language()->getId();
    $translation_langcodes = array_keys($entity->getTranslationLanguages(FALSE));
    $langcodes = array_merge([$default_langcode], $translation_langcodes);
    $langcode = $request->getPreferredLanguage($langcodes);
    return $langcode && $entity->hasTranslation($langcode) ?
      $entity->getTranslation($langcode) : $entity->getUntranslated();
  }

  /**
   * Returns the resource translation specified by the request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   A content entity object.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   A translation of the specified content entity object or NULL if the
   *   specified translation does not exist.
   */
  protected function getResourceTranslation(ContentEntityInterface $entity, Request $request): ?ContentEntityInterface {
    return $this->getRequestAttribute($request, static::ATTR_TRANSLATION_RESOURCE, function (Request $request) use ($entity) {
      $language = $this->getResourceLanguage($request);
      $langcode = $language ? $language->getId() : NULL;
      return $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
    });
  }

  /**
   * Checks whether the specified resource type supports language assignment.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the request to be served.
   *
   * @return bool
   *   TRUE if the resource type is language-aware, FALSE otherwise.
   */
  protected function isResourceLanguageAware(ResourceType $resource_type): bool {
    $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
    return $entity_type->hasKey('langcode');
  }

  /**
   * Checks whether the specified resource type supports translation.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the request to be served.
   *
   * @return bool
   *   TRUE if the resource type is translatable, FALSE otherwise.
   */
  protected function isResourceTypeTranslatable(ResourceType $resource_type): bool {
    if ($resource_type->isTranslatable()) {
      return TRUE;
    }

    return $this->isResourceLanguageAware($resource_type);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildWrappedResponse(TopLevelDataInterface $data, Request $request, IncludedData $includes, $response_code = 200, array $headers = [], ?LinkCollection $links = NULL, array $meta = []) {
    if ($data instanceof Data && $data->getCardinality() !== 1) {
      return parent::buildWrappedResponse($data, $request, $includes, $response_code, $headers, $links, $meta);
    }

    $translation = $request->attributes->get(static::ATTR_TRANSLATION_RESOURCE);
    if ($translation instanceof ContentEntityInterface) {
      $translation_langcodes = array_keys($translation->getTranslationLanguages());
      $attributes = [
        'hreflang' => implode(',', $translation_langcodes),
      ];

      $resource_objects = [];
      $query = $request->query->all();
      unset($query[static::PARAM_LANGCODE]);
      $url = static::getRequestLink($request, $query);

      foreach ($data->getData() as $resource_data) {
        if ($resource_data instanceof ResourceObject) {
          $resource_links = $resource_data->getLinks()->filter(function ($key) {
            return $key !== 'self';
          });
          $self_link = new Link(new CacheableMetadata(), $url, 'self', $attributes);
          $resource_links = $resource_links->withLink('self', $self_link);
          $resource_objects[] = new ResourceObject(
            $resource_data,
            $resource_data->getResourceType(),
            $resource_data->getId(),
            $resource_data->getResourceType()->isVersionable() ? $resource_data->getVersionIdentifier() : NULL,
            $resource_data->getFields(),
            $resource_links,
            $resource_data->getLanguage()
          );
        }
      }

      if ($resource_objects) {
        $data = new ResourceObjectData($resource_objects, count($resource_objects));
      }
    }

    return parent::buildWrappedResponse($data, $request, $includes, $response_code, $headers, $links, $meta);
  }

}
