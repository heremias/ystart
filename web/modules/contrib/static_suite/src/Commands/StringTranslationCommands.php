<?php

namespace Drupal\static_suite\Commands;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\locale\SourceString;
use Drupal\locale\StringStorageInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file for string translation utilities.
 */
class StringTranslationCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The locale storage.
   *
   * @var \Drupal\locale\StringStorageInterface
   */
  protected $localeStorage;

  /**
   * Constructor for TranslationCommands.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\locale\StringStorageInterface $localeStorage
   *   The locale storage.
   */
  public function __construct(LanguageManagerInterface $languageManager, StringStorageInterface $localeStorage) {
    parent::__construct();
    $this->languageManager = $languageManager;
    $this->localeStorage = $localeStorage;
  }

  /**
   * Register a string into Drupal translation system.
   *
   * @param string $sourceString
   *   The string to be registered into Drupal.
   * @param string|null $translation
   *   Optional translation for the registered string.
   * @param string|null $langcode
   *   Optional language code to be used for the translation.
   *
   * @command static-suite:string-translation:register
   *
   * @usage drush static-suite:string-translation:register "source string"
   *   "translated string" langcode
   *
   * @throws \Drupal\locale\StringStorageException
   */
  public function registerString(string $sourceString, string $translation = NULL, string $langcode = NULL): void {
    // Check source string.
    $sanitizedSourceString = $this->removeWhiteSpace($sourceString);
    if (empty($sanitizedSourceString)) {
      $this->logger()
        ->error('The provided source string is not valid: "' . $sourceString . '"');
      return;
    }

    // Check translation string.
    $sanitizedTranslation = NULL;
    if ($translation !== NULL) {
      $sanitizedTranslation = $this->removeWhiteSpace($translation);
      if (empty($sanitizedTranslation)) {
        $this->logger()
          ->error('The provided translation string is not valid: "' . $translation . '"');
        return;
      }
    }

    // Check langcode.
    $availableLanguages = $this->languageManager->getLanguages();
    $sanitizedLangcode = NULL;
    if ($langcode !== NULL) {
      $sanitizedLangcode = $this->removeWhiteSpace($langcode);
      if (!isset($availableLanguages[$sanitizedLangcode])) {
        $this->logger()
          ->error('The provided langcode is not valid: "' . $langcode . '"');
        return;
      }
    }
    // We don't require langcode unless translating the source string.
    if (!$sanitizedLangcode && $sanitizedTranslation) {
      $sanitizedLangcode = $this->languageManager->getDefaultLanguage()
        ->getId();
    }

    $this->io()->title("Registering string:");
    $this->io()->table([
      'Source string',
      'Translation string',
      'Langcode',
    ], [
      [
        $sanitizedSourceString,
        $sanitizedTranslation ?? '--',
        $sanitizedLangcode ?? '--',
      ],
    ]);
    if ($this->io()->confirm("Do you want to continue?", TRUE)) {
      if ($sanitizedTranslation) {
        $this->addTranslation($sanitizedSourceString, $sanitizedTranslation, $sanitizedLangcode);
      }
      else {
        $this->getStringTranslation()
          ->translate($sanitizedSourceString)
          ->render();
      }
      $this->logger()->success('Registration done.');
    }
    else {
      $this->logger()->notice('Aborted');
    }
  }

  /**
   * Delete a string from Drupal translation system.
   *
   * @param string $string
   *   The string to be deleted.
   *
   * @command static-suite:string-translation:delete
   *
   * @usage drush static-suite:string-translation:delete string
   *
   * @throws \Drupal\locale\StringStorageException
   */
  public function stringDelete(string $string): void {
    $this->io()->title('Deleting string:');
    $this->io()->text($string);
    if ($this->io()->confirm("Do you want to continue?", FALSE)) {
      $storedString = $this->localeStorage->findString(['source' => $string]);
      if ($storedString) {
        $this->localeStorage->delete($storedString);
        $this->logger()->success('Delete done.');
      }
      else {
        $this->logger()->warning('String not found.');
      }
    }
    else {
      $this->logger()->notice('Aborted');
    }
  }

  /**
   * Create or update translated string.
   *
   * @param string $sourceString
   *   The string to be registered.
   * @param string $translation
   *   The translation.
   * @param string $langcode
   *   The language code.
   *
   * @throws \Drupal\locale\StringStorageException
   */
  protected function addTranslation(string $sourceString, string $translation, string $langcode): void {
    $string = $this->localeStorage->findString(['source' => $sourceString]);

    // If it is new, create a new SourceString.
    if (is_null($string)) {
      $string = new SourceString();
      $string->setString($sourceString);
      $string->setStorage($this->localeStorage);
      $string->save();
    }

    // If already present, replace it.
    $this->localeStorage->createTranslation([
      'lid' => $string->lid,
      'language' => $langcode,
      'translation' => $translation,
    ])->save();
  }

  /**
   * Removes white space from a string.
   *
   * @param string $string
   *   The string to be cleaned.
   *
   * @return string
   *   The cleaned string.
   */
  protected function removeWhiteSpace(string $string): string {
    return trim(preg_replace("/\s+/", " ", $string));
  }

}
