<?php

namespace Drupal\static_suite\Security;

/**
 * Interface for a URI sanitizer.
 *
 * Sanitizes all components of a URI. It can be specialized to sanitize any kind
 * of URI that Static Suite has to deal with (file paths, in-memory storage
 * keys, bucket keys, git keys, etc)
 *
 * The URI generic syntax consists of a hierarchical sequence of five
 * components:
 *
 *   URI = scheme:[//authority]path[?query][#fragment]
 *
 * where the authority component divides into three subcomponents:
 *
 *   authority = [userinfo@]host[:port]
 *
 * @see https://en.wikipedia.org/wiki/Uniform_Resource_Identifier#Definition
 */
interface UriSanitizerInterface {

  /**
   * Allows the use of uppercase characters.
   *
   * By default, only lowercase characters are allowed.
   *
   * @param bool $flag
   *   A boolean flag to enable or disable this rule..
   */
  public function allowUppercase(bool $flag): void;

  /**
   * Sanitizes a URI.
   *
   * @param string $uri
   *   URI to be sanitized.
   *
   * @return string
   *   Sanitized URI.
   */
  public function sanitize(string $uri): string;

  /**
   * Sanitizes a URI scheme.
   *
   * @param string $scheme
   *   Scheme to be sanitized.
   *
   * @return string
   *   Sanitized scheme.
   */
  public function sanitizeScheme(string $scheme): string;

  /**
   * Sanitizes a URI authority.
   *
   * @param string $authority
   *   Authority to be sanitized.
   *
   * @return string
   *   Sanitized authority.
   */
  public function sanitizeAuthority(string $authority): string;

  /**
   * Sanitizes a URI authority's user info.
   *
   * @param string $authorityUserInfo
   *   Authority's user info to be sanitized.
   *
   * @return string
   *   Sanitized authority's user info.
   */
  public function sanitizeAuthorityUserInfo(string $authorityUserInfo): string;

  /**
   * Sanitizes a URI authority's host.
   *
   * @param string $authorityHost
   *   Authority's host to be sanitized.
   *
   * @return string
   *   Sanitized authority's host.
   */
  public function sanitizeAuthorityHost(string $authorityHost): string;

  /**
   * Sanitizes a URI authority's port.
   *
   * @param int $authorityPort
   *   Authority's port to be sanitized.
   *
   * @return int
   *   Sanitized authority's port.
   */
  public function sanitizeAuthorityPort(int $authorityPort): int;

  /**
   * Sanitizes a URI $path.
   *
   * @param string $path
   *   Path to be sanitized.
   *
   * @return string
   *   Sanitized path.
   */
  public function sanitizePath(string $path): string;

  /**
   * Sanitizes a URI path segment.
   *
   * @param string $pathSegment
   *   Path segment to be sanitized.
   *
   * @return string
   *   Sanitized path segment.
   */
  public function sanitizePathSegment(string $pathSegment): string;

  /**
   * Sanitizes a URI query.
   *
   * @param string $query
   *   Query to be sanitized.
   *
   * @return string
   *   Sanitized query.
   */
  public function sanitizeQuery(string $query): string;

  /**
   * Sanitizes a URI fragment.
   *
   * @param string $fragment
   *   Fragment to be sanitized.
   *
   * @return string
   *   Sanitized fragment.
   */
  public function sanitizeFragment(string $fragment): string;

}
