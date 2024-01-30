<?php

namespace Drupal\queue_mail_language;

use Drupal\language\LanguageNegotiator;

/**
 * Class responsible for performing language negotiation.
 */
class QueueMailLanguageNegotiator extends LanguageNegotiator {

  /**
   * Language code.
   *
   * @var string
   */
  public $languageCode = NULL;

  /**
   * {@inheritdoc}
   */
  public function initializeType($type) {
    $method_id = static::METHOD_ID;
    $availableLanguages = $this->languageManager->getLanguages();

    if ($this->languageCode && isset($availableLanguages[$this->languageCode])) {
      $language = $availableLanguages[$this->languageCode];
    }
    else {
      // If no other language was found use the default one.
      $language = $this->languageManager->getDefaultLanguage();
    }

    return [$method_id => $language];
  }

  /**
   * Sets language code.
   *
   * @param string $langcode
   *   Language code.
   */
  public function setLanguageCode($langcode) {
    $this->languageCode = $langcode;
  }

}
