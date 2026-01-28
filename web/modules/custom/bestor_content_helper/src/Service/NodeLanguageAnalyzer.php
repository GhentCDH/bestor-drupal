<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\node\NodeInterface;
use Drupal\bestor_content_helper\Service\NodeContentAnalyzer;
use Drupal\Core\Language\LanguageInterface;

class NodeLanguageAnalyzer {

  protected NodeContentAnalyzer $contentAnalyzer;

  // Configurable values of what is a short translation and a long translation.
  const MAX_EMPTYISH_TRANSLATION = 10;
  const MAX_SHORT_TRANSLATION = 200;
  const MIN_LONG_TRANSLATION = 1000;


  public function __construct(NodeContentAnalyzer $contentAnalyzer) {
    $this->contentAnalyzer = $contentAnalyzer;
  }


  /**
   * Find substantially better translations.
   */
  public function getTranslationSuggestions(NodeInterface $node, string $current_langcode): ?array {
    
    if (!$node->hasTranslation($current_langcode)) {
      return $this->listSuggestions($node, 0);
    }
    
    $translation = $node->getTranslation($current_langcode);
    $word_count = $this->contentAnalyzer->countContentWords($translation);

    if ($word_count < self::MAX_SHORT_TRANSLATION) {
       return $this->listSuggestions($node, $word_count, $current_langcode);;
    }
    
    return null;
  }
 

  protected function listSuggestions(
    NodeInterface $node, 
    int $original_word_count,
    ?string $exclude_langcode = null
  ): ?array {
    
    $suggest_translations = [];
    
    foreach ($node->getTranslationLanguages() as $langcode => $language) {
      if ($langcode === $exclude_langcode) {
        continue;
      }
      
      $translation = $node->getTranslation($langcode);
      $translation_word_count = $this->contentAnalyzer->countContentWords($translation);
      
      if (empty($translation_word_count)) {
        continue;
      }

      if (
        empty($original_word_count) ||
        (
          $original_word_count < self::MAX_EMPTYISH_TRANSLATION && 
          $original_word_count * 10 < $translation_word_count
        ) ||
        $translation_word_count > self::MIN_LONG_TRANSLATION
      ) {
        $suggest_translations[$langcode] =  [
          'language_name' => $language->getName(),
          'word_count' => $translation_word_count,
          'url' => $translation->toUrl()->toString(),
          'title' => $translation->getTitle(),
        ];
      }
    }

    if (empty($suggest_translations)) {
      return null;
    } 

    uasort($suggest_translations, function($a, $b) {
      return $b['word_count'] <=> $a['word_count'];
    });

    if (count($suggest_translations) > 1){
      $max_count = reset($suggest_translations)['word_count'];
      foreach($suggest_translations as $suggestion_code => $suggestion_info){
        $suggestion_count = $suggestion_info['word_count'];
        if(
          $suggestion_count === $max_count ||
          $suggestion_count > self::MIN_LONG_TRANSLATION || 
          $suggestion_count > $max_count / 10
        ) {
          continue;
        }

        unset($suggest_translations[$suggestion_code]);
      }
    }
    
    return [
      'current_words' => $original_word_count,
      'alternatives' => $suggest_translations,
    ];
  }


  /**
   * Ensure default translation has a title.
   */
  public function ensureDefaultTranslationTitle(NodeInterface $node): void {
    $default = $node->getUntranslated();
    
    if (!empty($default->getTitle())) {
      return;
    }
    
    $languages = ['en', 'fr', 'nl'];
    
    foreach ($languages as $langcode) {
      if (!$node->hasTranslation($langcode)) {
        continue;
      }
      
      $translation = $node->getTranslation($langcode);
      if (!empty($translation->getTitle())) {
        $default->setTitle($translation->getTitle());
        return;
      }
    }
    
    // Fallback
    $default->setTitle('[Node ' . $node->id() . ']');
  }
}