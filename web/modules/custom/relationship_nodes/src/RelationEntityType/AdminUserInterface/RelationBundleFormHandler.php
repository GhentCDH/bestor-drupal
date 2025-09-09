<?php

namespace Drupal\relationship_nodes\RelationEntityType\AdminUserInterface;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Drupal\relationship_nodes\RelationEntityType\RelationField\RelationFieldConfigurator;
use Drupal\relationship_nodes\RelationEntityType\AdminUserInterface\FieldConfigUiUpdater;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

class RelationBundleFormHandler {

    use StringTranslationTrait;

    protected RelationBundleSettingsManager $settingsManager;
    protected RelationFieldConfigurator $fieldConfigurator;
    protected FieldConfigUiUpdater $fieldUiUpdater;

    public function __construct(
        RelationBundleSettingsManager $settingsManager,
        RelationFieldConfigurator $fieldConfigurator,
        FieldConfigUiUpdater $fieldUiUpdater,
    ) {
        $this->settingsManager = $settingsManager;
        $this->fieldConfigurator = $fieldConfigurator;
        $this->fieldUiUpdater = $fieldUiUpdater;
    }


    public function handleSubmission(array &$form, FormStateInterface $form_state) {
        $entity = $this->getFormEntity($form_state);
        if (!$entity) {
            return;
        }
        $values = $form_state->getValue('relationship_nodes') ?? [];
        $this->settingsManager->setProperties($entity, $values); 
        if (!$this->settingsManager->isRelationEntity($entity)) {
            return;
        }

        $updates = $this->fieldConfigurator->implementFieldUpdates($entity);

        if(isset($updates['created'])){
            $this->showFieldCreationMessage($entity, $updates['created']);
        }
    }


    public function getFormEntity(FormStateInterface $form_state): NodeType|Vocabulary|null {
        $entity = $form_state->getFormObject()->getEntity();
        return ($entity instanceof NodeType || $entity instanceof Vocabulary) ? $entity : null;
    }


    protected function showFieldCreationMessage(ConfigEntityBundleBase $entity, array $missing_fields): void {
        if(empty($missing_fields)){
            return;
        }

        $url_info = $this->fieldUiUpdater->getDefaultRoutingInfo($this->settingsManager->getEntityTypeId($entity));
        $url = Url::fromRoute($url_info['field_ui_fields_route'], [
            $url_info['bundle_param_key'] => $entity->id(),
        ]);

        $link = Link::fromTextAndUrl($this->t('Manage fields'), $url)->toString();

        if($entity instanceof NodeType){
            $message = 'The following relationship fields were created but need to be configured: @fields. @link';
        } else {
            $message = 'The following relationship fields were created: @fields. You can review them here: @link';
        }
        
        \Drupal::messenger()->addStatus($this->t(
             $message, ['@fields' => implode(', ', array_keys($missing_fields)), '@link' => $link]
        ));
    }
}