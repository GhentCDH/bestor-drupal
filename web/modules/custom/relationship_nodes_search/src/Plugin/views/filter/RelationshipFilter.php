<?php

namespace Drupal\relationship_nodes_search\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes_search\Service\RelationSearchService;
use Drupal\relationship_nodes_search\SearchAPI\Query\NestedParentFieldConditionGroup;

/**
 * Filter for nested relationship data in Search API.
 *
 * @ViewsFilter("search_api_relationship_filter")
 */
class RelationshipFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

    use SearchApiFilterTrait;

    protected RelationSearchService $relationSearchService;

    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        RelationSearchService $relationSearchService,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->relationSearchService = $relationSearchService;
    }
    
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('relationship_nodes_search.relation_search_service'),
        );
    }

    public function defineOptions() {
        $options = parent::defineOptions();
        
        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
            $options[$option] = ['default' => $default];
        }
      
        return $options;
    }

     protected function getDefaultRelationFieldOptions(){
        return [
            'filter_field_settings' => [],
            'operator' => 'and',
            'expose_operators' => FALSE,
        ];
    }

   public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    
    $available_fields = $this->getCalculatedFields();
    
    if (empty($available_fields)) {
        $form['info'] = [
            '#markup' => $this->t('No nested fields available. Please configure nested fields in the Search API index.'),
        ];
        return;
    }

    $form['operator'] = [
        '#type' => 'radios',
        '#title' => $this->t('Operator'),
        '#options' => [
            'and' => $this->t('AND - All conditions must match'),
            'or' => $this->t('OR - Any condition can match'),
        ],
        '#default_value' => $this->options['operator'] ?? 'and',
        '#description' => $this->t('How to combine multiple filter fields.'),
    ];

    $form['expose_operators'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Allow users to select operators'),
        '#default_value' => $this->options['expose_operators'] ?? FALSE,
        '#description' => $this->t('When exposed, allow users to choose the comparison operator for each field.'),
    ];

    $form['filter_field_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Filter fields'),
        '#description' => $this->t('Select which fields should be available for filtering.'),
        '#tree' => TRUE,
    ];

    $filter_field_settings = $this->options['filter_field_settings'] ?? [];

    foreach ($available_fields as $field_name) {
        $is_enabled = !empty($filter_field_settings[$field_name]['enabled']);
        
        $form['filter_field_settings'][$field_name] = [
            '#type' => 'details',
            '#title' => $field_name,
            '#open' => $is_enabled,
        ];

        $form['filter_field_settings'][$field_name]['enabled'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Enable this filter field'),
            '#default_value' => $is_enabled,
        ];

        $form['filter_field_settings'][$field_name]['label'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Label'),
            '#default_value' => $filter_field_settings[$field_name]['label'] ?? $this->formatFieldLabel($field_name),
            '#description' => $this->t('Label shown to users when exposed.'),
            '#size' => 30,
            '#states' => [
                'disabled' => [
                    ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                ],
            ],
        ];

        $form['filter_field_settings'][$field_name]['widget'] = [
            '#type' => 'select',
            '#title' => $this->t('Widget type'),
            '#options' => [
                'textfield' => $this->t('Text field'),
                'entity_autocomplete' => $this->t('Entity autocomplete'),
            ],
            '#default_value' => $filter_field_settings[$field_name]['widget'] ?? 'textfield',
            '#states' => [
                'disabled' => [
                    ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                ],
            ],
        ];

        $form['filter_field_settings'][$field_name]['weight'] = [
            '#type' => 'number',
            '#title' => $this->t('Weight'),
            '#default_value' => $filter_field_settings[$field_name]['weight'] ?? 0,
            '#description' => $this->t('Fields with lower weights appear first.'),
            '#size' => 5,
            '#states' => [
                'disabled' => [
                    ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                ],
            ],
        ];

        $form['filter_field_settings'][$field_name]['required'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Required'),
            '#default_value' => $filter_field_settings[$field_name]['required'] ?? FALSE,
            '#description' => $this->t('Make this field required when exposed.'),
            '#states' => [
                'disabled' => [
                    ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                ],
            ],
        ];

        $form['filter_field_settings'][$field_name]['placeholder'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Placeholder'),
            '#default_value' => $filter_field_settings[$field_name]['placeholder'] ?? '',
            '#description' => $this->t('Placeholder text for the filter field.'),
            '#states' => [
                'disabled' => [
                    ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                ],
            ],
        ];

        $form['filter_field_settings'][$field_name]['field_operator'] = [
            '#type' => 'select',
            '#title' => $this->t('Operator'),
            '#options' => $this->getOperatorOptions(),
            '#default_value' => $filter_field_settings[$field_name]['field_operator'] ?? '=',
            '#description' => $this->t('Comparison operator for this field.'),
            '#states' => [
                'disabled' => [
                    ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                ],
            ],
        ];

        $form['filter_field_settings'][$field_name]['expose_field_operator'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Let user choose operator'),
            '#default_value' => $filter_field_settings[$field_name]['expose_field_operator'] ?? FALSE,
            '#description' => $this->t('Override global setting for this specific field.'),
            '#states' => [
                'disabled' => [
                    ':input[name="options[filter_field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
                ],
                'visible' => [
                    ':input[name="options[expose_operators]"]' => ['checked' => TRUE],
                ],
            ],
        ];
    }

    // Expose button
    $this->showExposeButton($form, $form_state);
}

