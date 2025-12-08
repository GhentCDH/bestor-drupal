<?php

namespace Drupal\bestor_content_helper\Service;


use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;


class NodeContentAnalyzer {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->entityTypeManager = $entity_type_manager;
  }

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

  public function getFormattedReadingTime(NodeInterface $node){
    $int = $this->getReadingTime($node);
    if(empty($int)){
      $int = '< 1';
    }
    return $int . ' min.'; 

  }


  public function isLemma(string $bundleName): bool {
    $lemmas = ['concept', 'document', 'institution', 'instrument', 'person', 'place', 'story'];
    return in_array($bundleName, $lemmas);
  }


  protected function getNodeValue(NodeInterface $node, string $field_name){
    if (!$node->hasField($field_name) || empty($node->get($field_name)->getValue())) {
      return NULL;
    }
    return $node->get($field_name)->getValue();
  }

  public function entityRefFieldToResultArray(NodeInterface $node, string $field_name, string $target_entity_type): ?array{
    if(!in_array($target_entity_type, ['taxonomy_term', 'node'])){
      return NULL;
    }
    $values = $this->getNodeValue($node, $field_name);
    if(empty($values)){
      return NULL;
    }

    $result = [];
    $storage = $this->entityTypeManager->getStorage($target_entity_type);
    foreach($values as $value){
      if(empty($value['target_id'])){
        continue;
      }
      $target = $storage->load($value['target_id']);
      if(empty($target)){
        continue;
      }
      if($target_entity_type === 'taxonomy_term') {
        $result[] = $target->getName();
      } else {
        $result[] = $target->getTitle();
      }
      
    }
    
    return $result;
  }

   public function entityRefFieldToResultString(NodeInterface $node, string $field_name, string $target_entity_type): ?string{
    $array = $this->entityRefFieldToResultArray($node, $field_name, $target_entity_type);
    if(empty($array)){
      return NULL;
    }
    return implode(', ', $array);
  }

  public function getBoolValue(NodeInterface $node, string $field_name):bool {
    $value = $this->getNodeValue($node, $field_name);
    if(empty($value[0]['value']) ){
      return FALSE;
    }
    return TRUE;
  }
}