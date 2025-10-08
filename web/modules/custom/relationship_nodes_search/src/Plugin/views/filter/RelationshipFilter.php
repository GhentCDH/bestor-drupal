<?php

namespace Drupal\relationship_nodes_search\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\relationship_nodes\RelationEntityType\RelationField\FieldNameResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\relationship_nodes_search\Service\RelationViewService;

/**
 * Filter for nested relationship data.
 *
 * @ViewsFilter("search_api_relationship_filter")
 */
class RelationshipFilter extends FilterPluginBase implements ContainerFactoryPluginInterface {

    use SearchApiFilterTrait;

    protected RelationViewService $relationViewService;


    public function __construct(
        array $configuration,
        string $plugin_id,
        mixed $plugin_definition,
        RelationViewService $relationViewService,
    ) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->relationViewService = $relationViewService;
    }
    
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('relationship_nodes_search.relation_view_service'),
        );
    }

    protected function defineOptions() {
        $options = parent::defineOptions();
   

        $real_field = $this->definition['real field']; // With space - as such implemented in search api.
        if(empty($real_field)){
            return $options;
        }

        $index = $this->getIndex(); 
        if(!$index instanceof Index){
            return $options;
        }

        $relation_fields = $this->relationViewService->getCalculatedFields($index, $real_field);
        if(empty($relation_fields)){
            return $options;
        }

        

        foreach($relation_fields as $field){
            $options['value']['default'] = [$field => ''];
        }
        
        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function buildOptionsForm(&$form, FormStateInterface $form_state) {

        parent::buildOptionsForm($form, $form_state);
    /*    
        $form['related_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Related entity ID'),
        '#default_value' => $this->options['related_id'] ?? '',
        ];
        
        $form['relation_type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Relation type ID'),
        '#default_value' => $this->options['relation_type'] ?? '',
        ];
      */  
        // Basis filter opties toevoegen
        $this->showExposeButton($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    protected function valueForm(&$form, FormStateInterface $form_state) {
        parent::valueForm($form, $form_state);
       /* $form['value'] = [
        '#type' => 'container',
        '#tree' => TRUE,
        ];
        
        $form['value']['related_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Related entity'),
        '#default_value' => $this->value['related_id'] ?? '',
        ];
        
        $form['value']['relation_type'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Relation type'),
        '#default_value' => $this->value['relation_type'] ?? '',
        ];*/
    }

    /**
     * {@inheritdoc}
     */
    public function query() {
        if (!isset($this->query) || !method_exists($this->query, 'addCondition')) {
        return;
        }
        
        $related_id = $this->options['related_id'] ?? '';
        $relation_type = $this->options['relation_type'] ?? '';
        
        // Voor exposed input
        if ($this->options['exposed'] && !empty($this->value)) {
        $related_id = $this->value['related_id'] ?? '';
        $relation_type = $this->value['relation_type'] ?? '';
        }
        
        if (empty($related_id) && empty($relation_type)) {
        return;
        }
        
        $field = $this->realField ?? $this->field ?? 'relationship_info__relationnode__person_person__nested';
        
        if (!empty($related_id)) {
        $this->query->addCondition($field . '.calculated_related_id', $related_id, '=');
        }
        
        if (!empty($relation_type)) {
        $this->query->addCondition($field . '.calculated_relation_type_id', $relation_type, '=');
        }
    }
    
    
    
}