<?php

namespace Drupal\bestor_content_helper\Service;


use Drupal\node\NodeInterface;


/**
 * Service for analyzing and formatting node content.
 * 
 * Provides utilities for extracting key data from lemma nodes,
 * calculating reading times, and formatting field values.
 */
class NodeContentAnalyzer {


  /**
   * Count words in node body or description field.
   *
   * @param NodeInterface $node
   *   The node to analyze (should be translation-specific).
   *
   * @return int
   *   Word count.
   */
  public function countContentWords(NodeInterface $node): int {
    // $node = The translation of a node instance.
    $node_fields = $node->getFields();
    if(empty($node_fields) || (empty($node_fields['field_description']) && $node_fields['body'])){
      return 0;
    }
    $words = '';
    if (isset($node_fields['field_description']) && $node->field_description->getFieldDefinition()->getType() === 'entity_reference_revisions') {
      if ($node_fields['field_description']->referencedEntities() > 0) {
        foreach ($node_fields['field_description']->referencedEntities() as $paragraph) {
          if ($paragraph->field_formatted_text->value) {
            $paragraph_text = strip_tags($paragraph->field_formatted_text->value);
            $words = $words ? $words . ' ' . $paragraph_text : $paragraph_text;
          }
        }
      }
    } else if (isset($node_fields['body']) && $node->body->getFieldDefinition()->getType() === 'text_with_summary') {
      $words = strip_tags($node->body->value);
    }
    return str_word_count($words);
  }


  /**
   * Calculate reading time in minutes.
   *
   * @param NodeInterface $node
   *   The node to analyze.
   *
   * @return int
   *   Reading time in minutes.
   */
  protected function getReadingTime(NodeInterface $node): int {
    return ceil($this->countContentWords($node) / 250);
  }


  /**
   * Get formatted reading time string.
   *
   * @param NodeInterface $node
   *   The node to analyze.
   *
   * @return string
   *   Formatted reading time (e.g., "5 min." or "< 1 min.").
   */
  public function getFormattedReadingTime(NodeInterface $node){
    $int = $this->getReadingTime($node);
    if(empty($int)){
      $int = '< 1';
    }
    return $int . ' min.'; 

  }


  /**
   * Check if bundle is a lemma type.
   *
   * @param string $bundleName
   *   The node bundle name.
   *
   * @return bool
   *   TRUE if lemma, FALSE otherwise.
   */
  public function isLemma(string $bundleName): bool {
    $lemmas = ['concept', 'document', 'institution', 'instrument', 'person', 'place', 'story'];
    return in_array($bundleName, $lemmas);
  }

}  