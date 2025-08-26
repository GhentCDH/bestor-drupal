<?php

namespace Drupal\relationship_nodes\FormAlter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\relationship_nodes\Service\RelationEntityTypePreparer;
use Drupal\node\Entity\ConfigEntityBundleBase;
use Drupal\node\Entity\NodeType;

class NodeTypeFormAlter {

    use StringTranslationTrait;

    protected RelationEntityTypePreparer $relationEntityPreparer;

    public function __construct(
        RelationEntityTypePreparer $relationEntityPreparer,
    ) {
        $this->relationEntityPreparer = $relationEntityPreparer;
    }

    public function alterForm(array &$form, FormStateInterface $form_state, $form_id) {
        $node_type = $this->relationEntityPreparer->getFormEntity($form_state);
        if(!$node_type instanceof NodeType){
            return;
        }
        
        $form['relationship_nodes'] = [
            '#type' => 'details',
            '#title' => $this->t('Relationship Node'),
            '#collapsed' => FALSE,
            '#collapsible' => TRUE,
            '#tree' => TRUE,
            '#group' => 'additional_settings',
            '#weight' => 1,
            '#attributes' => ['class' => ['relationship-nodes-settings-form']],
        ];

        $form['relationship_nodes']['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('This is a relationship node entity type'),
            '#default_value' => $this->relationEntityPreparer->getRelationEntityProperty($node_type, 'enabled'),
            '#description' => $this->t('If this is checked, this content type will be validated as a relationship node. It gets two "related entity fields" that need to be configured.'),
        ];


        $form['relationship_nodes']['typed_relation'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('This is a typed relation: a vocabulary term describes the relation type'),
            '#default_value' => $this->relationEntityPreparer->getRelationEntityProperty($node_type, 'typed_relation'),
            '#description' => $this->t('If this is checked, this content type will be validated as a typed relationship node. It get an extra "relation type feeld" that needs to be configured.'),
            '#states' => [
                'visible' => [
                    ':input[name="relationship_nodes[enabled]"]' => ['checked' => TRUE],
                ],
            ],
        ];

        $form['relationship_nodes']['auto_title'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Hide title field and generate a title automatically'),
            '#default_value' =>  $this->relationEntityPreparer->getRelationEntityProperty($node_type, 'auto_title'),
            '#description' => $this->t('If this is checked, the title field will automatically filled/updated on node save.'),
            '#states' => [
                    'visible' => [
                        ':input[name="relationship_nodes[enabled]"]' => ['checked' => TRUE],
                    ],
                ],
        ];

        $form['#validate'][] = [$this->relationEntityPreparer,  'detectRelationEntityConfigConflicts'];
        $form['actions']['submit']['#submit'][] = [$this->relationEntityPreparer,  'handleRelationEntitySubmission'];
    }

}