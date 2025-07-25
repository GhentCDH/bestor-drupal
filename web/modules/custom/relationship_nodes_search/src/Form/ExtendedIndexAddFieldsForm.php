<?php

namespace Drupal\relationship_nodes_search\Form;

use Drupal\search_api\Form\IndexAddFieldsForm;
use Drupal\Core\Url;
use Drupal\Core\Render\Element;

class ExtendedIndexAddFieldsForm extends IndexAddFieldsForm {
/*
    protected function getPropertiesList(
    array $properties,
    string $active_property_path,
    Url $base_url,
    ?string $datasource_id,
    string $parent_path = '',
    string $label_prefix = '',
    int $depth = 0,
    array $rows = [],
    ): array {
        $rows = parent::getPropertiesList($properties, $active_property_path, $base_url, $datasource_id, $parent_path, $label_prefix, $depth, $rows);
        foreach ($rows as $key => &$row) {
            if (!isset($row['machine_name']['data'])) {
            continue;
            }
            $machine_name = $row['machine_name']['data'];
            if (!str_starts_with($machine_name, 'relationship_info__')) {
                continue;
            }
            if (substr_count($machine_name, ':') === 0) {
                $row['add']['data'] = [
                    '#type' => 'submit',
                    '#name' => 'relationship_add_parent_' . str_replace(':', '__', $machine_name),
                    '#value' => $this->t('Add'),
                    '#attributes' => [
                        'class' => ['button', 'button--primary', 'button--extrasmall'],
                        'disabled' => 'disabled',
                    ],
                ];
            } else{
                dpm('yes');
                $row['add']['data'] = [
                        '#type' => 'checkbox',
                        '#title' => '',
                        '#name' => 'relationship_add_' . str_replace(':', '__', $machine_name),
                    ];
            }
        }    
        return $rows;
    }*/

    public function preRenderForm(array $form): array {
        $form = parent::preRenderForm($form);
        //dpm($form);
        dpm(Element::children($form['add_field_buttons']));
        $rows = &$form['datasources']['datasource_entity:node']['table']['#rows'] ?? [];
        foreach($rows as $row){
            $machine_name = $row['machine_name']['data'] ?? '';
            if (str_starts_with($machine_name, 'relationship_info__')) {
                $row['add']['data'] = [
                    '#type' => 'checkbox',
                    '#title' => '',
                    '#name' => 'relationship_add_' . str_replace(':', '__', $machine_name),
                ];
            }
        }
/*
        foreach (Element::children($form['datasources']) as $ds_key) {
            $rows = &$form['datasources'][$ds_key]['table']['#rows'] ?? [];
            foreach ($rows as &$row) {
            $machine_name = $row['machine_name']['data'] ?? '';
            if (str_starts_with($machine_name, 'relationship_info__')) {
                // Vervang de "Add"-knop door een checkbox.
                $row['add']['data'] = [
                '#type' => 'checkbox',
                '#title' => '',
                '#name' => 'relationship_add_' . str_replace(':', '__', $machine_name),
                ];
            }
            }
        }
*/
        return $form;
    }
}