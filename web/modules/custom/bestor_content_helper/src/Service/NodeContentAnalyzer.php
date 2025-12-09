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


  protected function getExtraElementFieldMapping(string $element) : ?array {
    $mapping = [
      'name' => [
        'fields' => ['field_alternative_name'],
        'icon' => 'light'
      ],
      'creator' => [
        'fields' => ['field_creator'],
        'icon' => 'pen'
      ],
      'location' => [
        'fields' => ['field_municipality', 'field_country'],
        'icon' => 'marker'
      ],
      'birth' => [
        'fields' => ['field_period', 'field_municipality'],
        'icon' => 'birthdate'
      ],
      'death' => [
        'fields' => ['field_period', 'field_end_municipality'],
        'icon' => 'death'
      ]
    ];
    return empty($mapping[$element]) ? NULL : $mapping[$element];
  }


  protected function getLemmaExtraElementMapping(string $node_type){
    $mapping = [
      'concept' => ['name'],
      'document' => ['creator'],
      'institution' => ['location'],
      'instrument' => ['name'],
      'person' => ['birth', 'death'],
      'place' => ['location'],
      'story' => ['name'],
    ];
    return empty($mapping[$node_type]) ? NULL : $mapping[$node_type];
  }


  public function getLemmaExtraData(NodeInterface $node): ? array {
    $node_type = $node->getType();
    $extra_elements = $this->getLemmaExtraElementMapping($node_type);

    if (!$extra_elements) {
      return NULL;
    }

    $return_values = [];
    foreach ($extra_elements as $el_key){
      $el_def = $this->getExtraElementFieldMapping($el_key);

      if(empty($el_def)){
        continue;
      }

      $fields = $el_def['fields'];
      $fld_vals = [];
      foreach($fields as $fld_nm){
        $node_val = $this->getNodeValue($node, $fld_nm);
        if(empty($node_val)){
          continue;
        }
        $fld_def = $node->get($fld_nm)->getFieldDefinition();
        $fld_type = $fld_def->getType();
        switch ($fld_type) {
          case 'entity_reference':
            $target_type = $fld_def->getSettings()['target_type'];
            $str_val = $this->entityRefFieldToResultString($node, $fld_nm, $target_type);
            break;
          case 'daterange':
            if ($el_key === 'death') {
              $str_val = $node_val[0]['end_value'];
              break;
            }
          default:
            $str_val = $node_val[0]['value'];
            break;
        }
        if($str_val){
          $fld_vals[] = $str_val;
        }
      } 
      if ($fld_vals){
        $return_values[] = [
          'value' => $this->formatLemmaExtraData($fld_vals),
          'icon' => $el_def['icon']
        ];
      }
    }
    return empty($return_values) ? NULL : $return_values;
  }


  protected function formatLemmaExtraData(array $values): string {
    if (empty($values)) {
      return '';
    } 
    if (count($values) === 1) {
      return strval($values[0]);
    }
    
    return strval($values[0]) . ' (' . strval($values[1]) . ')';
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


  public function stringFieldToResultArray(NodeInterface $node, string $field_name): ?array{
    $values = $this->getNodeValue($node, $field_name);
    if(is_string($values)){
      return [$values];
    }
    if(!is_array($values)){
      return NULL;
    }
    $result = [];
    foreach ($values as $value) {
      if(empty($value['value'])){
        continue;
      }
      $result[] = $value['value'];
    }
    return $result;
  }
}