<?php

namespace Drupal\static_suite\Security;

use Drupal\Component\Transliteration\TransliterationInterface;

/**
 * Base class for all classes implementing UriSanitizerInterface.
 *
 * This class offers a working method for all members of the interface. This
 * way, sanitizers only have to implement the methods they are interested in.
 */
abstract class UriSanitizerBase implements UriSanitizerInterface {

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * Tells whether uppercase characters are allowed.
   *
   * True by default.
   *
   * @var bool
   */
  protected $allowUppercase = TRUE;

  /**
   * UriSanitizerBase constructor.
   *
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   */
  public function __construct(TransliterationInterface $transliteration) {
    $this->transliteration = $transliteration;
  }

  /**
   * {@inheritdoc}
   */
  public function allowUppercase(bool $flag): void {
    $this->allowUppercase = $flag;
  }

  /**
   * {@inheritdoc}
   */
  public function sanitize(string $uri): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $uri : strtolower($uri));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeScheme(string $scheme): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $scheme : strtolower($scheme));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeAuthority(string $authority): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $authority : strtolower($authority));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeAuthorityUserInfo(string $authorityUserInfo): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $authorityUserInfo : strtolower($authorityUserInfo));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeAuthorityHost(string $authorityHost): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $authorityHost : strtolower($authorityHost));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeAuthorityPort(int $authorityPort): int {
    return abs($authorityPort);
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizePath(string $path): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $path : strtolower($path));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizePathSegment(string $pathSegment): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $pathSegment : strtolower($pathSegment));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeQuery(string $query): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $query : strtolower($query));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeFragment(string $fragment): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $fragment : strtolower($fragment));
  }

}
