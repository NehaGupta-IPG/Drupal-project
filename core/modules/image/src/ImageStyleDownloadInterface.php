<?php

namespace Drupal\image;

use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to serve image styles.
 */
interface ImageStyleDownloadInterface {

  /**
   * Generates a derivative, given a style and image path.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   * @param string $required_derivative_scheme
   *   The required scheme for the derivative image.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file request is invalid.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style, string $required_derivative_scheme);

  /**
   * Checks whether the provided source image exists.
   *
   * @param string $image_uri
   *   The URI for the source image.
   * @param bool $token_is_valid
   *   Whether a valid image token was supplied.
   *
   * @return bool
   *   Whether the source image exists.
   */
  public function sourceImageExists(string $image_uri, bool $token_is_valid): bool;

  /**
   * Get the file URI without the extension from any conversion image style.
   *
   * If the image style converted the image, then an extension has been added
   * to the original file, resulting in filenames like image.png.jpeg.
   *
   * @param string $uri
   *   The file URI.
   *
   * @return string
   *   The file URI without the extension from any conversion image style.
   */
  public static function getUriWithoutConvertedExtension(string $uri): string;

  /**
   * Factorization of ImageStyleDownloadController::deliver Line 114-124.
   *
   * @param string $scheme
   *   Target Scheme.
   * @param string $image_uri
   *   Original image uri.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file request is invalid.
   */
  public function checkNormalizedScheme($scheme, $image_uri): void;

  /**
   * Factorization of ImageStyleDownloadController::deliver Line 139-142.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTPS request.
   * @param string $image_uri
   *   Original image uri.
   * @param string $scheme
   *   Target Scheme.
   * @param string $target
   *   Target File.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   Image Style.
   *
   * @return bool
   *   ITOK Token ins valid.
   */
  public function checkToken(Request $request, $image_uri, $scheme, $target, ImageStyleInterface $image_style) : bool;

  /**
   * Factorization of ImageStyleDownloadController::deliver Line 126-152.
   *
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   ImageStyle used.
   * @param string $scheme
   *   Target Scheme.
   * @param string $target
   *   Target File.
   * @param bool $token_is_valid
   *   ITOK token is valid.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file request is invalid.
   */
  public function authorizedDerivativeGeneration(ImageStyleInterface $image_style, $scheme, $target, $token_is_valid): void;

  /**
   * Factorization of ImageStyleDownloadController::deliver Line 157-165.
   *
   * @param bool $token_is_valid
   *   ITOK token is valid.
   * @param string $scheme
   *   Target Scheme.
   * @param string $derivative_scheme
   *   Derivative Scheme.
   *
   * @return bool
   *   Scheme is public.
   */
  public function isSchemePublic(bool $token_is_valid, string $scheme, string $derivative_scheme): bool;

}
