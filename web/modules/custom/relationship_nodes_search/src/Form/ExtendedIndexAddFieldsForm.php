<?php

namespace Drupal\relationship_nodes_search\Form;

use Drupal\search_api\Form\IndexAddFieldsForm;
use Drupal\Core\Render\Element;

class ExtendedIndexAddFieldsForm extends IndexAddFieldsForm {
    public function preRenderForm(array $form): array {
        $form = parent::preRenderForm($form);
        $form['#attached']['library'][] = 'relationship_nodes_search/relationship_form';
        $rows = &$form['datasources']['datasource_entity:node']['table']['#rows'] ?? [];
        foreach($rows as &$row){
            $machine_name = $row['machine_name']['data'] ?? '';
            if (!str_starts_with($machine_name, 'relationship_info__') || !isset($row['add']['data'])) {
                continue;
            }
            if (substr_count($machine_name, ':') === 0) {
                $row['add']['data'] = [
                    '#type' => 'submit',
                    '#name' => 'relationship_add_parent_' . str_replace(':', '__', $machine_name),
                    '#value' => $this->t('Add'),
                    '#submit' => ['::addRelationshipGroup', '::save'],
                    '#attributes' => [
                        'class' => ['button', 'button--primary', 'button--extrasmall', 'relationship-parent-add'],
                        'disabled' => 'disabled',
                    ],
                ];
            } else {
                $row['add']['data'] = [
                    '#type' => 'checkbox',
                    '#title' => '',
                    '#name' => 'relationship_add_' . str_replace(':', '__', $machine_name),
                    '#attributes' => [
                        'class' => ['relationship-child-checkbox']
                    ],
                ];
            }                    
        }
        return $form;
    }

    public function addRelationshipGroup(array $form, FormStateInterface $form_state): void {
        //  HIEROP MOET VERDER GEWERKT WORDEN!!!
        $button = $form_state->getTriggeringElement();
        if (!$button || empty($button['#name'])) {
            return;
        }

        $parent_machine_name = str_replace('__', ':', str_replace('relationship_add_parent_', '', $button['#name']));
        $values = $form_state->getValues();

        foreach ($form['datasources']['datasource_entity:node']['table']['#rows'] as $row) {
            $machine_name = $row['machine_name']['data'] ?? '';
            
            // Enkel children van de juiste parent.
            if (!str_starts_with($machine_name, 'relationship_info__')) {
            continue;
            }

            if (!str_starts_with($machine_name, $parent_machine_name . ':')) {
            continue;
            }

            $checkbox_name = 'relationship_add_' . str_replace(':', '__', $machine_name);

            if (!empty($values[$checkbox_name])) {
            /** @var \Drupal\Core\TypedData\DataDefinitionInterface $property */
            $property = $row['add']['data']['#property'] ?? NULL;

            if (!$property) {
                continue;
            }

            $field = $this->fieldsHelper->createFieldFromProperty(
                $this->entity,
                $property,
                'entity:node',
                $machine_name,
                NULL,
                $row['add']['data']['#data_type'] ?? 'string' // fallback
            );

            $field->setLabel($row['add']['data']['#prefixed_label'] ?? $machine_name);
            $this->entity->addField($field);

            $args['%label'] = $field->getLabel();
            $this->messenger->addStatus($this->t('Field %label was added to the index.', $args));
            }
        }

        // Redirect zoals bij enkel veld.
        $form_state->setRedirect('entity.search_api_index.fields', ['search_api_index' => $this->entity->id()]);
        }

}