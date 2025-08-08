<?php

namespace Drupal\relationship_nodes\Service;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\field\Entity\FieldConfig;

class ReferenceFieldHelper {
        public function getFieldTargetBundles(FieldConfig $field_config):array {
        if($field_config->getType() != 'entity_reference'){
            return [];
        }

        $settings = $field_config->get('settings') ?? [];
        $handler_settings = $settings['handler_settings'] ?? [];
        $target_bundles = $handler_settings['target_bundles'] ?? [];
       
        return is_array($target_bundles) ? array_values($target_bundles) : [];
    }

    public function getFieldListTargetIds(EntityReferenceFieldItemList $list): array{
        $result = []; 
        foreach ($list->getValue() as $item) {
          if (is_array($item) && isset($item['target_id'])) {
              $result[] = (int) $item['target_id'];
          }
      }
        return $result;
    }
}