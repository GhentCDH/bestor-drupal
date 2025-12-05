<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\node\NodeInterface;

class NodeContentAnalyzer {

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


  public function getReadingTime(NodeInterface $node): int {
    return ceil($this->countContentWords($node) / 250);
  }


  public function isLemma(string $bundleName): bool {
    $lemmas = ['concept', 'document', 'institution', 'instrument', 'person', 'place', 'story'];
    return in_array($bundleName, $lemmas);
  }

}