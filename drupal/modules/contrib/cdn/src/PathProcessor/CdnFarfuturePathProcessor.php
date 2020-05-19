<?php

declare(strict_types = 1);

namespace Drupal\cdn\PathProcessor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite CDN farfuture URLs.
 *
 * As the route system does not allow arbitrary amount of parameters convert
 * the file path to a query parameter on the request.
 *
 * Also normalizes legacy far-future URLs generated prior to
 * https://www.drupal.org/node/2870435
 *
 * @see \Drupal\image\PathProcessor\PathProcessorImageStyles
 */
class CdnFarfuturePathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    // @todo Remove before CDN 4.0.
    if (strpos($path, '/cdn/farfuture/') === 0) {
      return $this->processLegacyFarFuture($path, $request);
    }
    if (strpos($path, '/cdn/ff/') === 0) {
      return $this->processFarFuture($path, $request);
    }
    return $path;
  }

  /**
   * Process the path for the far future controller.
   *
   * @param string $path
   *   The path.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The processed path.
   */
  protected function processFarFuture(string $path, Request $request) : string {
    // Parse the security token, mtime, scheme and root-relative file URL.
    $tail = substr($path, strlen('/cdn/ff/'));
    list($security_token, $mtime, $scheme, $relative_file_url) = explode('/', $tail, 4);
    $returnPath = "/cdn/ff/$security_token/$mtime/$scheme";
    // Set the root-relative file URL as query parameter.
    $request->query->set('relative_file_url', '/' . UrlHelper::encodePath($relative_file_url));
    // Return the same path, but without the trailing file.
    return $returnPath;
  }

  /**
   * Process the path for the deprecated far future controller.
   *
   * @param string $path
   *   The path.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The processed path.
   *
   * @todo Remove in 4.x.
   */
  protected function processLegacyFarFuture($path, Request $request) {
    $tail = substr($path, strlen('/cdn/farfuture/'));
    list($security_token, $mtime, $root_relative_file_url) = explode('/', $tail, 3);
    $returnPath = "/cdn/farfuture/$security_token/$mtime";
    // Set the root-relative file URL as query parameter.
    $request->query->set('root_relative_file_url', '/' . UrlHelper::encodePath($root_relative_file_url));
    // Return the same path, but without the trailing file.
    return $returnPath;
  }

}
