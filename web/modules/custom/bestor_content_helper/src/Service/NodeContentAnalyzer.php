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




  public function getLemmaKeyData(NodeInterface $node, string $display_type = 'full'): ? array {
    $node_type = $node->getType();
    $extra_elements = $this->getLemmaTypeToElementMapping($node_type, $display_type);

    if (!$extra_elements) {
      return NULL;
    }

    $return_values = [];
    foreach ($extra_elements as $el_key){
      $el_def = $this->getElementToFieldMapping($el_key);

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
          'value' => $this->formatLemmaKeyData($fld_vals),
          'icon' => $el_def['icon']
        ];
      }
    }
    return empty($return_values) ? NULL : $return_values;
  }


  protected function formatLemmaKeyData(array $values): string {
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


  public function getElementToFieldMapping(string $element){
    $mapping = [
      'discipline' => [
        'fields' => ['field_discipline'],
        'label_key' => 'lemma_key_discipline',
        'icon' => ''
      ],
      'specialisation' => [
        'fields' => ['field_specialisation'],
        'label_key' => 'lemma_key_specialisation',
        'icon' => ''
      ],
      'typology' => [
        'fields' => ['field_typology'],
        'label_key' => 'lemma_key_typology',
        'icon' => ''
      ],
      'period' => [
        'fields' => ['field_date_start', 'field_date_end'],
        'label_key' => 'lemma_key_period',
        'icon' => ''
      ],
      'location' => [
        'fields' => ['field_country', 'field_municipality'],
        'label_key' => 'lemma_key_location',
        'icon' => 'marker'
      ],
      'birth_death' => [
        'fields' => ['field_date_start', 'field_date_end'],
        'label_key' => 'lemma_key_birth_death',
        'icon' => ''
      ],
      'birth' => [
        'fields' => ['field_date_start', 'field_municipality'],
        'icon' => 'birthdate'
      ],
      'death' => [
        'fields' => ['field_date_end', 'field_end_municipality'],
        'icon' => 'death'
      ],
      'inventor' => [
        'fields' => ['field_inventor'],
        'label_key' => 'lemma_key_inventor',
        'icon' => 'light'
      ],
      'creator' => [
        'fields' => ['field_creator'],
        'label_key' => 'lemma_key_creator',
        'icon' => 'pen'
      ],
      'gender' => [
        'fields' => ['field_gender'],
        'label_key' => 'lemma_key_gender',
        'icon' => ''
      ],
      'alt_names' => [
        'fields' => ['field_alternative_name'],
        'label_key' => 'lemma_key_alt_names',
        'icon' => ''
      ],
    ];
    return empty($mapping[$element]) ? NULL : $mapping[$element];
  }



  public function getLemmaTypeToElementMapping(string $node_type, string $display_type){
    $mapping = [
      '_all' => [
        'full' => ['discipline', 'specialisation', 'typology', 'alt_names'],
        'teaser' => [],
      ],
      'concept' => [
        'full' => ['inventor', 'period'],
        'teaser' => ['inventor'],
      ],
      'document' => [
        'full' => ['creator', 'period'],
        'teaser' => ['creator'],
      ],
      'institution' => [
        'full' => ['location', 'period'],
        'teaser' => ['location'],
      ],
      'instrument' => [
        'full' => ['inventor', 'period'],
        'teaser' => ['inventor'],
      ],
      'person' => [
        'full' => ['gender', 'birth_death'],
        'teaser' => ['birth', 'death'],
      ],
      'place' => [
        'full' => ['location', 'period'],
        'teaser' => ['location'],
      ],
      'story' => [
        'full' => ['period'],
        'teaser' => [],
      ],
    ];

    return array_merge(
      empty($mapping['_all'][$display_type]) ? [] : $mapping['_all'][$display_type],
      empty($mapping[$node_type][$display_type]) ? [] : $mapping[$node_type][$display_type]
    );
  }
}