public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    
    // Haal waarden direct uit options, niet uit filter_configuration
    foreach($this->getDefaultRelationFieldOptions() as $option => $default){
        $value = $form_state->getValue(['options', $option]);
        if (isset($value)) {
            $this->options[$option] = $value;
        }
    }
}

    protected function valueForm(&$form, FormStateInterface $form_state) {
        if (!$this->options['exposed']) {
            return;
        }
        
        $filter_field_settings = $this->options['filter_field_settings'] ?? [];
        
        // Filter alleen enabled fields
        $enabled_fields = array_filter($filter_field_settings, function($field_config) {
            return !empty($field_config['enabled']);
        });

        // Als er geen enabled fields zijn, toon gewoon niets (geen foutmelding)
        if (empty($enabled_fields)) {
            return;
        }

        // Sorteer op weight
        uasort($enabled_fields, function($a, $b) {
            return ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
        });

        $form['value'] = [
            '#type' => 'container',
            '#tree' => TRUE,
        ];

        foreach ($enabled_fields as $field_name => $field_config) {
            $widget_type = $field_config['widget'] ?? 'textfield';
            $label = $field_config['label'] ?? $this->formatFieldLabel($field_name);
            $required = !empty($field_config['required']);
            $placeholder = $field_config['placeholder'] ?? '';
            $expose_operators = $this->options['expose_operators'] ?? FALSE;
            $expose_field_operator = !empty($field_config['expose_field_operator']);

            // Container voor veld + eventueel operator
            $form['value'][$field_name] = [
                '#type' => 'container',
                '#attributes' => ['class' => ['relationship-filter-field-wrapper']],
            ];

            // Operator selectie indien exposed (globaal OF per veld)
            if ($expose_operators && $expose_field_operator) {
                $form['value'][$field_name]['operator'] = [
                    '#type' => 'select',
                    '#title' => $this->t('Operator'),
                    '#options' => $this->getOperatorOptions(),
                    '#default_value' => $this->value[$field_name]['operator'] ?? $field_config['field_operator'] ?? '=',
                    '#attributes' => ['class' => ['relationship-filter-operator']],
                ];
            }

            // Het eigenlijke veld
            switch ($widget_type) {
                case 'entity_autocomplete':
                    $target_type = $this->getTargetTypeForField($field_name);
                    $default_entity = $this->getDefaultEntityValue($field_name, $target_type);
                    
                    $form['value'][$field_name]['value'] = [
                        '#type' => 'entity_autocomplete',
                        '#title' => $label,
                        '#target_type' => $target_type,
                        '#default_value' => $default_entity,
                        '#required' => $required,
                        '#placeholder' => $placeholder,
                    ];
                    break;

                case 'textfield':
                default:
                    $form['value'][$field_name]['value'] = [
                        '#type' => 'textfield',
                        '#title' => $label,
                        '#default_value' => $this->value[$field_name]['value'] ?? $this->value[$field_name] ?? '',
                        '#required' => $required,
                        '#placeholder' => $placeholder,
                    ];
                    break;
            }
        }
    }

    public function query() {
        if (!$this->getQuery()) {
            return;
        }

        $filter_field_settings = $this->options['filter_field_settings'] ?? [];
        $operator = $this->options['operator'] ?? 'and';
        $parent_field = $this->getRealField();

        if (empty($parent_field)) {
            return;
        }

        $conditions = [];
        foreach ($filter_field_settings as $sub_field_name => $field_config) {
            if (empty($field_config['enabled'])) {
                continue;
            }

            $value = $this->value[$sub_field_name]['value'] ?? $this->value[$sub_field_name] ?? '';
            
            if ($value === '' || $value === NULL) {
                continue;
            }

            $field_operator = '=';

            if (!empty($field_config['expose_field_operator']) && isset($this->value[$sub_field_name]['operator'])) {
                $field_operator = $this->value[$sub_field_name]['operator'];
            } elseif (!empty($field_config['field_operator'])) {
                $field_operator = $field_config['field_operator'];
            }

            if (!$this->isValidOperator($field_operator)) {
                $field_operator = '=';
            }

            $conditions[] = [
                'sub_field_name' => $sub_field_name,
                'value' => $value,
                'operator' => $field_operator,
            ];
        }
        
        if (empty($conditions)) {
            return;
        }

        $nested_field_condition = new NestedParentFieldConditionGroup(strtoupper($operator));
        $nested_field_condition->setParentFieldName($parent_field);
        
        foreach ($conditions as $condition) {
            $nested_field_condition->addSubFieldCondition(
                $condition['sub_field_name'],
                $condition['value'],
                $condition['operator']
            );
        }
        
        $this->query->addConditionGroup($nested_field_condition);
    }



    public function adminSummary() {
        if (!$this->isExposed()) {
            return parent::adminSummary();
        }

        $filter_field_settings = $this->options['filter_field_settings'] ?? [];
        $enabled = array_filter($filter_field_settings, function($config) {
            return !empty($config['enabled']);
        });

        if (empty($enabled)) {
            return $this->t('Not configured');
        }

        $operator = $this->options['operator'] ?? 'and';
        
        return $this->t('@count fields (@operator)', [
            '@count' => count($enabled),
            '@operator' => strtoupper($operator),
        ]);
    }

    protected function getTargetTypeForField(string $field_name): string {
        // Bepaal entity type op basis van field name
        if (strpos($field_name, 'relation_type') !== FALSE) {
            return 'taxonomy_term';
        }
        return 'node';
    }

