<?php

namespace Drupal\relationship_nodes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\relationship_nodes\RelationEntityType\AdminUserInterface\FieldConfigUiUpdater;
use Drupal\relationship_nodes\RelationEntityType\RelationBundle\RelationBundleSettingsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;


class RnFieldDeleteController extends ControllerBase {

    protected RelationBundleSettingsManager $settingsManager;
    protected FieldConfigUiUpdater $uiUpdater;


    /**
     * Constructs a RnFieldDeleteController object.
     *
     * @param RelationBundleSettingsManager $settingsManager
     *   The settings manager service.
     * @param FieldConfigUiUpdater $uiUpdater
     *   The UI updater service.
     */
    public function __construct(RelationBundleSettingsManager $settingsManager, FieldConfigUiUpdater $uiUpdater) {
        $this->settingsManager = $settingsManager;
        $this->uiUpdater = $uiUpdater;
    }


    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container): self {
        return new static(
            $container->get('relationship_nodes.relation_bundle_settings_manager'),
            $container->get('relationship_nodes.field_config_ui_updater')
        );
    }

    
    /**
     * Deletes a field configuration.
     *
     * @param FieldConfig $field_config
     *   The field configuration entity.
     *
     * @return RedirectResponse
     *   Redirect response to the field list.
     */
    public function delete(FieldConfig $field_config):RedirectResponse {
        $rn_field = (bool) $field_config->getThirdPartySetting('relationship_nodes', 'rn_created', FALSE);
        $relation_entity = $this->settingsManager->isRelationEntity($field_config->getTargetBundle());
    
        $redirect_url = $this->uiUpdater->getRedirectUrl($field_config);
        if($relation_entity){
            $this->messenger()->addError($this->t('This field cannot be deleted because it is managed by Relationship Nodes.'));
        } elseif ($rn_field) {
            $field_config->delete();
            $this->messenger()->addStatus($this->t('RN-managed field deleted.'));
        }
        
        return new RedirectResponse($redirect_url->toString());
    }
}