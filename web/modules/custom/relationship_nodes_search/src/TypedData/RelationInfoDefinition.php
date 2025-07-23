<?php

namespace Drupal\relationship_nodes_search\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes_search\TypedData\RelationInfoData;
use Drupal\field\Entity\FieldConfig; 

class RelationInfoDefinition extends ComplexDataDefinitionBase {

  /**
   * @var string
   */
  protected $bundle;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


    /**
   * @param array $settings
   */
    public function __construct(array $settings = []) {
        parent::__construct();

        $this->bundle = $settings['bundle'] ?? NULL;

        $this->entityFieldManager = \Drupal::service('entity_field.manager');
        $this->entityTypeManager = \Drupal::service('entity_type.manager');
    }

    public function getDataClass() {
        return RelationInfoData::class;
    }

    public function getPropertyDefinitions() {
        if (!isset($this->propertyDefinitions)) {
        $this->propertyDefinitions = $this->buildPropertyDefinitions();
        }
        return $this->propertyDefinitions;
    }

    protected function buildPropertyDefinitions() {
        $definitions = [];
        if (!$this->bundle) {
            return $definitions;
        }

        $fields = $this->entityFieldManager->getFieldDefinitions('node', $this->bundle);
        foreach ($fields as $field_name => $field_config) {
            if (!$field_config instanceof FieldConfig) {
                continue;
            }

            $field_type = $field_config->getType();

            switch ($field_type) {
                case 'entity_reference':
                    dpm('test');
                    $target_type = $field_config->getSetting('target_type') ?: 'entity';
                    $property = DataDefinition::create('entity:' . $target_type)
                    ->setLabel($field_config->getLabel())
                    ->setDescription($field_config->getDescription())
                    ->setSetting('data_type', 'entity_reference');
                    break;
                case 'datetime_range':
                    $property = DataDefinition::create('search_api_elasticsearch_client_date_range')
                        ->setLabel($field_config->getLabel())
                        ->setDescription($field_config->getDescription());
                    break;
                case 'boolean':
                    $property = DataDefinition::create('boolean')
                    ->setLabel($field_config->getLabel())
                    ->setDescription($field_config->getDescription());
                    break;
                case 'integer':
                case 'decimal':
                case 'float':
                    $property = DataDefinition::create('float')
                    ->setLabel($field_config->getLabel())
                    ->setDescription($field_config->getDescription());
                    break;
                default:
                    $property = DataDefinition::create('string')
                    ->setLabel($field_config->getLabel())
                    ->setDescription($field_config->getDescription());
                    break;
            }

             $definitions[$field_name] = $property;
        }
        $this->propertyDefinitions = $definitions;
        return $this->propertyDefinitions;

        // CEHCK Drupal\Core\TypedData\ComplexDataDefinitionInterface; VOOR $property->getMainPropertyName();, WAT NULL GEEFT
    }
}