protected function getDefaultEntityValue(string $field_name, string $target_type) {
    // Check beide mogelijke locaties voor de waarde
    $value = $this->value[$field_name]['value'] ?? $this->value[$field_name] ?? NULL;
    
    if (empty($value) || !is_numeric($value)) {
        return NULL;
    }

    try {
        $entity = \Drupal::entityTypeManager()
            ->getStorage($target_type)
            ->load($value);
        return $entity;
    } catch (\Exception $e) {
        return NULL;
    }
}

    protected function formatFieldLabel(string $field_name): string {
        $label = str_replace(['calculated_', '_'], ['', ' '], $field_name);
        return ucfirst(trim($label));
    }

    protected function getRealField(): ?string {
        return $this->definition['real field'] ?? null;
    }

    protected function getCalculatedFields(): array {
        $index = $this->getIndex();
        $real_field = $this->getRealField();    

        if (!$index instanceof Index || empty($real_field)) {
            return [];
        }

        return $this->relationSearchService->getCalculatedFields($index, $real_field) ?? [];
    }

    protected function getOriginalNestedFields(): array {
        $index = $this->getIndex();
        $real_field = $this->getRealField();          
        
        if (!$index instanceof Index || empty($real_field)) {
            return [];
        }

        return $this->relationSearchService->getOriginalNestedFields($index, $real_field) ?? [];
    }

    public function canExpose() {
        return TRUE;
    }

