<?php

namespace Drupal\relationship_nodes_search\TypedData;

use Drupal\Core\TypedData\ComplexDataDefinitionBase;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\relationship_nodes_search\TypedData\RelationInfoData;

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
        $properties = [];
        if (!$this->bundle) {
            return $properties;
        }

        $fields = $this->entityFieldManager->getFieldDefinitions('node', $this->bundle);

        foreach ($fields as $field_name => $field_definition) {
            if ($field_definition->isBaseField()) {
                continue;
            }

            $field_type = $field_definition->getType();

            switch ($field_type) {
            case 'entity_reference':
                $target_type = $field_definition->getSetting('target_type') ?: 'entity';
                $property = DataDefinition::create('entity:' . $target_type)
                ->setLabel($field_definition->getLabel())
                ->setDescription($field_definition->getDescription());
                break;
            case 'datetime_range':
                $property = DataDefinition::create('search_api_elasticsearch_client_date_range')
                    ->setLabel($field_definition->getLabel())
                    ->setDescription($field_definition->getDescription());
                break;
            case 'boolean':
                $property = DataDefinition::create('boolean')
                ->setLabel($field_definition->getLabel())
                ->setDescription($field_definition->getDescription());
                break;
            case 'integer':
            case 'decimal':
            case 'float':
                $property = DataDefinition::create('float')
                ->setLabel($field_definition->getLabel())
                ->setDescription($field_definition->getDescription());
                break;
            default:
                $property = DataDefinition::create('string')
                ->setLabel($field_definition->getLabel())
                ->setDescription($field_definition->getDescription());
                break;
            }

             $properties[$field_name] = $property;
        }
        return $properties;
    }
}
