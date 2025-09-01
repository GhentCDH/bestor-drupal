<?php

namespace Drupal\relationship_nodes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\field\Entity\FieldConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RnFieldDeleteController extends ControllerBase {

  public function delete(FieldConfig $field_config) {
    $rn_field = (bool) $field_config->getThirdPartySetting('relationship_nodes', 'rn_created', FALSE);
    $propertyManager = \Drupal::service('relationship_nodes.relation_property_manager');
    $relation_entity = $propertyManager->isRelationEntity($field_config->getTargetBundle());
  
    if($relation_entity){
        $this->messenger()->addError($this->t('This field cannot be deleted because it is managed by Relationship Nodes.'));
    } elseif ($rn_field) {
        $field_config->delete();
        $this->messenger()->addStatus($this->t('RN-managed field deleted.'));
    }
    
    return $this->configuredRedirect($field_config);
}

    protected function configuredRedirect(FieldConfig $field_config):RedirectResponse{
        $entity_type = $field_config->getTargetEntityTypeId();
        $bundle = $field_config->getTargetBundle();

        switch($entity_type){
            case 'node':
                return $this->redirect('entity.node.field_ui_fields', ['node_type' => $bundle]);
            case 'taxonomy_term':
                return $this->redirect('entity.taxonomy_term.field_ui_fields', ['taxonomy_vocabulary' => $bundle]);
        }
    }
}