public function showExposeForm(&$form, FormStateInterface $form_state) {
    parent::showExposeForm($form, $form_state);
    
    $form['expose']['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $this->options['expose']['description'] ?? '',
        '#description' => $this->t('Description shown above the filter fields.'),
        '#rows' => 2,
    ];
    
    // TOEVOEGEN: Toon configuratie ook in expose form
    $form['expose']['info'] = [
        '#type' => 'markup',
        '#markup' => '<p><strong>' . $this->t('Note: To configure which fields are available in this filter, close the expose settings first.') . '</strong></p>',
    ];
    
    // Of beter: kopieer de field configuratie naar hier
    $available_fields = $this->getCalculatedFields();
    
    if (!empty($available_fields)) {
        $form['expose']['filter_field_settings_summary'] = [
            '#type' => 'details',
            '#title' => $this->t('Configured filter fields'),
            '#open' => FALSE,
        ];
        
        $filter_field_settings = $this->options['filter_field_settings'] ?? [];
        $enabled_fields = array_filter($filter_field_settings, function($config) {
            return !empty($config['enabled']);
        });
        
        if (empty($enabled_fields)) {
            $form['expose']['filter_field_settings_summary']['warning'] = [
                '#markup' => '<div class="messages messages--warning">' . 
                    $this->t('No filter fields are enabled. Close expose settings and configure fields first.') . 
                    '</div>',
            ];
        } else {
            $items = [];
            foreach ($enabled_fields as $field_name => $config) {
                $label = $config['label'] ?? $field_name;
                $operator = $config['field_operator'] ?? '=';
                $items[] = $label . ' (' . $operator . ')';
            }
            
            $form['expose']['filter_field_settings_summary']['list'] = [
                '#theme' => 'item_list',
                '#items' => $items,
            ];
        }
    }
}

public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);
    
    // Alleen valideren als de filter exposed is
    $exposed = $form_state->getValue(['options', 'expose', 'expose']);
    
    if ($exposed) {
        // AANGEPAST: niet meer via filter_configuration
        $filter_field_settings = $form_state->getValue(['options', 'filter_field_settings']) ?? [];
        
        $has_enabled = false;
        foreach ($filter_field_settings as $field_config) {
            if (!empty($field_config['enabled'])) {
                $has_enabled = true;
                break;
            }
        }
        
        if (!$has_enabled) {
            $form_state->setErrorByName('filter_field_settings', 
                $this->t('You must enable at least one filter field before exposing this filter.')
            );
        }
    }
}

    protected function getOperatorOptions(): array {
    return [
        '=' => $this->t('Is equal to'),
        '!=' => $this->t('Is not equal to'),
        '<' => $this->t('Is less than'),
        '<=' => $this->t('Is less than or equal to'),
        '>' => $this->t('Is greater than'),
        '>=' => $this->t('Is greater than or equal to'),
        'IN' => $this->t('Is one of'),
        'NOT IN' => $this->t('Is not one of'),
        'BETWEEN' => $this->t('Is between'),
        'NOT BETWEEN' => $this->t('Is not between'),
        '<>' => $this->t('Contains'),
    ];
}

    protected function isValidOperator(string $operator): bool {
        return array_key_exists($operator, $this->getOperatorOptions());
    }
}