commit fa23de7604226b6dd0bc2e7ad5fdaefdae495cb1
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Tue Feb 3 17:10:47 2026 +0100

    fix: debug views - relationship nodes integration

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 6b1b824..77456dc 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -117,8 +117,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     $this->fieldConfigurator->savePluginOptions(
       $form_state,
       $this->getDefaultRelationFieldOptions(),
-      $this->options,
-      'relation_display_settings'
+      $this->options
     );
   }  
 

commit 2e8cad908134f99b02eaf41e58bdfdc1cf8b1a47
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Fri Dec 12 23:32:59 2025 +0100

    chore: implement displaying relationships on lemma pages

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index b78e3b9..6b1b824 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -163,8 +163,8 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     
     return [
       '#theme' => $theme_hook,
-      '#relationships' => $template_data['relationships'],
-      '#grouped' => $template_data['grouped'],
+      '#items' => $template_data['items'],
+      '#groups' => $template_data['groups'],
       '#summary' => $template_data['summary'],
       '#fields' => $template_data['fields'],
       '#row' => $values,
@@ -239,8 +239,8 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     $child_field_metadata = $this->buildFieldsMetadata($field_settings);
     
     return [
-      'relationships' => $relationships,
-      'grouped' => $grouped,
+      'items' => $relationships,
+      'groups' => $grouped,
       'summary' => $this->buildSummary($relationships, $child_field_metadata, $grouped),
       'fields' => $child_field_metadata,
     ];

commit 81764c04d553cb43bce06ce5ad01cc7413b88deb
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Thu Nov 27 18:21:22 2025 +0100

    post refactor, bugs!!

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 231e5ee..b78e3b9 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -8,8 +8,8 @@ use Drupal\views\ResultRow;
 use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
 use Symfony\Component\DependencyInjection\ContainerInterface;
 use Drupal\search_api\Entity\Index;
-use Drupal\relationship_nodes_search\FieldHelper\ChildFieldEntityReferenceHelper;
-use Drupal\relationship_nodes\RelationEntityType\RelationField\CalculatedFieldHelper;
+use Drupal\relationship_nodes_search\Views\Parser\NestedFieldResultViewsParser;
+use Drupal\relationship_nodes\RelationField\CalculatedFieldHelper;
 use Drupal\relationship_nodes_search\Views\Config\NestedFieldViewsFieldConfigurator;
 
 
@@ -21,7 +21,7 @@ use Drupal\relationship_nodes_search\Views\Config\NestedFieldViewsFieldConfigura
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
     
   protected NestedFieldViewsFieldConfigurator $fieldConfigurator;
-  protected ChildFieldEntityReferenceHelper $childReferenceHelper;
+  protected NestedFieldResultViewsParser $resultParser;
   protected CalculatedFieldHelper $calculatedFieldHelper;
 
   /**
@@ -35,7 +35,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
    *    The plugin definition.
    * @param NestedFieldViewsFieldConfigurator $fieldConfigurator
    *    The field configurator service.
-   * @param ChildFieldEntityReferenceHelper $childReferenceHelper
+   * @param NestedFieldResultViewsParser $resultParser
    *    The child reference helper service.
    * @param CalculatedFieldHelper $calculatedFieldHelper
    *    The calculated field helper service.
@@ -45,12 +45,12 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     string $plugin_id,
     mixed $plugin_definition,
     NestedFieldViewsFieldConfigurator $fieldConfigurator,
-    ChildFieldEntityReferenceHelper $childReferenceHelper,
+    NestedFieldResultViewsParser $resultParser,
     CalculatedFieldHelper $calculatedFieldHelper
   ) {
     parent::__construct($configuration, $plugin_id, $plugin_definition);
     $this->fieldConfigurator = $fieldConfigurator;
-    $this->childReferenceHelper = $childReferenceHelper;
+    $this->resultParser = $resultParser;
     $this->calculatedFieldHelper = $calculatedFieldHelper;
   }
   
@@ -64,7 +64,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
       $plugin_id,
       $plugin_definition,
       $container->get('relationship_nodes_search.nested_field_views_field_configurator'),
-      $container->get('relationship_nodes_search.child_field_entity_reference_helper'),
+      $container->get('relationship_nodes_search.nested_field_result_views_parser'),
       $container->get('relationship_nodes.calculated_field_helper')
     );
   }
@@ -271,7 +271,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     }
 
     // Step 1: Batch load all needed entities via helper service
-    $preloaded_entities = $this->childReferenceHelper->batchLoadEntities(
+    $preloaded_entities = $this->resultParser->batchLoadFromIndexedData(
       $nested_data, 
       $field_settings, 
       $index, 
@@ -289,7 +289,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         }
 
         // Use helper's cache-aware processing
-        $field_value = $this->childReferenceHelper->batchProcessFieldValues(
+        $field_value = $this->resultParser->processFieldValuesWithCache(
           $item[$child_fld_nm], 
           $settings, 
           $preloaded_entities

commit 823a126b303f75e3d17cc142bb21a1639527af89
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Wed Nov 26 18:25:31 2025 +0100

    chore: field display formatter Config form implemented -- todo: data display builder!

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index bb58ace..231e5ee 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -8,9 +8,8 @@ use Drupal\views\ResultRow;
 use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
 use Symfony\Component\DependencyInjection\ContainerInterface;
 use Drupal\search_api\Entity\Index;
-use Drupal\relationship_nodes_search\FieldHelper\NestedFieldHelper;
 use Drupal\relationship_nodes_search\FieldHelper\ChildFieldEntityReferenceHelper;
-use Drupal\relationship_nodes_search\FieldHelper\CalculatedFieldHelper;
+use Drupal\relationship_nodes\RelationEntityType\RelationField\CalculatedFieldHelper;
 use Drupal\relationship_nodes_search\Views\Config\NestedFieldViewsFieldConfigurator;
 
 
@@ -21,7 +20,6 @@ use Drupal\relationship_nodes_search\Views\Config\NestedFieldViewsFieldConfigura
  */
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
     
-  protected NestedFieldHelper $nestedFieldHelper;
   protected NestedFieldViewsFieldConfigurator $fieldConfigurator;
   protected ChildFieldEntityReferenceHelper $childReferenceHelper;
   protected CalculatedFieldHelper $calculatedFieldHelper;
@@ -35,8 +33,6 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
    *    The plugin ID.
    * @param mixed $plugin_definition
    *    The plugin definition.
-   * @param NestedFieldHelper $nestedFieldHelper
-   *    The nested field helper service.
    * @param NestedFieldViewsFieldConfigurator $fieldConfigurator
    *    The field configurator service.
    * @param ChildFieldEntityReferenceHelper $childReferenceHelper
@@ -48,13 +44,11 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     array $configuration,
     string $plugin_id,
     mixed $plugin_definition,
-    NestedFieldHelper $nestedFieldHelper,
     NestedFieldViewsFieldConfigurator $fieldConfigurator,
     ChildFieldEntityReferenceHelper $childReferenceHelper,
     CalculatedFieldHelper $calculatedFieldHelper
   ) {
     parent::__construct($configuration, $plugin_id, $plugin_definition);
-    $this->nestedFieldHelper = $nestedFieldHelper;
     $this->fieldConfigurator = $fieldConfigurator;
     $this->childReferenceHelper = $childReferenceHelper;
     $this->calculatedFieldHelper = $calculatedFieldHelper;
@@ -69,10 +63,9 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
       $configuration,
       $plugin_id,
       $plugin_definition,
-      $container->get('relationship_nodes_search.nested_field_helper'),
       $container->get('relationship_nodes_search.nested_field_views_field_configurator'),
       $container->get('relationship_nodes_search.child_field_entity_reference_helper'),
-      $container->get('relationship_nodes_search.calculated_field_helper')
+      $container->get('relationship_nodes.calculated_field_helper')
     );
   }
 
@@ -100,11 +93,11 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
       $this->definition,
       $form
     );
+
     if (!$config) {
       return;
     }
 
-    // Use new high-level method to build the form
     $this->fieldConfigurator->buildFieldDisplayForm(
       $form,
       $config['index'],
@@ -135,7 +128,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
    */
   public function getValue(ResultRow $values, $field = NULL) {
     $index = $this->getIndex();
-    $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);      
+    $sapi_fld_nm = $this->fieldConfigurator->getPluginParentFieldName($this->definition);      
     if (!$index instanceof Index || empty($sapi_fld_nm)) {
       return parent::getValue($values, $field);
     }
@@ -233,7 +226,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
    */
   protected function prepareTemplateData(array $nested_data): array {
     $index = $this->getIndex();
-    $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);
+    $sapi_fld_nm = $this->fieldConfigurator->getPluginParentFieldName($this->definition);
     
     if (!$index instanceof Index || empty($sapi_fld_nm)) {
       return $this->getEmptyTemplateData();

commit 39c5871be06a7726782ec65b66380bff0473402f
Author: hblomme <hans.blomme@ugent.be>
Date:   Tue Nov 25 22:35:09 2025 +0100

    refactor display of nested views (still buggy, though)

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index c44d973..bb58ace 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -95,8 +95,6 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
   public function buildOptionsForm(&$form, FormStateInterface $form_state) {
     parent::buildOptionsForm($form, $form_state);
     
-    $index = $this->getIndex();
-    $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition); 
     $config = $this->fieldConfigurator->validateAndPreparePluginForm(
       $this->getIndex(),
       $this->definition,
@@ -104,60 +102,20 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     );
     if (!$config) {
       return;
-    } 
-
-    $available_fields = $config['available_fields'];
-
-    $form['relation_display_settings'] = [
-      '#type' => 'details',
-      '#title' => $this->t('Relation display settings'),
-      '#open' => TRUE,
-    ];
-
-    $form['relation_display_settings']['field_settings'] = [
-      '#type' => 'fieldset',
-      '#title' => $this->t('Field configuration'),
-      '#description' => $this->t('Select fields to display and configure their appearance.'),
-      '#tree' => TRUE
-    ];
-
-    $field_settings = $this->options['field_settings'] ?? [];
-
-    foreach ($available_fields as $child_fld_nm) {
-      $this->fieldConfigurator->buildFieldConfigForm(
-        $form,
-        $config['index'],
-        $config['field_name'],
-        $child_fld_nm,
-        $field_settings
-      );
     }
 
-    $form['relation_display_settings']['sort_by_field'] = [
-      '#type' => 'select',
-      '#title' => $this->t('Sort by field'),
-      '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
-      '#default_value' => $this->options['sort_by_field'],
-      '#description' => $this->t('Sort relationships by this field value.'),
-    ];
-
-    $form['relation_display_settings']['group_by_field'] = [
-      '#type' => 'select',
-      '#title' => $this->t('Group by field'),
-      '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
-      '#default_value' => $this->options['group_by_field'],
-      '#description' => $this->t('Group relationships by this field value.'),
-    ];
-
-    $form['relation_display_settings']['template'] = [
-      '#type' => 'textfield',
-      '#title' => $this->t('Template name'),
-      '#default_value' => $this->options['template'],
-      '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
-    ];
+    // Use new high-level method to build the form
+    $this->fieldConfigurator->buildFieldDisplayForm(
+      $form,
+      $config['index'],
+      $config['field_name'],
+      $config['available_fields'],
+      $this->options
+    );
   }
 
 
+
   /**
    * {@inheritdoc}
    */
@@ -290,7 +248,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     return [
       'relationships' => $relationships,
       'grouped' => $grouped,
-      'summary' => $this->buildSummary($relationships, $fields, $grouped),
+      'summary' => $this->buildSummary($relationships, $child_field_metadata, $grouped),
       'fields' => $child_field_metadata,
     ];
   }

commit 8bb144f72abd8b2a4066fac0a769e479d0ed068c
Author: hblomme <hans.blomme@ugent.be>
Date:   Tue Nov 25 16:27:32 2025 +0100

    fix: restore removed custom admin toolbar config

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 6701644..c44d973 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -285,13 +285,13 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_fld_nm);
     $relationships = $this->sortRelationships($relationships);
     $grouped = $this->groupRelationships($relationships);
-    $fields = $this->buildFieldsMetadata($field_settings);
+    $child_field_metadata = $this->buildFieldsMetadata($field_settings);
     
     return [
       'relationships' => $relationships,
       'grouped' => $grouped,
       'summary' => $this->buildSummary($relationships, $fields, $grouped),
-      'fields' => $fields,
+      'fields' => $child_field_metadata,
     ];
   }
 

commit c15ab338fb1f97b07a090d49b6c0fa2f0557ed50
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Fri Nov 21 16:27:34 2025 +0100

    chore: style php files of custom module relationship_nodes_search to drupals coding standards

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index a174a0c..6701644 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -21,432 +21,493 @@ use Drupal\relationship_nodes_search\Views\Config\NestedFieldViewsFieldConfigura
  */
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
     
-    protected NestedFieldHelper $nestedFieldHelper;
-    protected NestedFieldViewsFieldConfigurator $fieldConfigurator;
-    protected ChildFieldEntityReferenceHelper $childReferenceHelper;
-    protected CalculatedFieldHelper $calculatedFieldHelper;
-
-    /**
-     * Constructs a RelationshipField object.
-     *
-     * @param array $configuration
-     *   The plugin configuration.
-     * @param string $plugin_id
-     *   The plugin ID.
-     * @param mixed $plugin_definition
-     *   The plugin definition.
-     * @param NestedFieldHelper $nestedFieldHelper
-     *   The nested field helper service.
-     * @param NestedFieldViewsFieldConfigurator $fieldConfigurator
-     *   The field configurator service.
-     * @param ChildFieldEntityReferenceHelper $childReferenceHelper
-     *   The child reference helper service.
-     * @param CalculatedFieldHelper $calculatedFieldHelper
-     *   The calculated field helper service.
-     */
-    public function __construct(
-        array $configuration,
-        string $plugin_id,
-        mixed $plugin_definition,
-        NestedFieldHelper $nestedFieldHelper,
-        NestedFieldViewsFieldConfigurator $fieldConfigurator,
-        ChildFieldEntityReferenceHelper $childReferenceHelper,
-        CalculatedFieldHelper $calculatedFieldHelper,
-    ) {
-        parent::__construct($configuration, $plugin_id, $plugin_definition);
-        $this->nestedFieldHelper = $nestedFieldHelper;
-        $this->fieldConfigurator = $fieldConfigurator;
-        $this->childReferenceHelper = $childReferenceHelper;
-        $this->calculatedFieldHelper = $calculatedFieldHelper;
+  protected NestedFieldHelper $nestedFieldHelper;
+  protected NestedFieldViewsFieldConfigurator $fieldConfigurator;
+  protected ChildFieldEntityReferenceHelper $childReferenceHelper;
+  protected CalculatedFieldHelper $calculatedFieldHelper;
+
+  /**
+   * Constructs a RelationshipField object.
+   *
+   * @param array $configuration
+   *    The plugin configuration.
+   * @param string $plugin_id
+   *    The plugin ID.
+   * @param mixed $plugin_definition
+   *    The plugin definition.
+   * @param NestedFieldHelper $nestedFieldHelper
+   *    The nested field helper service.
+   * @param NestedFieldViewsFieldConfigurator $fieldConfigurator
+   *    The field configurator service.
+   * @param ChildFieldEntityReferenceHelper $childReferenceHelper
+   *    The child reference helper service.
+   * @param CalculatedFieldHelper $calculatedFieldHelper
+   *    The calculated field helper service.
+   */
+  public function __construct(
+    array $configuration,
+    string $plugin_id,
+    mixed $plugin_definition,
+    NestedFieldHelper $nestedFieldHelper,
+    NestedFieldViewsFieldConfigurator $fieldConfigurator,
+    ChildFieldEntityReferenceHelper $childReferenceHelper,
+    CalculatedFieldHelper $calculatedFieldHelper
+  ) {
+    parent::__construct($configuration, $plugin_id, $plugin_definition);
+    $this->nestedFieldHelper = $nestedFieldHelper;
+    $this->fieldConfigurator = $fieldConfigurator;
+    $this->childReferenceHelper = $childReferenceHelper;
+    $this->calculatedFieldHelper = $calculatedFieldHelper;
+  }
+  
+
+  /**
+   * {@inheritdoc}
+   */
+  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
+    return new static(
+      $configuration,
+      $plugin_id,
+      $plugin_definition,
+      $container->get('relationship_nodes_search.nested_field_helper'),
+      $container->get('relationship_nodes_search.nested_field_views_field_configurator'),
+      $container->get('relationship_nodes_search.child_field_entity_reference_helper'),
+      $container->get('relationship_nodes_search.calculated_field_helper')
+    );
+  }
+
+
+  /**
+   * {@inheritdoc}
+   */
+  public function defineOptions() {
+    $options = parent::defineOptions();
+    foreach($this->getDefaultRelationFieldOptions() as $option => $default){
+      $options[$option] = ['default' => $default];
     }
-    
-
-    /**
-     * {@inheritdoc}
-     */
-    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
-        return new static(
-            $configuration,
-            $plugin_id,
-            $plugin_definition,
-            $container->get('relationship_nodes_search.nested_field_helper'),
-            $container->get('relationship_nodes_search.nested_field_views_field_configurator'),
-            $container->get('relationship_nodes_search.child_field_entity_reference_helper'),
-            $container->get('relationship_nodes_search.calculated_field_helper'),
-        );
-    }
-
-
-    /**
-     * {@inheritdoc}
-     */
-    public function defineOptions() {
-        $options = parent::defineOptions();
-        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
-            $options[$option] = ['default' => $default];
-        }
-        return $options;
-    }
-
-
-    /**
-     * {@inheritdoc}
-     */
-    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
-        parent::buildOptionsForm($form, $form_state);
-        
-        $index = $this->getIndex();
-        $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition); 
-        $config = $this->fieldConfigurator->validateAndPreparePluginForm(
-            $this->getIndex(),
-            $this->definition,
-            $form
-        );
-        if (!$config) {
-            return;
-        } 
-
-        $available_fields = $config['available_fields'];
-
-        $form['relation_display_settings'] = [
-            '#type' => 'details',
-            '#title' => $this->t('Relation display settings'),
-            '#open' => TRUE,
-        ];
-
-        $form['relation_display_settings']['field_settings'] = [
-            '#type' => 'fieldset',
-            '#title' => $this->t('Field configuration'),
-            '#description' => $this->t('Select fields to display and configure their appearance.'),
-            '#tree' => TRUE
-        ];
-
-        $field_settings = $this->options['field_settings'] ?? [];
-
-        foreach ($available_fields as $child_fld_nm) {
-           $this->fieldConfigurator->buildFieldConfigForm(
-                $form,
-                $config['index'],
-                $config['field_name'],
-                $child_fld_nm,
-                $field_settings
-            );
-        }
-
-        $form['relation_display_settings']['sort_by_field'] = [
-            '#type' => 'select',
-            '#title' => $this->t('Sort by field'),
-            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
-            '#default_value' => $this->options['sort_by_field'],
-            '#description' => $this->t('Sort relationships by this field value.'),
-        ];
-
-        $form['relation_display_settings']['group_by_field'] = [
-            '#type' => 'select',
-            '#title' => $this->t('Group by field'),
-            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
-            '#default_value' => $this->options['group_by_field'],
-            '#description' => $this->t('Group relationships by this field value.'),
-        ];
-
-        $form['relation_display_settings']['template'] = [
-            '#type' => 'textfield',
-            '#title' => $this->t('Template name'),
-            '#default_value' => $this->options['template'],
-            '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
-        ];
-    }
-
-
-    /**
-     * {@inheritdoc}
-     */
-    public function submitOptionsForm(&$form, FormStateInterface $form_state) {
-        parent::submitOptionsForm($form, $form_state);
-        $this->fieldConfigurator->savePluginOptions(
-            $form_state,
-            $this->getDefaultRelationFieldOptions(),
-            $this->options,
-            'relation_display_settings'
-        );
-    }  
+    return $options;
+  }
 
 
-    /**
-     * {@inheritdoc}
-     */
-    public function getValue(ResultRow $values, $field = NULL) {
-        $index = $this->getIndex();
-        $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);      
-        if (!$index instanceof Index || empty($sapi_fld_nm)) {
-            return parent::getValue($values, $field);
-        }
-
-        $values_arr = get_object_vars($values);
-        if(empty($values_arr) || !is_array($values_arr)){
-            return parent::getValue($values, $field);
-        }
-
-        if(empty($values_arr[$sapi_fld_nm])){
-            return parent::getValue($values, $field);
-        }
-        $value = $values_arr[$sapi_fld_nm];
-        if(!is_array($value)){
-            return parent::getValue($values, $field);
-        }
-        return $value;
+  /**
+   * {@inheritdoc}
+   */
+  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
+    parent::buildOptionsForm($form, $form_state);
+    
+    $index = $this->getIndex();
+    $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition); 
+    $config = $this->fieldConfigurator->validateAndPreparePluginForm(
+      $this->getIndex(),
+      $this->definition,
+      $form
+    );
+    if (!$config) {
+      return;
+    } 
+
+    $available_fields = $config['available_fields'];
+
+    $form['relation_display_settings'] = [
+      '#type' => 'details',
+      '#title' => $this->t('Relation display settings'),
+      '#open' => TRUE,
+    ];
+
+    $form['relation_display_settings']['field_settings'] = [
+      '#type' => 'fieldset',
+      '#title' => $this->t('Field configuration'),
+      '#description' => $this->t('Select fields to display and configure their appearance.'),
+      '#tree' => TRUE
+    ];
+
+    $field_settings = $this->options['field_settings'] ?? [];
+
+    foreach ($available_fields as $child_fld_nm) {
+      $this->fieldConfigurator->buildFieldConfigForm(
+        $form,
+        $config['index'],
+        $config['field_name'],
+        $child_fld_nm,
+        $field_settings
+      );
     }
 
-
-    /**
-     * {@inheritdoc}
-     */
-    public function render(ResultRow $values) {
-        $nested_data = $this->getValue($values);
-        if (empty($nested_data) || !is_array($nested_data)) {
-            return '';
-        }
-
-        $template_data = $this->prepareTemplateData($nested_data);
-        $theme_hook = str_replace('-', '_', $this->options['template']);
-        
-        return [
-            '#theme' => $theme_hook,
-            '#relationships' => $template_data['relationships'],
-            '#grouped' => $template_data['grouped'],
-            '#summary' => $template_data['summary'],
-            '#fields' => $template_data['fields'],
-            '#row' => $values,
-            '#cache' => [
-                'contexts' => ['languages:language_content'],
-                'tags' => [
-                    'node_list',
-                    'relationship_nodes_search:relationships',
-                ],
-            ],
-        ];
+    $form['relation_display_settings']['sort_by_field'] = [
+      '#type' => 'select',
+      '#title' => $this->t('Sort by field'),
+      '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
+      '#default_value' => $this->options['sort_by_field'],
+      '#description' => $this->t('Sort relationships by this field value.'),
+    ];
+
+    $form['relation_display_settings']['group_by_field'] = [
+      '#type' => 'select',
+      '#title' => $this->t('Group by field'),
+      '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
+      '#default_value' => $this->options['group_by_field'],
+      '#description' => $this->t('Group relationships by this field value.'),
+    ];
+
+    $form['relation_display_settings']['template'] = [
+      '#type' => 'textfield',
+      '#title' => $this->t('Template name'),
+      '#default_value' => $this->options['template'],
+      '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
+    ];
+  }
+
+
+  /**
+   * {@inheritdoc}
+   */
+  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
+    parent::submitOptionsForm($form, $form_state);
+    $this->fieldConfigurator->savePluginOptions(
+      $form_state,
+      $this->getDefaultRelationFieldOptions(),
+      $this->options,
+      'relation_display_settings'
+    );
+  }  
+
+
+  /**
+   * {@inheritdoc}
+   */
+  public function getValue(ResultRow $values, $field = NULL) {
+    $index = $this->getIndex();
+    $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);      
+    if (!$index instanceof Index || empty($sapi_fld_nm)) {
+      return parent::getValue($values, $field);
     }
 
-
-    /**
-     * {@inheritdoc}
-     */
-    public function clickSortable() {
-        return FALSE;
+    $values_arr = get_object_vars($values);
+    if(empty($values_arr) || !is_array($values_arr)){
+      return parent::getValue($values, $field);
     }
 
-
-    /**
-     * {@inheritdoc}
-     */
-    public function renderItems($items) {
-        return [];
+    if(empty($values_arr[$sapi_fld_nm])){
+      return parent::getValue($values, $field);
     }
-
-
-    /**
-     * {@inheritdoc}
-     */
-    public function advancedRender(ResultRow $values) {
-        return $this->render($values);
+    $value = $values_arr[$sapi_fld_nm];
+    if(!is_array($value)){
+      return parent::getValue($values, $field);
     }
+    return $value;
+  }
 
 
-    /**
-     * {@inheritdoc}
-     */
-    public function render_item($count, $item) {
-        return '';
+  /**
+   * {@inheritdoc}
+   */
+  public function render(ResultRow $values) {
+    $nested_data = $this->getValue($values);
+    if (empty($nested_data) || !is_array($nested_data)) {
+      return '';
     }
 
-
-    /**
-     * Prepare the data to be send to the twig template
-     */
-    protected function prepareTemplateData(array $nested_data): array {
-        $index = $this->getIndex();
-        $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);
-        
-        if (!$index instanceof Index || empty($sapi_fld_nm)) {
-            return $this->getEmptyTemplateData();
-        }
-        $field_settings = $this->options['field_settings'] ?? [];
-        $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_fld_nm);
-        $relationships = $this->sortRelationships($relationships);
-        $grouped = $this->groupRelationships($relationships);
-        $fields = $this->buildFieldsMetadata($field_settings);
-        
-        return [
-            'relationships' => $relationships,
-            'grouped' => $grouped,
-            'summary' => $this->buildSummary($relationships, $fields, $grouped),
-            'fields' => $fields,
-        ];
+    $template_data = $this->prepareTemplateData($nested_data);
+    $theme_hook = str_replace('-', '_', $this->options['template']);
+    
+    return [
+      '#theme' => $theme_hook,
+      '#relationships' => $template_data['relationships'],
+      '#grouped' => $template_data['grouped'],
+      '#summary' => $template_data['summary'],
+      '#fields' => $template_data['fields'],
+      '#row' => $values,
+      '#cache' => [
+        'contexts' => ['languages:language_content'],
+        'tags' => [
+          'node_list',
+          'relationship_nodes_search:relationships',
+        ],
+      ],
+    ];
+  }
+
+
+  /**
+   * {@inheritdoc}
+   */
+  public function clickSortable() {
+    return FALSE;
+  }
+
+
+  /**
+   * {@inheritdoc}
+   */
+  public function renderItems($items) {
+    return [];
+  }
+
+
+  /**
+   * {@inheritdoc}
+   */
+  public function advancedRender(ResultRow $values) {
+    return $this->render($values);
+  }
+
+
+  /**
+   * {@inheritdoc}
+   */
+  public function render_item($count, $item) {
+    return '';
+  }
+
+
+  /**
+   * Prepares data to be sent to the twig template.
+   *
+   * @param array $nested_data
+   *   The nested relationship data from the search index.
+   *
+   * @return array
+   *   Template data array containing:
+   *   - relationships: processed relationship items
+   *   - grouped: relationships grouped by configured field
+   *   - summary: metadata about the relationships
+   *   - fields: field configuration and metadata
+   */
+  protected function prepareTemplateData(array $nested_data): array {
+    $index = $this->getIndex();
+    $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);
+    
+    if (!$index instanceof Index || empty($sapi_fld_nm)) {
+      return $this->getEmptyTemplateData();
     }
 
-
-    /**
-     * Build relationships array from nested data.
-     */
-    protected function buildRelationshipsArray(array $nested_data, array $field_settings, Index $index, string $sapi_fld_nm): array {
-        if (empty($nested_data)) {
-                return [];
-            }
-
-            // Step 1: Batch load all needed entities via helper service
-            $preloaded_entities = $this->childReferenceHelper->batchLoadEntities(
-                $nested_data, 
-                $field_settings, 
-                $index, 
-                $sapi_fld_nm
-            );
-            
-            // Step 2: Build relationships using cached entities
-            $relationships = [];
-            
-            foreach ($nested_data as $item) {
-                $item_with_values = [];       
-                foreach ($field_settings as $child_fld_nm => $settings) {
-                    if (empty($settings['enabled']) || !isset($item[$child_fld_nm])) {
-                        continue;
-                    }
-            
-                    // Use helper's cache-aware processing
-                    $field_value = $this->childReferenceHelper->batchProcessFieldValues(
-                        $item[$child_fld_nm], 
-                        $settings, 
-                        $preloaded_entities
-                    );
-                    
-                    if ($field_value !== null) {
-                        $item_with_values[$child_fld_nm] = $field_value;
-                    }
-                }       
-
-                if (!empty($item_with_values)) {
-                    $relationships[] = $item_with_values;
-                }
-            }      
-            return $relationships;
+    $field_settings = $this->options['field_settings'] ?? [];
+    $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_fld_nm);
+    $relationships = $this->sortRelationships($relationships);
+    $grouped = $this->groupRelationships($relationships);
+    $fields = $this->buildFieldsMetadata($field_settings);
+    
+    return [
+      'relationships' => $relationships,
+      'grouped' => $grouped,
+      'summary' => $this->buildSummary($relationships, $fields, $grouped),
+      'fields' => $fields,
+    ];
+  }
+
+
+  /**
+   * Builds relationships array from nested data.
+   *
+   * Batch loads all referenced entities for performance, then processes
+   * each relationship item's fields using the preloaded entity cache.
+   *
+   * @param array $nested_data
+   *   The raw nested relationship data from the index.
+   * @param array $field_settings
+   *   Field configuration from the Views field settings.
+   * @param Index $index
+   *   The Search API index.
+   * @param string $sapi_fld_nm
+   *   The Search API field name for the relationship.
+   *
+   * @return array
+   *   Array of processed relationship items with resolved field values.
+   */
+  protected function buildRelationshipsArray(array $nested_data, array $field_settings, Index $index, string $sapi_fld_nm): array {
+    if (empty($nested_data)) {
+      return [];
     }
 
-
-    /**
-     * Sort relationships based on configured sort field.
-     */
-    protected function sortRelationships(array $relationships): array {
-        if (empty($this->options['sort_by_field']) || empty($relationships)) {
-            return $relationships;
+    // Step 1: Batch load all needed entities via helper service
+    $preloaded_entities = $this->childReferenceHelper->batchLoadEntities(
+      $nested_data, 
+      $field_settings, 
+      $index, 
+      $sapi_fld_nm
+    );
+    
+    // Step 2: Build relationships using cached entities
+    $relationships = [];
+    
+    foreach ($nested_data as $item) {
+      $item_with_values = [];
+      foreach ($field_settings as $child_fld_nm => $settings) {
+        if (empty($settings['enabled']) || !isset($item[$child_fld_nm])) {
+          continue;
         }
-        
-        $sort_fld_nm = $this->options['sort_by_field'];
-        usort($relationships, function($a, $b) use ($sort_fld_nm) {
-            if (!isset($a[$sort_fld_nm]) || !isset($b[$sort_fld_nm])) {
-                return 0;
-            }
-            
-            $val_a = $a[$sort_fld_nm]['field_values'][0]['value'] ?? '';
-            $val_b = $b[$sort_fld_nm]['field_values'][0]['value'] ?? '';
-            
-            return strcasecmp($val_a, $val_b);
-        });  
-        return $relationships;
-    }
 
-
-    /**
-     * Group relationships based on configured group field.
-     */
-    protected function groupRelationships(array $relationships): array {
-        if (empty($this->options['group_by_field']) || empty($relationships)) {
-            return [];
-        }
-        
-        $grouped = [];
-        $sort_fld_nm = $this->options['group_by_field'];
-        
-        foreach ($relationships as $item) {
-            if (!isset($item[$sort_fld_nm])) {
-                continue;
-            }
-            
-            $group_key = $item[$sort_fld_nm]['field_values'][0]['value'] ?? 'ungrouped';
-            
-            if (!isset($grouped[$group_key])) {
-                $grouped[$group_key] = [];
-            }
-            $grouped[$group_key][] = $item;
-        }  
-        return $grouped;
-    }
-
-
-    /**
-     * Build fields metadata for template.
-     */
-    protected function buildFieldsMetadata(array $field_settings): array {
-        $fields = [];
+        // Use helper's cache-aware processing
+        $field_value = $this->childReferenceHelper->batchProcessFieldValues(
+          $item[$child_fld_nm], 
+          $settings, 
+          $preloaded_entities
+        );
         
-        foreach ($field_settings as $child_fld_nm => $settings) {
-            if (empty($settings['enabled'])) {
-                continue;
-            }
-            
-            $fields[$child_fld_nm] = [
-                'name' => $child_fld_nm,
-                'label' => !empty($settings['label']) 
-                    ? $settings['label'] 
-                    :  $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm),
-                'weight' => $settings['weight'] ?? 0,
-                'hide_label' => !empty($settings['hide_label']),
-                'display_mode' => $settings['display_mode'] ?? 'id',
-                'multiple_separator' => $settings['multiple_separator'] ?? ', '
-            ];
+        if ($field_value !== NULL) {
+          $item_with_values[$child_fld_nm] = $field_value;
         }
-        return $this->fieldConfigurator->sortFieldsByWeight($fields);
+      }       
+
+      if (!empty($item_with_values)) {
+        $relationships[] = $item_with_values;
+      }
+    }   
+
+    return $relationships;
+  }
+
+
+  /**
+   * Sorts relationships based on configured sort field.
+   *
+   * @param array $relationships
+   *   The relationships array to sort.
+   *
+   * @return array
+   *   The sorted relationships array.
+   */
+  protected function sortRelationships(array $relationships): array {
+    if (empty($this->options['sort_by_field']) || empty($relationships)) {
+      return $relationships;
     }
-
-
-    /**
-     * Build summary data for template.
-     */
-    protected function buildSummary(array $relationships, array $fields, array $grouped): array {
-        return [
-            'total' => count($relationships),
-            'fields' => array_keys($fields),
-            'has_groups' => !empty($grouped),
-            'group_count' => count($grouped),
-        ];
-    }
-
-
-    /**
-     * Get empty template data structure.
-     */
-    protected function getEmptyTemplateData(): array {
-        return [
-            'relationships' => [],
-            'grouped' => [],
-            'summary' => [
-                'total' => 0,
-                'fields' => [],
-                'has_groups' => false,
-                'group_count' => 0
-            ],
-            'fields' => [],
-        ];
+    
+    $sort_fld_nm = $this->options['sort_by_field'];
+    usort($relationships, function($a, $b) use ($sort_fld_nm) {
+      if (!isset($a[$sort_fld_nm]) || !isset($b[$sort_fld_nm])) {
+        return 0;
+      }
+      
+      $val_a = $a[$sort_fld_nm]['field_values'][0]['value'] ?? '';
+      $val_b = $b[$sort_fld_nm]['field_values'][0]['value'] ?? '';
+      
+      return strcasecmp($val_a, $val_b);
+    });  
+    return $relationships;
+  }
+
+
+  /**
+   * Groups relationships based on configured group field.
+   *
+   * @param array $relationships
+   *   The relationships array to group.
+   *
+   * @return array
+   *   Associative array with group keys and relationship arrays as values.
+   */
+  protected function groupRelationships(array $relationships): array {
+    if (empty($this->options['group_by_field']) || empty($relationships)) {
+      return [];
     }
-
-
-    /**
-     * Get an array of options for the field config form in views.
-     */
-    protected function getDefaultRelationFieldOptions(): array{
-        return [
-            'field_settings' => [],
-            'sort_by_field' => '',
-            'group_by_field' => '',
-            'template' => 'relationship-field',
-        ];
+    
+    $grouped = [];
+    $group_fld_nm  = $this->options['group_by_field'];
+    
+    foreach ($relationships as $item) {
+      if (!isset($item[$group_fld_nm ])) {
+        continue;
+      }
+      
+      $group_key = $item[$group_fld_nm ]['field_values'][0]['value'] ?? 'ungrouped';
+      
+      if (!isset($grouped[$group_key])) {
+        $grouped[$group_key] = [];
+      }
+      $grouped[$group_key][] = $item;
+    }  
+    return $grouped;
+  }
+
+
+  /**
+   * Builds fields metadata for the template.
+   *
+   * @param array $field_settings
+   *   Field configuration from Views settings.
+   *
+   * @return array
+   *   Array of field metadata sorted by weight.
+   */
+  protected function buildFieldsMetadata(array $field_settings): array {
+    $fields = [];
+    
+    foreach ($field_settings as $child_fld_nm => $settings) {
+      if (empty($settings['enabled'])) {
+        continue;
+      }
+      
+      $fields[$child_fld_nm] = [
+        'name' => $child_fld_nm,
+        'label' => !empty($settings['label']) 
+          ? $settings['label'] 
+          :  $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm),
+        'weight' => $settings['weight'] ?? 0,
+        'hide_label' => !empty($settings['hide_label']),
+        'display_mode' => $settings['display_mode'] ?? 'id',
+        'multiple_separator' => $settings['multiple_separator'] ?? ', '
+      ];
     }
+    return $this->fieldConfigurator->sortFieldsByWeight($fields);
+  }
+
+
+  /**
+   * Builds summary data for the template.
+   *
+   * @param array $relationships
+   *   The processed relationships array.
+   * @param array $fields
+   *   The fields metadata.
+   * @param array $grouped
+   *   The grouped relationships array.
+   *
+   * @return array
+   *   Summary array with total count, field list, and grouping info.
+   */
+  protected function buildSummary(array $relationships, array $fields, array $grouped): array {
+    return [
+      'total' => count($relationships),
+      'fields' => array_keys($fields),
+      'has_groups' => !empty($grouped),
+      'group_count' => count($grouped),
+    ];
+  }
+
+
+  /**
+   * Gets empty template data structure.
+   *
+   * @return array
+   *   Empty template data array with all required keys.
+   */
+  protected function getEmptyTemplateData(): array {
+    return [
+      'relationships' => [],
+      'grouped' => [],
+      'summary' => [
+        'total' => 0,
+        'fields' => [],
+        'has_groups' => FALSE,
+        'group_count' => 0
+      ],
+      'fields' => [],
+    ];
+  }
+
+
+  /**
+   * Gets default options for the field configuration form in Views.
+   *
+   * @return array
+   *   Array of default option values.
+   */
+  protected function getDefaultRelationFieldOptions(): array{
+    return [
+      'field_settings' => [],
+      'sort_by_field' => '',
+      'group_by_field' => '',
+      'template' => 'relationship-field',
+    ];
+  }
 }
\ No newline at end of file

commit 0cd08aa6eff01cef7e53bfff99c5b934c8638916
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Fri Nov 21 10:48:22 2025 +0100

    refactor: reorganize services of relationship_nodes_search custom module

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index a022d4d..a174a0c 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -8,10 +8,10 @@ use Drupal\views\ResultRow;
 use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
 use Symfony\Component\DependencyInjection\ContainerInterface;
 use Drupal\search_api\Entity\Index;
-use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;
-use Drupal\relationship_nodes_search\Service\Field\ChildFieldEntityReferenceHelper;
-use Drupal\relationship_nodes_search\Service\Field\CalculatedFieldHelper;
-use Drupal\relationship_nodes_search\Service\ConfigForm\NestedFieldViewsFieldConfigurator;
+use Drupal\relationship_nodes_search\FieldHelper\NestedFieldHelper;
+use Drupal\relationship_nodes_search\FieldHelper\ChildFieldEntityReferenceHelper;
+use Drupal\relationship_nodes_search\FieldHelper\CalculatedFieldHelper;
+use Drupal\relationship_nodes_search\Views\Config\NestedFieldViewsFieldConfigurator;
 
 
 /**

commit 9bd71d81da4948edddf28840736b9781373c5b13
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Thu Nov 20 18:48:48 2025 +0100

    doc: add documentation to custom services of the relationship_nodes_search module

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index c52a678..a022d4d 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -15,6 +15,8 @@ use Drupal\relationship_nodes_search\Service\ConfigForm\NestedFieldViewsFieldCon
 
 
 /**
+ * Views field plugin for displaying nested relationship data.
+ *
  * @ViewsField("search_api_relationship_field")
  */
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
@@ -24,7 +26,24 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     protected ChildFieldEntityReferenceHelper $childReferenceHelper;
     protected CalculatedFieldHelper $calculatedFieldHelper;
 
-
+    /**
+     * Constructs a RelationshipField object.
+     *
+     * @param array $configuration
+     *   The plugin configuration.
+     * @param string $plugin_id
+     *   The plugin ID.
+     * @param mixed $plugin_definition
+     *   The plugin definition.
+     * @param NestedFieldHelper $nestedFieldHelper
+     *   The nested field helper service.
+     * @param NestedFieldViewsFieldConfigurator $fieldConfigurator
+     *   The field configurator service.
+     * @param ChildFieldEntityReferenceHelper $childReferenceHelper
+     *   The child reference helper service.
+     * @param CalculatedFieldHelper $calculatedFieldHelper
+     *   The calculated field helper service.
+     */
     public function __construct(
         array $configuration,
         string $plugin_id,
@@ -41,7 +60,10 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         $this->calculatedFieldHelper = $calculatedFieldHelper;
     }
     
-    
+
+    /**
+     * {@inheritdoc}
+     */
     public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
         return new static(
             $configuration,
@@ -56,7 +78,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
 
     /**
-     * Inherit docs.
+     * {@inheritdoc}
      */
     public function defineOptions() {
         $options = parent::defineOptions();
@@ -68,7 +90,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
 
     /**
-     * Inherit docs.
+     * {@inheritdoc}
      */
     public function buildOptionsForm(&$form, FormStateInterface $form_state) {
         parent::buildOptionsForm($form, $form_state);
@@ -137,7 +159,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
 
     /**
-     * Inherit docs.
+     * {@inheritdoc}
      */
     public function submitOptionsForm(&$form, FormStateInterface $form_state) {
         parent::submitOptionsForm($form, $form_state);
@@ -151,7 +173,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
 
     /**
-     * Inherit docs.
+     * {@inheritdoc}
      */
     public function getValue(ResultRow $values, $field = NULL) {
         $index = $this->getIndex();
@@ -177,7 +199,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
 
     /**
-     * Override render and sort methods to prevent the default views rendering: force to use custom rendering.
+     * {@inheritdoc}
      */
     public function render(ResultRow $values) {
         $nested_data = $this->getValue($values);
@@ -206,21 +228,33 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     }
 
 
+    /**
+     * {@inheritdoc}
+     */
     public function clickSortable() {
         return FALSE;
     }
 
 
+    /**
+     * {@inheritdoc}
+     */
     public function renderItems($items) {
         return [];
     }
 
 
+    /**
+     * {@inheritdoc}
+     */
     public function advancedRender(ResultRow $values) {
         return $this->render($values);
     }
 
-    
+
+    /**
+     * {@inheritdoc}
+     */
     public function render_item($count, $item) {
         return '';
     }

commit 766e82a118cd6ccc3a91680dfbe34ca682fb3e70
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Wed Nov 19 20:59:25 2025 +0100

    refactor phase 1 relationship nodes search finished: more testing required

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 537efa6..c52a678 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -8,9 +8,10 @@ use Drupal\views\ResultRow;
 use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
 use Symfony\Component\DependencyInjection\ContainerInterface;
 use Drupal\search_api\Entity\Index;
-use Drupal\relationship_nodes_search\Service\NestedFieldHelper;
-use Drupal\relationship_nodes_search\Service\ChildFieldEntityReferenceHelper;
-use Drupal\relationship_nodes_search\Service\CalculatedFieldHelper;
+use Drupal\relationship_nodes_search\Service\Field\NestedFieldHelper;
+use Drupal\relationship_nodes_search\Service\Field\ChildFieldEntityReferenceHelper;
+use Drupal\relationship_nodes_search\Service\Field\CalculatedFieldHelper;
+use Drupal\relationship_nodes_search\Service\ConfigForm\NestedFieldViewsFieldConfigurator;
 
 
 /**
@@ -19,6 +20,7 @@ use Drupal\relationship_nodes_search\Service\CalculatedFieldHelper;
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
     
     protected NestedFieldHelper $nestedFieldHelper;
+    protected NestedFieldViewsFieldConfigurator $fieldConfigurator;
     protected ChildFieldEntityReferenceHelper $childReferenceHelper;
     protected CalculatedFieldHelper $calculatedFieldHelper;
 
@@ -28,11 +30,13 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         string $plugin_id,
         mixed $plugin_definition,
         NestedFieldHelper $nestedFieldHelper,
+        NestedFieldViewsFieldConfigurator $fieldConfigurator,
         ChildFieldEntityReferenceHelper $childReferenceHelper,
         CalculatedFieldHelper $calculatedFieldHelper,
     ) {
         parent::__construct($configuration, $plugin_id, $plugin_definition);
         $this->nestedFieldHelper = $nestedFieldHelper;
+        $this->fieldConfigurator = $fieldConfigurator;
         $this->childReferenceHelper = $childReferenceHelper;
         $this->calculatedFieldHelper = $calculatedFieldHelper;
     }
@@ -44,6 +48,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             $plugin_id,
             $plugin_definition,
             $container->get('relationship_nodes_search.nested_field_helper'),
+            $container->get('relationship_nodes_search.nested_field_views_field_configurator'),
             $container->get('relationship_nodes_search.child_field_entity_reference_helper'),
             $container->get('relationship_nodes_search.calculated_field_helper'),
         );
@@ -68,24 +73,18 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     public function buildOptionsForm(&$form, FormStateInterface $form_state) {
         parent::buildOptionsForm($form, $form_state);
         
-        $sapi_fld_nm = $this->getSearchApiField();
         $index = $this->getIndex();
-        
-        if (!$index instanceof Index || empty($sapi_fld_nm)) {
-            $form['error'] = [
-                '#markup' => $this->t('Cannot load index or field configuration.'),
-            ];
+        $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition); 
+        $config = $this->fieldConfigurator->validateAndPreparePluginForm(
+            $this->getIndex(),
+            $this->definition,
+            $form
+        );
+        if (!$config) {
             return;
-        }
+        } 
 
-        $available_fields = $this->nestedFieldHelper->getProcessedNestedChildFieldNames($index, $sapi_fld_nm);
-        
-        if (empty($available_fields)) {
-            $form['info'] = [
-                '#markup' => $this->t('No nested fields available. Please configure nested fields in the Search API index.'),
-            ];
-            return;
-        }
+        $available_fields = $config['available_fields'];
 
         $form['relation_display_settings'] = [
             '#type' => 'details',
@@ -103,74 +102,13 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         $field_settings = $this->options['field_settings'] ?? [];
 
         foreach ($available_fields as $child_fld_nm) {
-            $is_enabled = !empty($field_settings[$child_fld_nm]['enabled']);
-            $disabled_state = ['disabled' => [':input[name="options[relation_display_settings][field_settings][' . $child_fld_nm . '][enabled]"]' => ['checked' => FALSE]]];
-
-            $link_option =  $this->childReferenceHelper->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm);
-            
-            $form['relation_display_settings']['field_settings'][$child_fld_nm] = [
-                '#type' => 'details',
-                '#title' => $child_fld_nm,
-                '#open' => $is_enabled,
-            ];
-
-            $form['relation_display_settings']['field_settings'][$child_fld_nm]['enabled'] = [
-                '#type' => 'checkbox',
-                '#title' => $this->t('Display this field'),
-                '#default_value' => $is_enabled,
-            ];
-
-            if($link_option){
-                $display_mode = $field_settings[$child_fld_nm]['display_mode'] ?? 'raw';
-                $form['relation_display_settings']['field_settings'][$child_fld_nm]['display_mode'] = [
-                    '#type' => 'radios',
-                    '#title' => $this->t('Display mode'),
-                    '#options' => [
-                        'raw' => $this->t('The raw value (id)'),
-                        'label' => $this->t('Label'),
-                        'link' => $this->t('Label as link'),
-                    ],
-                    '#default_value' => $display_mode,
-                    '#description' => $this->t('How to display this field value.'),
-                    '#states' => $disabled_state,
-                ];
-            }
-
-            $form['relation_display_settings']['field_settings'][$child_fld_nm]['label'] = [
-                '#type' => 'textfield',
-                '#title' => $this->t('Custom label'),
-                '#default_value' => $field_settings[$child_fld_nm]['label'] ?? $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm),
-                '#description' => $this->t('Custom label for this field.'),
-                '#size' => 30,
-                '#states' => $disabled_state
-            ];
-
-            $form['relation_display_settings']['field_settings'][$child_fld_nm]['weight'] = [
-                '#type' => 'number',
-                '#title' => $this->t('Weight'),
-                '#default_value' => $field_settings[$child_fld_nm]['weight'] ?? 0,
-                '#description' => $this->t('Fields with lower weights appear first.'),
-                '#size' => 5,
-                '#states' => $disabled_state
-            ];
-
-            $form['relation_display_settings']['field_settings'][$child_fld_nm]['hide_label'] = [
-                '#type' => 'checkbox',
-                '#title' => $this->t('Hide label in output'),
-                '#default_value' => $field_settings[$child_fld_nm]['hide_label'] ?? FALSE,
-                '#states' => $disabled_state
-            ];
-
-            if(!$is_predefined){
-                $form['relation_display_settings']['field_settings'][$child_fld_nm]['multiple_separator'] = [
-                    '#type' => 'textfield',
-                    '#title' => $this->t('Multiple Values Separator'),
-                    '#default_value' => $field_settings[$child_fld_nm]['multiple_separator'] ?? ', ',
-                    '#description' => $this->t('Configure how to separate multiple values (only applies if this field has multiple values).'),
-                    '#size' => 10,
-                    '#states' => $disabled_state
-                ];
-            }
+           $this->fieldConfigurator->buildFieldConfigForm(
+                $form,
+                $config['index'],
+                $config['field_name'],
+                $child_fld_nm,
+                $field_settings
+            );
         }
 
         $form['relation_display_settings']['sort_by_field'] = [
@@ -203,12 +141,12 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
      */
     public function submitOptionsForm(&$form, FormStateInterface $form_state) {
         parent::submitOptionsForm($form, $form_state);
-        $relation_options = $form_state->getValue(['options', 'relation_display_settings']);
-        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
-            if (isset($relation_options[$option])) {
-                $this->options[$option] = $relation_options[$option];
-            }
-        }
+        $this->fieldConfigurator->savePluginOptions(
+            $form_state,
+            $this->getDefaultRelationFieldOptions(),
+            $this->options,
+            'relation_display_settings'
+        );
     }  
 
 
@@ -217,7 +155,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
      */
     public function getValue(ResultRow $values, $field = NULL) {
         $index = $this->getIndex();
-        $sapi_fld_nm = $this->getSearchApiField();      
+        $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);      
         if (!$index instanceof Index || empty($sapi_fld_nm)) {
             return parent::getValue($values, $field);
         }
@@ -259,18 +197,25 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             '#row' => $values,
             '#cache' => [
                 'contexts' => ['languages:language_content'],
+                'tags' => [
+                    'node_list',
+                    'relationship_nodes_search:relationships',
+                ],
             ],
         ];
     }
 
+
     public function clickSortable() {
         return FALSE;
     }
 
+
     public function renderItems($items) {
         return [];
     }
 
+
     public function advancedRender(ResultRow $values) {
         return $this->render($values);
     }
@@ -280,18 +225,18 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         return '';
     }
 
+
     /**
      * Prepare the data to be send to the twig template
      */
     protected function prepareTemplateData(array $nested_data): array {
-        $field_settings = $this->options['field_settings'] ?? [];
         $index = $this->getIndex();
-        $sapi_fld_nm = $this->getSearchApiField();
+        $sapi_fld_nm = $this->nestedFieldHelper->getPluginParentFieldName($this->definition);
         
         if (!$index instanceof Index || empty($sapi_fld_nm)) {
             return $this->getEmptyTemplateData();
         }
-
+        $field_settings = $this->options['field_settings'] ?? [];
         $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_fld_nm);
         $relationships = $this->sortRelationships($relationships);
         $grouped = $this->groupRelationships($relationships);
@@ -310,50 +255,45 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
      * Build relationships array from nested data.
      */
     protected function buildRelationshipsArray(array $nested_data, array $field_settings, Index $index, string $sapi_fld_nm): array {
-        $relationships = [];
-        
-        foreach ($nested_data as $item) {
-            $item_with_values = [];
-            
-            foreach ($field_settings as $child_fld_nm => $settings) {
-                if (empty($settings['enabled']) || !isset($item[$child_fld_nm])) {
-                    continue;
-                }
-                
-                $field_value = $this->processFieldValue($item[$child_fld_nm], $settings);
-                
-                if ($field_value !== null) {
-                    $item_with_values[$child_fld_nm] = $field_value;
-                }
+        if (empty($nested_data)) {
+                return [];
             }
-            
-            if (!empty($item_with_values)) {
-                $relationships[] = $item_with_values;
-            }
-        }
-        
-        return $relationships;
-    }
-
-
-    /**
-     * Process a single field value based on its type and settings.
-     */
-    protected function processFieldValue($raw_value, array $settings): ?array {
-        $value_arr = is_array($raw_value) ? $raw_value : [$raw_value];
-        $display_mode = $settings['display_mode'] ?? 'raw';
-        $processed_values = [];
 
-        foreach($value_arr as $raw_val){
-            $processed_values[] = $this->childReferenceHelper->processSingleFieldValue($raw_val, $display_mode);        
-        }
-        
-        return [
-            'field_values' => $processed_values,
-            'separator' => $settings['multiple_separator'] ?? ', ',
-            'is_multiple' => count($value_arr) > 1,
-        ];
-               
+            // Step 1: Batch load all needed entities via helper service
+            $preloaded_entities = $this->childReferenceHelper->batchLoadEntities(
+                $nested_data, 
+                $field_settings, 
+                $index, 
+                $sapi_fld_nm
+            );
+            
+            // Step 2: Build relationships using cached entities
+            $relationships = [];
+            
+            foreach ($nested_data as $item) {
+                $item_with_values = [];       
+                foreach ($field_settings as $child_fld_nm => $settings) {
+                    if (empty($settings['enabled']) || !isset($item[$child_fld_nm])) {
+                        continue;
+                    }
+            
+                    // Use helper's cache-aware processing
+                    $field_value = $this->childReferenceHelper->batchProcessFieldValues(
+                        $item[$child_fld_nm], 
+                        $settings, 
+                        $preloaded_entities
+                    );
+                    
+                    if ($field_value !== null) {
+                        $item_with_values[$child_fld_nm] = $field_value;
+                    }
+                }       
+
+                if (!empty($item_with_values)) {
+                    $relationships[] = $item_with_values;
+                }
+            }      
+            return $relationships;
     }
 
 
@@ -375,8 +315,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             $val_b = $b[$sort_fld_nm]['field_values'][0]['value'] ?? '';
             
             return strcasecmp($val_a, $val_b);
-        });
-        
+        });  
         return $relationships;
     }
 
@@ -403,8 +342,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 $grouped[$group_key] = [];
             }
             $grouped[$group_key][] = $item;
-        }
-        
+        }  
         return $grouped;
     }
 
@@ -431,12 +369,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 'multiple_separator' => $settings['multiple_separator'] ?? ', '
             ];
         }
-
-        uasort($fields, function($a, $b) {
-            return $a['weight'] <=> $b['weight'];
-        });
-        
-        return $fields;
+        return $this->fieldConfigurator->sortFieldsByWeight($fields);
     }
 
 
@@ -470,14 +403,6 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         ];
     }
 
-    
-    /**
-     * Get the name of the search api field.
-     */
-    protected function getSearchApiField(): ?string {
-        return $this->definition['search_api field'] ?? null; // With space - as such implemented in search api.
-    }
-
 
     /**
      * Get an array of options for the field config form in views.

commit 3eb27bab0ed66a41476f1a2885deef228a1eed46
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Fri Nov 14 17:54:38 2025 +0100

    Refactor: split large service into smaller, focused services

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 22a9d72..537efa6 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -7,8 +7,10 @@ use Drupal\search_api\Plugin\views\field\SearchApiStandard;
 use Drupal\views\ResultRow;
 use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
 use Symfony\Component\DependencyInjection\ContainerInterface;
-use Drupal\relationship_nodes_search\Service\RelationSearchService;
 use Drupal\search_api\Entity\Index;
+use Drupal\relationship_nodes_search\Service\NestedFieldHelper;
+use Drupal\relationship_nodes_search\Service\ChildFieldEntityReferenceHelper;
+use Drupal\relationship_nodes_search\Service\CalculatedFieldHelper;
 
 
 /**
@@ -16,17 +18,23 @@ use Drupal\search_api\Entity\Index;
  */
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
     
-    protected RelationSearchService $relationSearchService;
+    protected NestedFieldHelper $nestedFieldHelper;
+    protected ChildFieldEntityReferenceHelper $childReferenceHelper;
+    protected CalculatedFieldHelper $calculatedFieldHelper;
 
 
     public function __construct(
         array $configuration,
         string $plugin_id,
         mixed $plugin_definition,
-        RelationSearchService $relationSearchService,
+        NestedFieldHelper $nestedFieldHelper,
+        ChildFieldEntityReferenceHelper $childReferenceHelper,
+        CalculatedFieldHelper $calculatedFieldHelper,
     ) {
         parent::__construct($configuration, $plugin_id, $plugin_definition);
-        $this->relationSearchService = $relationSearchService;
+        $this->nestedFieldHelper = $nestedFieldHelper;
+        $this->childReferenceHelper = $childReferenceHelper;
+        $this->calculatedFieldHelper = $calculatedFieldHelper;
     }
     
     
@@ -35,7 +43,9 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             $configuration,
             $plugin_id,
             $plugin_definition,
-            $container->get('relationship_nodes_search.relation_search_service'),
+            $container->get('relationship_nodes_search.nested_field_helper'),
+            $container->get('relationship_nodes_search.child_field_entity_reference_helper'),
+            $container->get('relationship_nodes_search.calculated_field_helper'),
         );
     }
 
@@ -68,7 +78,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return;
         }
 
-        $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_fld_nm);
+        $available_fields = $this->nestedFieldHelper->getProcessedNestedChildFieldNames($index, $sapi_fld_nm);
         
         if (empty($available_fields)) {
             $form['info'] = [
@@ -96,7 +106,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             $is_enabled = !empty($field_settings[$child_fld_nm]['enabled']);
             $disabled_state = ['disabled' => [':input[name="options[relation_display_settings][field_settings][' . $child_fld_nm . '][enabled]"]' => ['checked' => FALSE]]];
 
-            $link_option =  $this->relationSearchService->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm);
+            $link_option =  $this->childReferenceHelper->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm);
             
             $form['relation_display_settings']['field_settings'][$child_fld_nm] = [
                 '#type' => 'details',
@@ -129,7 +139,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             $form['relation_display_settings']['field_settings'][$child_fld_nm]['label'] = [
                 '#type' => 'textfield',
                 '#title' => $this->t('Custom label'),
-                '#default_value' => $field_settings[$child_fld_nm]['label'] ?? $this->relationSearchService->formatCalculatedFieldLabel($child_fld_nm),
+                '#default_value' => $field_settings[$child_fld_nm]['label'] ?? $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm),
                 '#description' => $this->t('Custom label for this field.'),
                 '#size' => 30,
                 '#states' => $disabled_state
@@ -335,7 +345,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         $processed_values = [];
 
         foreach($value_arr as $raw_val){
-            $processed_values[] = $this->relationSearchService->processSingleFieldValue($raw_val, $display_mode);        
+            $processed_values[] = $this->childReferenceHelper->processSingleFieldValue($raw_val, $display_mode);        
         }
         
         return [
@@ -414,7 +424,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 'name' => $child_fld_nm,
                 'label' => !empty($settings['label']) 
                     ? $settings['label'] 
-                    :  $this->relationSearchService->formatCalculatedFieldLabel($child_fld_nm),
+                    :  $this->calculatedFieldHelper->formatCalculatedFieldLabel($child_fld_nm),
                 'weight' => $settings['weight'] ?? 0,
                 'hide_label' => !empty($settings['hide_label']),
                 'display_mode' => $settings['display_mode'] ?? 'id',

commit 35f65579a99e80dae2243f11009bc2317a9baf67
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Thu Nov 6 13:34:38 2025 +0100

    archive work for nested facets : going to be removed

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 1a2e655..22a9d72 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -405,16 +405,16 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     protected function buildFieldsMetadata(array $field_settings): array {
         $fields = [];
         
-        foreach ($field_settings as $field_name => $settings) {
+        foreach ($field_settings as $child_fld_nm => $settings) {
             if (empty($settings['enabled'])) {
                 continue;
             }
             
-            $fields[$field_name] = [
-                'name' => $field_name,
+            $fields[$child_fld_nm] = [
+                'name' => $child_fld_nm,
                 'label' => !empty($settings['label']) 
                     ? $settings['label'] 
-                    :  $this->relationSearchService->formatCalculatedFieldLabel($field_name),
+                    :  $this->relationSearchService->formatCalculatedFieldLabel($child_fld_nm),
                 'weight' => $settings['weight'] ?? 0,
                 'hide_label' => !empty($settings['hide_label']),
                 'display_mode' => $settings['display_mode'] ?? 'id',

commit 10e72274ea02eb17c9d0507ef2361e693993c9f7
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Wed Nov 5 16:58:45 2025 +0100

    continue add widget for nested fields

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index e0a6364..1a2e655 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -58,17 +58,17 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     public function buildOptionsForm(&$form, FormStateInterface $form_state) {
         parent::buildOptionsForm($form, $form_state);
         
-        $sapi_field = $this->definition['search_api field'] ?? '';
+        $sapi_fld_nm = $this->getSearchApiField();
         $index = $this->getIndex();
         
-        if (!$index instanceof Index || empty($sapi_field)) {
+        if (!$index instanceof Index || empty($sapi_fld_nm)) {
             $form['error'] = [
                 '#markup' => $this->t('Cannot load index or field configuration.'),
             ];
             return;
         }
 
-        $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_field);
+        $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_fld_nm);
         
         if (empty($available_fields)) {
             $form['info'] = [
@@ -92,30 +92,27 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
         $field_settings = $this->options['field_settings'] ?? [];
 
-        $index = $this->getIndex();
-        $sapi_field = $this->getSearchApiField(); 
-
-        foreach ($available_fields as $field_name) {
-            $is_enabled = !empty($field_settings[$field_name]['enabled']);
-            $disabled_state = ['disabled' => [':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE]]];
+        foreach ($available_fields as $child_fld_nm) {
+            $is_enabled = !empty($field_settings[$child_fld_nm]['enabled']);
+            $disabled_state = ['disabled' => [':input[name="options[relation_display_settings][field_settings][' . $child_fld_nm . '][enabled]"]' => ['checked' => FALSE]]];
 
-            $link_option =  $this->relationSearchService->nestedFieldCanLink($index, $sapi_field, $field_name);
+            $link_option =  $this->relationSearchService->nestedFieldCanLink($index, $sapi_fld_nm, $child_fld_nm);
             
-            $form['relation_display_settings']['field_settings'][$field_name] = [
+            $form['relation_display_settings']['field_settings'][$child_fld_nm] = [
                 '#type' => 'details',
-                '#title' => $field_name,
+                '#title' => $child_fld_nm,
                 '#open' => $is_enabled,
             ];
 
-            $form['relation_display_settings']['field_settings'][$field_name]['enabled'] = [
+            $form['relation_display_settings']['field_settings'][$child_fld_nm]['enabled'] = [
                 '#type' => 'checkbox',
                 '#title' => $this->t('Display this field'),
                 '#default_value' => $is_enabled,
             ];
 
             if($link_option){
-                $display_mode = $field_settings[$field_name]['display_mode'] ?? 'raw';
-                $form['relation_display_settings']['field_settings'][$field_name]['display_mode'] = [
+                $display_mode = $field_settings[$child_fld_nm]['display_mode'] ?? 'raw';
+                $form['relation_display_settings']['field_settings'][$child_fld_nm]['display_mode'] = [
                     '#type' => 'radios',
                     '#title' => $this->t('Display mode'),
                     '#options' => [
@@ -129,36 +126,36 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 ];
             }
 
-            $form['relation_display_settings']['field_settings'][$field_name]['label'] = [
+            $form['relation_display_settings']['field_settings'][$child_fld_nm]['label'] = [
                 '#type' => 'textfield',
                 '#title' => $this->t('Custom label'),
-                '#default_value' => $field_settings[$field_name]['label'] ?? $this->relationSearchService->formatCalculatedFieldLabel($field_name),
+                '#default_value' => $field_settings[$child_fld_nm]['label'] ?? $this->relationSearchService->formatCalculatedFieldLabel($child_fld_nm),
                 '#description' => $this->t('Custom label for this field.'),
                 '#size' => 30,
                 '#states' => $disabled_state
             ];
 
-            $form['relation_display_settings']['field_settings'][$field_name]['weight'] = [
+            $form['relation_display_settings']['field_settings'][$child_fld_nm]['weight'] = [
                 '#type' => 'number',
                 '#title' => $this->t('Weight'),
-                '#default_value' => $field_settings[$field_name]['weight'] ?? 0,
+                '#default_value' => $field_settings[$child_fld_nm]['weight'] ?? 0,
                 '#description' => $this->t('Fields with lower weights appear first.'),
                 '#size' => 5,
                 '#states' => $disabled_state
             ];
 
-            $form['relation_display_settings']['field_settings'][$field_name]['hide_label'] = [
+            $form['relation_display_settings']['field_settings'][$child_fld_nm]['hide_label'] = [
                 '#type' => 'checkbox',
                 '#title' => $this->t('Hide label in output'),
-                '#default_value' => $field_settings[$field_name]['hide_label'] ?? FALSE,
+                '#default_value' => $field_settings[$child_fld_nm]['hide_label'] ?? FALSE,
                 '#states' => $disabled_state
             ];
 
             if(!$is_predefined){
-                $form['relation_display_settings']['field_settings'][$field_name]['multiple_separator'] = [
+                $form['relation_display_settings']['field_settings'][$child_fld_nm]['multiple_separator'] = [
                     '#type' => 'textfield',
                     '#title' => $this->t('Multiple Values Separator'),
-                    '#default_value' => $field_settings[$field_name]['multiple_separator'] ?? ', ',
+                    '#default_value' => $field_settings[$child_fld_nm]['multiple_separator'] ?? ', ',
                     '#description' => $this->t('Configure how to separate multiple values (only applies if this field has multiple values).'),
                     '#size' => 10,
                     '#states' => $disabled_state
@@ -210,8 +207,8 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
      */
     public function getValue(ResultRow $values, $field = NULL) {
         $index = $this->getIndex();
-        $sapi_field = $this->getSearchApiField();      
-        if (!$index instanceof Index || empty($sapi_field)) {
+        $sapi_fld_nm = $this->getSearchApiField();      
+        if (!$index instanceof Index || empty($sapi_fld_nm)) {
             return parent::getValue($values, $field);
         }
 
@@ -220,10 +217,10 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return parent::getValue($values, $field);
         }
 
-        if(empty($values_arr[$sapi_field])){
+        if(empty($values_arr[$sapi_fld_nm])){
             return parent::getValue($values, $field);
         }
-        $value = $values_arr[$sapi_field];
+        $value = $values_arr[$sapi_fld_nm];
         if(!is_array($value)){
             return parent::getValue($values, $field);
         }
@@ -279,13 +276,13 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     protected function prepareTemplateData(array $nested_data): array {
         $field_settings = $this->options['field_settings'] ?? [];
         $index = $this->getIndex();
-        $sapi_field = $this->getSearchApiField();
+        $sapi_fld_nm = $this->getSearchApiField();
         
-        if (!$index instanceof Index || empty($sapi_field)) {
+        if (!$index instanceof Index || empty($sapi_fld_nm)) {
             return $this->getEmptyTemplateData();
         }
 
-        $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_field);
+        $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_fld_nm);
         $relationships = $this->sortRelationships($relationships);
         $grouped = $this->groupRelationships($relationships);
         $fields = $this->buildFieldsMetadata($field_settings);
@@ -302,28 +299,21 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     /**
      * Build relationships array from nested data.
      */
-    protected function buildRelationshipsArray(array $nested_data, array $field_settings, Index $index, string $sapi_field): array {
+    protected function buildRelationshipsArray(array $nested_data, array $field_settings, Index $index, string $sapi_fld_nm): array {
         $relationships = [];
         
         foreach ($nested_data as $item) {
             $item_with_values = [];
             
-            foreach ($field_settings as $field_name => $settings) {
-                if (empty($settings['enabled']) || !isset($item[$field_name])) {
+            foreach ($field_settings as $child_fld_nm => $settings) {
+                if (empty($settings['enabled']) || !isset($item[$child_fld_nm])) {
                     continue;
                 }
                 
-                $field_value = $this->processFieldValue(
-                    $field_name,
-                    $item[$field_name],
-                    $settings,
-                    $item,
-                    $index,
-                    $sapi_field
-                );
+                $field_value = $this->processFieldValue($item[$child_fld_nm], $settings);
                 
                 if ($field_value !== null) {
-                    $item_with_values[$field_name] = $field_value;
+                    $item_with_values[$child_fld_nm] = $field_value;
                 }
             }
             
@@ -339,7 +329,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     /**
      * Process a single field value based on its type and settings.
      */
-    protected function processFieldValue(string $field_name, $raw_value, array $settings, array $item, Index $index, string $sapi_field): ?array {
+    protected function processFieldValue($raw_value, array $settings): ?array {
         $value_arr = is_array($raw_value) ? $raw_value : [$raw_value];
         $display_mode = $settings['display_mode'] ?? 'raw';
         $processed_values = [];
@@ -365,14 +355,14 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return $relationships;
         }
         
-        $sort_field = $this->options['sort_by_field'];
-        usort($relationships, function($a, $b) use ($sort_field) {
-            if (!isset($a[$sort_field]) || !isset($b[$sort_field])) {
+        $sort_fld_nm = $this->options['sort_by_field'];
+        usort($relationships, function($a, $b) use ($sort_fld_nm) {
+            if (!isset($a[$sort_fld_nm]) || !isset($b[$sort_fld_nm])) {
                 return 0;
             }
             
-            $val_a = $a[$sort_field]['field_values'][0]['value'] ?? '';
-            $val_b = $b[$sort_field]['field_values'][0]['value'] ?? '';
+            $val_a = $a[$sort_fld_nm]['field_values'][0]['value'] ?? '';
+            $val_b = $b[$sort_fld_nm]['field_values'][0]['value'] ?? '';
             
             return strcasecmp($val_a, $val_b);
         });
@@ -390,14 +380,14 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         }
         
         $grouped = [];
-        $group_field = $this->options['group_by_field'];
+        $sort_fld_nm = $this->options['group_by_field'];
         
         foreach ($relationships as $item) {
-            if (!isset($item[$group_field])) {
+            if (!isset($item[$sort_fld_nm])) {
                 continue;
             }
             
-            $group_key = $item[$group_field]['field_values'][0]['value'] ?? 'ungrouped';
+            $group_key = $item[$sort_fld_nm]['field_values'][0]['value'] ?? 'ungrouped';
             
             if (!isset($grouped[$group_key])) {
                 $grouped[$group_key] = [];

commit 02d5e7381a628e5d87155b8cc5dded5cf366e543
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Tue Oct 28 17:54:48 2025 +0100

    temp save -- continue working on nested fields widget for facets (BEF)

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 2f72fdb..e0a6364 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -68,14 +68,6 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return;
         }
 
-
-        // dit heironder verwijderen dpm
-        dpm($index->getFields()['relationship_info__relationnode__person_person__nested']);
-        dpm($index->getFields()['relationship_info__relationnode__person_person__nested']->getType());
-
-
-
-
         $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_field);
         
         if (empty($available_fields)) {

commit 0d831ef7e886ad1a08518c58f24de19af1e1fcd8
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Fri Oct 24 16:49:32 2025 +0200

    add blueprint of custom query type and processor for facets -> empty files, need to be implemented

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 267dc62..2f72fdb 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -68,6 +68,14 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return;
         }
 
+
+        // dit heironder verwijderen dpm
+        dpm($index->getFields()['relationship_info__relationnode__person_person__nested']);
+        dpm($index->getFields()['relationship_info__relationnode__person_person__nested']->getType());
+
+
+
+
         $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_field);
         
         if (empty($available_fields)) {
@@ -280,7 +288,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         $field_settings = $this->options['field_settings'] ?? [];
         $index = $this->getIndex();
         $sapi_field = $this->getSearchApiField();
-
+        
         if (!$index instanceof Index || empty($sapi_field)) {
             return $this->getEmptyTemplateData();
         }

commit 01773fa03a993c98e7db487d7c3a968f25e6afbe
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Thu Oct 23 17:36:07 2025 +0200

    experimental version of filter dropdown implemented

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 9fdb847..267dc62 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -114,12 +114,12 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             ];
 
             if($link_option){
-                $display_mode = $field_settings[$field_name]['display_mode'] ?? 'default';
+                $display_mode = $field_settings[$field_name]['display_mode'] ?? 'raw';
                 $form['relation_display_settings']['field_settings'][$field_name]['display_mode'] = [
                     '#type' => 'radios',
                     '#title' => $this->t('Display mode'),
                     '#options' => [
-                        'default' => $this->t('The raw value (id)'),
+                        'raw' => $this->t('The raw value (id)'),
                         'label' => $this->t('Label'),
                         'link' => $this->t('Label as link'),
                     ],
@@ -341,7 +341,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
      */
     protected function processFieldValue(string $field_name, $raw_value, array $settings, array $item, Index $index, string $sapi_field): ?array {
         $value_arr = is_array($raw_value) ? $raw_value : [$raw_value];
-        $display_mode = $settings['display_mode'] ?? 'default';
+        $display_mode = $settings['display_mode'] ?? 'raw';
         $processed_values = [];
 
         foreach($value_arr as $raw_val){

commit 1aa132bb738c61285acc31479281d28ccdc0fd7e
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Wed Oct 22 18:42:12 2025 +0200

    first DEV experimental version of nested filtering in views works!

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 3798a2d..9fdb847 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -29,7 +29,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         $this->relationSearchService = $relationSearchService;
     }
     
-
+    
     public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
         return new static(
             $configuration,
@@ -40,6 +40,9 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     }
 
 
+    /**
+     * Inherit docs.
+     */
     public function defineOptions() {
         $options = parent::defineOptions();
         foreach($this->getDefaultRelationFieldOptions() as $option => $default){
@@ -49,6 +52,9 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     }
 
 
+    /**
+     * Inherit docs.
+     */
     public function buildOptionsForm(&$form, FormStateInterface $form_state) {
         parent::buildOptionsForm($form, $form_state);
         
@@ -62,7 +68,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return;
         }
 
-        $available_fields = $this->relationSearchService->getCalculatedFields($index, $sapi_field);
+        $available_fields = $this->relationSearchService->getProcessedNestedChildFieldNames($index, $sapi_field);
         
         if (empty($available_fields)) {
             $form['info'] = [
@@ -86,12 +92,14 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
         $field_settings = $this->options['field_settings'] ?? [];
 
+        $index = $this->getIndex();
+        $sapi_field = $this->getSearchApiField(); 
+
         foreach ($available_fields as $field_name) {
             $is_enabled = !empty($field_settings[$field_name]['enabled']);
             $disabled_state = ['disabled' => [':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE]]];
 
-            $link_option = $this->titleFieldHasMatchingIdField($field_name);
-            $is_link = $is_enabled && $link_option && !empty($field_settings[$field_name]['link']);
+            $link_option =  $this->relationSearchService->nestedFieldCanLink($index, $sapi_field, $field_name);
             
             $form['relation_display_settings']['field_settings'][$field_name] = [
                 '#type' => 'details',
@@ -106,18 +114,25 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             ];
 
             if($link_option){
-                 $form['relation_display_settings']['field_settings'][$field_name]['link'] = [
-                    '#type' => 'checkbox',
-                    '#title' => $this->t('Display this field as a link'),
-                    '#default_value' => $is_link,
+                $display_mode = $field_settings[$field_name]['display_mode'] ?? 'default';
+                $form['relation_display_settings']['field_settings'][$field_name]['display_mode'] = [
+                    '#type' => 'radios',
+                    '#title' => $this->t('Display mode'),
+                    '#options' => [
+                        'default' => $this->t('The raw value (id)'),
+                        'label' => $this->t('Label'),
+                        'link' => $this->t('Label as link'),
+                    ],
+                    '#default_value' => $display_mode,
+                    '#description' => $this->t('How to display this field value.'),
+                    '#states' => $disabled_state,
                 ];
-
             }
 
             $form['relation_display_settings']['field_settings'][$field_name]['label'] = [
                 '#type' => 'textfield',
                 '#title' => $this->t('Custom label'),
-                '#default_value' => $field_settings[$field_name]['label'] ?? $this->formatFieldLabel($field_name),
+                '#default_value' => $field_settings[$field_name]['label'] ?? $this->relationSearchService->formatCalculatedFieldLabel($field_name),
                 '#description' => $this->t('Custom label for this field.'),
                 '#size' => 30,
                 '#states' => $disabled_state
@@ -139,7 +154,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 '#states' => $disabled_state
             ];
 
-            if(!$this->relationSearchService->isPredefinedRelationField($field_name)){
+            if(!$is_predefined){
                 $form['relation_display_settings']['field_settings'][$field_name]['multiple_separator'] = [
                     '#type' => 'textfield',
                     '#title' => $this->t('Multiple Values Separator'),
@@ -175,7 +190,10 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         ];
     }
 
-    
+
+    /**
+     * Inherit docs.
+     */
     public function submitOptionsForm(&$form, FormStateInterface $form_state) {
         parent::submitOptionsForm($form, $form_state);
         $relation_options = $form_state->getValue(['options', 'relation_display_settings']);
@@ -186,14 +204,22 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         }
     }  
 
-   
+
+    /**
+     * Inherit docs.
+     */
     public function getValue(ResultRow $values, $field = NULL) {
-        $indexed_relation_fields = $this->getOriginalNestedFields();
+        $index = $this->getIndex();
+        $sapi_field = $this->getSearchApiField();      
+        if (!$index instanceof Index || empty($sapi_field)) {
+            return parent::getValue($values, $field);
+        }
+
         $values_arr = get_object_vars($values);
         if(empty($values_arr) || !is_array($values_arr)){
             return parent::getValue($values, $field);
         }
-        $sapi_field = $this->getSearchApiField();
+
         if(empty($values_arr[$sapi_field])){
             return parent::getValue($values, $field);
         }
@@ -205,6 +231,9 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     }
 
 
+    /**
+     * Override render and sort methods to prevent the default views rendering: force to use custom rendering.
+     */
     public function render(ResultRow $values) {
         $nested_data = $this->getValue($values);
         if (empty($nested_data) || !is_array($nested_data)) {
@@ -227,10 +256,6 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         ];
     }
 
-
-    /**
-     * Override render and sort methods to prevent the default views rendering: force to use custom rendering.
-     */
     public function clickSortable() {
         return FALSE;
     }
@@ -248,82 +273,149 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         return '';
     }
 
+    /**
+     * Prepare the data to be send to the twig template
+     */
+    protected function prepareTemplateData(array $nested_data): array {
+        $field_settings = $this->options['field_settings'] ?? [];
+        $index = $this->getIndex();
+        $sapi_field = $this->getSearchApiField();
+
+        if (!$index instanceof Index || empty($sapi_field)) {
+            return $this->getEmptyTemplateData();
+        }
 
-    protected function prepareTemplateData(array $nested_data) {
+        $relationships = $this->buildRelationshipsArray($nested_data, $field_settings, $index, $sapi_field);
+        $relationships = $this->sortRelationships($relationships);
+        $grouped = $this->groupRelationships($relationships);
+        $fields = $this->buildFieldsMetadata($field_settings);
+        
+        return [
+            'relationships' => $relationships,
+            'grouped' => $grouped,
+            'summary' => $this->buildSummary($relationships, $fields, $grouped),
+            'fields' => $fields,
+        ];
+    }
 
-        $field_settings = $this->options['field_settings'] ?? [];
 
+    /**
+     * Build relationships array from nested data.
+     */
+    protected function buildRelationshipsArray(array $nested_data, array $field_settings, Index $index, string $sapi_field): array {
         $relationships = [];
+        
         foreach ($nested_data as $item) {
             $item_with_values = [];
+            
             foreach ($field_settings as $field_name => $settings) {
-                if (!empty($settings['enabled']) && isset($item[$field_name])) {
-                    $value = $item[$field_name];
-                    $is_link = !empty($settings['link']);
-                    
-                    if ($this->relationSearchService->isPredefinedRelationField($field_name)) {
-                        $item_with_values[$field_name] = [
-                            'field_values' => [[
-                                'value' => $value,
-                                'link_url' => $is_link ? $this->relationSearchService->getUrlForField($field_name, $item) : null
-                            ]]
-                        ];
-                    } else {
-                        $value_arr = is_array($value) ? $value : [$value];
-                        $formatted_value_arr = [];
-                        foreach($value_arr as $single_value){
-                            $formatted_value_arr[] = ['value' => $single_value];
-                        }
-                        $item_with_values[$field_name] = [
-                            'field_values' =>  $formatted_value_arr,
-                            'separator' => $settings['multiple_separator'] ?? ', ',
-                            'is_multiple' => count($value_arr) > 1,
-                        ];
-
-                    }
-
-
+                if (empty($settings['enabled']) || !isset($item[$field_name])) {
+                    continue;
+                }
+                
+                $field_value = $this->processFieldValue(
+                    $field_name,
+                    $item[$field_name],
+                    $settings,
+                    $item,
+                    $index,
+                    $sapi_field
+                );
+                
+                if ($field_value !== null) {
+                    $item_with_values[$field_name] = $field_value;
                 }
             }
+            
             if (!empty($item_with_values)) {
                 $relationships[] = $item_with_values;
             }
         }
+        
+        return $relationships;
+    }
 
-        if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
-            $sort_field = $this->options['sort_by_field'];
-            usort($relationships, function($a, $b) use ($sort_field) {
-                if (!isset($a[$sort_field]) || !isset($b[$sort_field])) {
-                    return 0;
-                }
-                
-                $val_a = $a[$sort_field]['field_values'][0]['value'] ?? '';
-                $val_b = $b[$sort_field]['field_values'][0]['value'] ?? '';
-                
-                return strcasecmp($val_a, $val_b);
-            });
+
+    /**
+     * Process a single field value based on its type and settings.
+     */
+    protected function processFieldValue(string $field_name, $raw_value, array $settings, array $item, Index $index, string $sapi_field): ?array {
+        $value_arr = is_array($raw_value) ? $raw_value : [$raw_value];
+        $display_mode = $settings['display_mode'] ?? 'default';
+        $processed_values = [];
+
+        foreach($value_arr as $raw_val){
+            $processed_values[] = $this->relationSearchService->processSingleFieldValue($raw_val, $display_mode);        
+        }
+        
+        return [
+            'field_values' => $processed_values,
+            'separator' => $settings['multiple_separator'] ?? ', ',
+            'is_multiple' => count($value_arr) > 1,
+        ];
+               
+    }
+
+
+    /**
+     * Sort relationships based on configured sort field.
+     */
+    protected function sortRelationships(array $relationships): array {
+        if (empty($this->options['sort_by_field']) || empty($relationships)) {
+            return $relationships;
+        }
+        
+        $sort_field = $this->options['sort_by_field'];
+        usort($relationships, function($a, $b) use ($sort_field) {
+            if (!isset($a[$sort_field]) || !isset($b[$sort_field])) {
+                return 0;
+            }
+            
+            $val_a = $a[$sort_field]['field_values'][0]['value'] ?? '';
+            $val_b = $b[$sort_field]['field_values'][0]['value'] ?? '';
+            
+            return strcasecmp($val_a, $val_b);
+        });
+        
+        return $relationships;
+    }
+
+
+    /**
+     * Group relationships based on configured group field.
+     */
+    protected function groupRelationships(array $relationships): array {
+        if (empty($this->options['group_by_field']) || empty($relationships)) {
+            return [];
         }
         
         $grouped = [];
-        if (!empty($this->options['group_by_field']) && !empty($relationships)) {
-            $group_field = $this->options['group_by_field'];
-            foreach ($relationships as $item) {
-                if (!isset($item[$group_field])) {
-                    continue;
-                }                
-                
-                $group_key = $item[$group_field]['field_values'][0]['value'] ?? 'ungrouped';
-                
-                if (!isset($grouped[$group_key])) {
-                    $grouped[$group_key] = [];
-                }
-                $grouped[$group_key][] = $item;
+        $group_field = $this->options['group_by_field'];
+        
+        foreach ($relationships as $item) {
+            if (!isset($item[$group_field])) {
+                continue;
             }
+            
+            $group_key = $item[$group_field]['field_values'][0]['value'] ?? 'ungrouped';
+            
+            if (!isset($grouped[$group_key])) {
+                $grouped[$group_key] = [];
+            }
+            $grouped[$group_key][] = $item;
         }
+        
+        return $grouped;
+    }
 
+
+    /**
+     * Build fields metadata for template.
+     */
+    protected function buildFieldsMetadata(array $field_settings): array {
         $fields = [];
+        
         foreach ($field_settings as $field_name => $settings) {
-
             if (empty($settings['enabled'])) {
                 continue;
             }
@@ -332,10 +424,10 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 'name' => $field_name,
                 'label' => !empty($settings['label']) 
                     ? $settings['label'] 
-                    : $this->formatFieldLabel($field_name),
+                    :  $this->relationSearchService->formatCalculatedFieldLabel($field_name),
                 'weight' => $settings['weight'] ?? 0,
                 'hide_label' => !empty($settings['hide_label']),
-                'is_link' => !empty($settings['link']),
+                'display_mode' => $settings['display_mode'] ?? 'id',
                 'multiple_separator' => $settings['multiple_separator'] ?? ', '
             ];
         }
@@ -344,67 +436,53 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return $a['weight'] <=> $b['weight'];
         });
         
-        $summary = [
+        return $fields;
+    }
+
+
+    /**
+     * Build summary data for template.
+     */
+    protected function buildSummary(array $relationships, array $fields, array $grouped): array {
+        return [
             'total' => count($relationships),
             'fields' => array_keys($fields),
             'has_groups' => !empty($grouped),
             'group_count' => count($grouped),
         ];
-             
-        return [
-            'relationships' => $relationships,
-            'grouped' => $grouped,
-            'summary' => $summary,
-            'fields' => $fields,
-        ];
-    }
-
-
-    protected function formatFieldLabel($field_name) {
-        $label = str_replace(['calculated_', '_'], ['', ' '], $field_name);
-        return ucfirst(trim($label));
     }
 
 
-    protected function getCalculatedFields():array {
-        $index = $this->getIndex();
-        $sapi_field = $this->getSearchApiField();    
-        if (!$index instanceof Index || empty($sapi_field)) {
-            return [];
-        }
-
-        return $this->relationSearchService->getCalculatedFields($index, $sapi_field) ?? [];
-    }
-
-
-    protected function getOriginalNestedFields(): array {
-        $index = $this->getIndex();
-        $sapi_field = $this->getSearchApiField();      
-        if (!$index instanceof Index || empty($sapi_field)) {
-            return [];
-        }
-
-        return $this->relationSearchService->getOriginalNestedFields($index, $sapi_field) ?? [];
-    }
-
-
-    protected function titleFieldHasMatchingIdField(string $title_field_name):bool{
-        $index = $this->getIndex();
-        $sapi_field = $this->getSearchApiField();   
-        if (!$index instanceof Index || empty($sapi_field)) {
-            return false;
-        }
-
-        return $this->relationSearchService->titleFieldHasMatchingIdField($index, $sapi_field, $title_field_name);
+    /**
+     * Get empty template data structure.
+     */
+    protected function getEmptyTemplateData(): array {
+        return [
+            'relationships' => [],
+            'grouped' => [],
+            'summary' => [
+                'total' => 0,
+                'fields' => [],
+                'has_groups' => false,
+                'group_count' => 0
+            ],
+            'fields' => [],
+        ];
     }
 
-
+    
+    /**
+     * Get the name of the search api field.
+     */
     protected function getSearchApiField(): ?string {
         return $this->definition['search_api field'] ?? null; // With space - as such implemented in search api.
     }
 
 
-    protected function getDefaultRelationFieldOptions(){
+    /**
+     * Get an array of options for the field config form in views.
+     */
+    protected function getDefaultRelationFieldOptions(): array{
         return [
             'field_settings' => [],
             'sort_by_field' => '',

commit b76aca7d085f0c5dea3e7afa6cbac779c44c9603
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Thu Oct 16 22:58:00 2025 +0200

    add base for displaying entity reference subfields as links in views

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index f321353..3798a2d 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -88,6 +88,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
         foreach ($available_fields as $field_name) {
             $is_enabled = !empty($field_settings[$field_name]['enabled']);
+            $disabled_state = ['disabled' => [':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE]]];
 
             $link_option = $this->titleFieldHasMatchingIdField($field_name);
             $is_link = $is_enabled && $link_option && !empty($field_settings[$field_name]['link']);
@@ -119,11 +120,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 '#default_value' => $field_settings[$field_name]['label'] ?? $this->formatFieldLabel($field_name),
                 '#description' => $this->t('Custom label for this field.'),
                 '#size' => 30,
-                '#states' => [
-                    'disabled' => [
-                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
-                    ],
-                ],
+                '#states' => $disabled_state
             ];
 
             $form['relation_display_settings']['field_settings'][$field_name]['weight'] = [
@@ -132,23 +129,26 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 '#default_value' => $field_settings[$field_name]['weight'] ?? 0,
                 '#description' => $this->t('Fields with lower weights appear first.'),
                 '#size' => 5,
-                '#states' => [
-                    'disabled' => [
-                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
-                    ],
-                ],
+                '#states' => $disabled_state
             ];
 
             $form['relation_display_settings']['field_settings'][$field_name]['hide_label'] = [
                 '#type' => 'checkbox',
                 '#title' => $this->t('Hide label in output'),
                 '#default_value' => $field_settings[$field_name]['hide_label'] ?? FALSE,
-                '#states' => [
-                    'disabled' => [
-                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
-                    ],
-                ],
+                '#states' => $disabled_state
             ];
+
+            if(!$this->relationSearchService->isPredefinedRelationField($field_name)){
+                $form['relation_display_settings']['field_settings'][$field_name]['multiple_separator'] = [
+                    '#type' => 'textfield',
+                    '#title' => $this->t('Multiple Values Separator'),
+                    '#default_value' => $field_settings[$field_name]['multiple_separator'] ?? ', ',
+                    '#description' => $this->t('Configure how to separate multiple values (only applies if this field has multiple values).'),
+                    '#size' => 10,
+                    '#states' => $disabled_state
+                ];
+            }
         }
 
         $form['relation_display_settings']['sort_by_field'] = [
@@ -261,18 +261,28 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                     $value = $item[$field_name];
                     $is_link = !empty($settings['link']);
                     
-
-                    $item_with_values[$field_name] = [
-                        'field_value' => $value,
-                        'link_url' => null,
-                    ];
-                    
-                    if ($is_link) {
-                        $url = $this->relationSearchService->getUrlForField($field_name, $item);
-                        if ($url) {
-                            $item_with_values[$field_name]['link_url'] = $url;
+                    if ($this->relationSearchService->isPredefinedRelationField($field_name)) {
+                        $item_with_values[$field_name] = [
+                            'field_values' => [[
+                                'value' => $value,
+                                'link_url' => $is_link ? $this->relationSearchService->getUrlForField($field_name, $item) : null
+                            ]]
+                        ];
+                    } else {
+                        $value_arr = is_array($value) ? $value : [$value];
+                        $formatted_value_arr = [];
+                        foreach($value_arr as $single_value){
+                            $formatted_value_arr[] = ['value' => $single_value];
                         }
+                        $item_with_values[$field_name] = [
+                            'field_values' =>  $formatted_value_arr,
+                            'separator' => $settings['multiple_separator'] ?? ', ',
+                            'is_multiple' => count($value_arr) > 1,
+                        ];
+
                     }
+
+
                 }
             }
             if (!empty($item_with_values)) {
@@ -283,8 +293,12 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
             $sort_field = $this->options['sort_by_field'];
             usort($relationships, function($a, $b) use ($sort_field) {
-                $val_a = $a[$sort_field]['field_value'] ?? '';
-                $val_b = $b[$sort_field]['field_value'] ?? '';
+                if (!isset($a[$sort_field]) || !isset($b[$sort_field])) {
+                    return 0;
+                }
+                
+                $val_a = $a[$sort_field]['field_values'][0]['value'] ?? '';
+                $val_b = $b[$sort_field]['field_values'][0]['value'] ?? '';
                 
                 return strcasecmp($val_a, $val_b);
             });
@@ -294,8 +308,12 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         if (!empty($this->options['group_by_field']) && !empty($relationships)) {
             $group_field = $this->options['group_by_field'];
             foreach ($relationships as $item) {
-                $group_key = $item[$group_field]['field_value'] ?? 'ungrouped';
-        
+                if (!isset($item[$group_field])) {
+                    continue;
+                }                
+                
+                $group_key = $item[$group_field]['field_values'][0]['value'] ?? 'ungrouped';
+                
                 if (!isset($grouped[$group_key])) {
                     $grouped[$group_key] = [];
                 }
@@ -318,6 +336,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 'weight' => $settings['weight'] ?? 0,
                 'hide_label' => !empty($settings['hide_label']),
                 'is_link' => !empty($settings['link']),
+                'multiple_separator' => $settings['multiple_separator'] ?? ', '
             ];
         }
 

commit 1a1de9a6dcb365c53eef45006e20469abbf2bf50
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Mon Oct 13 20:44:00 2025 +0200

    add condition group for neseted fields

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 1fb9924..f321353 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -7,7 +7,7 @@ use Drupal\search_api\Plugin\views\field\SearchApiStandard;
 use Drupal\views\ResultRow;
 use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
 use Symfony\Component\DependencyInjection\ContainerInterface;
-use Drupal\relationship_nodes_search\Service\RelationViewService;
+use Drupal\relationship_nodes_search\Service\RelationSearchService;
 use Drupal\search_api\Entity\Index;
 
 
@@ -16,17 +16,17 @@ use Drupal\search_api\Entity\Index;
  */
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
     
-    protected RelationViewService $relationViewService;
+    protected RelationSearchService $relationSearchService;
 
 
     public function __construct(
         array $configuration,
         string $plugin_id,
         mixed $plugin_definition,
-        RelationViewService $relationViewService,
+        RelationSearchService $relationSearchService,
     ) {
         parent::__construct($configuration, $plugin_id, $plugin_definition);
-        $this->relationViewService = $relationViewService;
+        $this->relationSearchService = $relationSearchService;
     }
     
 
@@ -35,18 +35,16 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             $configuration,
             $plugin_id,
             $plugin_definition,
-            $container->get('relationship_nodes_search.relation_view_service'),
+            $container->get('relationship_nodes_search.relation_search_service'),
         );
     }
 
 
     public function defineOptions() {
         $options = parent::defineOptions();
-
         foreach($this->getDefaultRelationFieldOptions() as $option => $default){
             $options[$option] = ['default' => $default];
         }
-
         return $options;
     }
 
@@ -54,17 +52,18 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     public function buildOptionsForm(&$form, FormStateInterface $form_state) {
         parent::buildOptionsForm($form, $form_state);
         
-        $real_field = $this->definition['search_api field'] ?? '';
+        $sapi_field = $this->definition['search_api field'] ?? '';
         $index = $this->getIndex();
         
-        if (!$index instanceof Index || empty($real_field)) {
+        if (!$index instanceof Index || empty($sapi_field)) {
             $form['error'] = [
                 '#markup' => $this->t('Cannot load index or field configuration.'),
             ];
             return;
         }
 
-        $available_fields = $this->relationViewService->getCalculatedFields($index, $real_field);
+        $available_fields = $this->relationSearchService->getCalculatedFields($index, $sapi_field);
+        
         if (empty($available_fields)) {
             $form['info'] = [
                 '#markup' => $this->t('No nested fields available. Please configure nested fields in the Search API index.'),
@@ -89,6 +88,9 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
         foreach ($available_fields as $field_name) {
             $is_enabled = !empty($field_settings[$field_name]['enabled']);
+
+            $link_option = $this->titleFieldHasMatchingIdField($field_name);
+            $is_link = $is_enabled && $link_option && !empty($field_settings[$field_name]['link']);
             
             $form['relation_display_settings']['field_settings'][$field_name] = [
                 '#type' => 'details',
@@ -102,6 +104,15 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 '#default_value' => $is_enabled,
             ];
 
+            if($link_option){
+                 $form['relation_display_settings']['field_settings'][$field_name]['link'] = [
+                    '#type' => 'checkbox',
+                    '#title' => $this->t('Display this field as a link'),
+                    '#default_value' => $is_link,
+                ];
+
+            }
+
             $form['relation_display_settings']['field_settings'][$field_name]['label'] = [
                 '#type' => 'textfield',
                 '#title' => $this->t('Custom label'),
@@ -175,47 +186,24 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         }
     }  
 
-
+   
     public function getValue(ResultRow $values, $field = NULL) {
-        foreach (get_object_vars($values) as $property => $value) {
-            if (str_starts_with($property, 'relationship_info__') && is_array($value)) {
-                return $value;
-            }
+        $indexed_relation_fields = $this->getOriginalNestedFields();
+        $values_arr = get_object_vars($values);
+        if(empty($values_arr) || !is_array($values_arr)){
+            return parent::getValue($values, $field);
         }
-        return parent::getValue($values, $field);
-    }
-    /**
-     * Complex array data cannot be sorted directly.
-     */
-    public function clickSortable() {
-        return FALSE;
-    }
-
-    /**
-     * Override to prevent rendering of individual items.
-     * all rendering by the render() method.
-     */
-    public function renderItems($items) {
-        // Return empty - we handle rendering in render() method
-        return [];
+        $sapi_field = $this->getSearchApiField();
+        if(empty($values_arr[$sapi_field])){
+            return parent::getValue($values, $field);
+        }
+        $value = $values_arr[$sapi_field];
+        if(!is_array($value)){
+            return parent::getValue($values, $field);
+        }
+        return $value;
     }
 
-    /**
-     * Override advancedRender to prevent default field rendering.
-     * Our render() method returns a render array that should be used directly.
-     */
-    public function advancedRender(ResultRow $values) {
-        return $this->render($values);
-    }
-
-    /**
-     * Override render_item to prevent it from being called.
-     * This prevents the "array to string" error.
-     */
-    public function render_item($count, $item) {
-        // This should never be called because we return render array from render()
-        return '';
-    }
 
     public function render(ResultRow $values) {
         $nested_data = $this->getValue($values);
@@ -239,10 +227,29 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         ];
     }
 
-    protected function prepareTemplateData(array $nested_data) {
 
-        $selected_fields = array_filter($this->options['relation_fields'] ?? []); //weg
+    /**
+     * Override render and sort methods to prevent the default views rendering: force to use custom rendering.
+     */
+    public function clickSortable() {
+        return FALSE;
+    }
+
+    public function renderItems($items) {
+        return [];
+    }
+
+    public function advancedRender(ResultRow $values) {
+        return $this->render($values);
+    }
+
+    
+    public function render_item($count, $item) {
+        return '';
+    }
+
 
+    protected function prepareTemplateData(array $nested_data) {
 
         $field_settings = $this->options['field_settings'] ?? [];
 
@@ -251,7 +258,21 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             $item_with_values = [];
             foreach ($field_settings as $field_name => $settings) {
                 if (!empty($settings['enabled']) && isset($item[$field_name])) {
-                    $item_with_values[$field_name] = $item[$field_name];
+                    $value = $item[$field_name];
+                    $is_link = !empty($settings['link']);
+                    
+
+                    $item_with_values[$field_name] = [
+                        'field_value' => $value,
+                        'link_url' => null,
+                    ];
+                    
+                    if ($is_link) {
+                        $url = $this->relationSearchService->getUrlForField($field_name, $item);
+                        if ($url) {
+                            $item_with_values[$field_name]['link_url'] = $url;
+                        }
+                    }
                 }
             }
             if (!empty($item_with_values)) {
@@ -259,25 +280,22 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             }
         }
 
-
-        // Sort if needed
         if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
             $sort_field = $this->options['sort_by_field'];
             usort($relationships, function($a, $b) use ($sort_field) {
-                $val_a = $a[$sort_field] ?? '';
-                $val_b = $b[$sort_field] ?? '';
+                $val_a = $a[$sort_field]['field_value'] ?? '';
+                $val_b = $b[$sort_field]['field_value'] ?? '';
                 
-                // Case-insensitive vergelijking
                 return strcasecmp($val_a, $val_b);
             });
         }
         
-        // Group if needed
         $grouped = [];
         if (!empty($this->options['group_by_field']) && !empty($relationships)) {
             $group_field = $this->options['group_by_field'];
             foreach ($relationships as $item) {
-                $group_key = $item[$group_field] ?? 'ungrouped';
+                $group_key = $item[$group_field]['field_value'] ?? 'ungrouped';
+        
                 if (!isset($grouped[$group_key])) {
                     $grouped[$group_key] = [];
                 }
@@ -287,7 +305,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
         $fields = [];
         foreach ($field_settings as $field_name => $settings) {
-            // Only include if enabled
+
             if (empty($settings['enabled'])) {
                 continue;
             }
@@ -297,27 +315,23 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 'label' => !empty($settings['label']) 
                     ? $settings['label'] 
                     : $this->formatFieldLabel($field_name),
-                'type' => $this->getFieldType($field_name),
                 'weight' => $settings['weight'] ?? 0,
                 'hide_label' => !empty($settings['hide_label']),
+                'is_link' => !empty($settings['link']),
             ];
         }
-        
-        // Sort fields by weight
+
         uasort($fields, function($a, $b) {
             return $a['weight'] <=> $b['weight'];
         });
         
-        // Create summary data
         $summary = [
             'total' => count($relationships),
             'fields' => array_keys($fields),
             'has_groups' => !empty($grouped),
             'group_count' => count($grouped),
         ];
-        
-
-        
+             
         return [
             'relationships' => $relationships,
             'grouped' => $grouped,
@@ -326,52 +340,52 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         ];
     }
 
+
     protected function formatFieldLabel($field_name) {
         $label = str_replace(['calculated_', '_'], ['', ' '], $field_name);
         return ucfirst(trim($label));
     }
 
-    protected function getFieldType($field_name) {
-        if (str_ends_with($field_name, '_id')) {
-            return 'id';
-        }
-        if (str_ends_with($field_name, '_title') || str_ends_with($field_name, '_label')) {
-            return 'title';
-        }
-        if (str_ends_with($field_name, '_type')) {
-            return 'type';
+
+    protected function getCalculatedFields():array {
+        $index = $this->getIndex();
+        $sapi_field = $this->getSearchApiField();    
+        if (!$index instanceof Index || empty($sapi_field)) {
+            return [];
         }
-        return 'text';
+
+        return $this->relationSearchService->getCalculatedFields($index, $sapi_field) ?? [];
     }
 
-        protected function getCalculatedFields():array {
 
+    protected function getOriginalNestedFields(): array {
         $index = $this->getIndex();
-        $real_field = $this->getRealField();
-             
-        if (!$index instanceof Index || empty($real_field)) {
+        $sapi_field = $this->getSearchApiField();      
+        if (!$index instanceof Index || empty($sapi_field)) {
             return [];
         }
 
-        return $this->relationViewService->getCalculatedFields($index, $real_field) ?? [];
+        return $this->relationSearchService->getOriginalNestedFields($index, $sapi_field) ?? [];
     }
 
-    protected function getOriginalNestedFields(): array {
+
+    protected function titleFieldHasMatchingIdField(string $title_field_name):bool{
         $index = $this->getIndex();
-        $real_field = $this->getRealField();
-             
-        if (!$index instanceof Index || empty($real_field)) {
-            return [];
+        $sapi_field = $this->getSearchApiField();   
+        if (!$index instanceof Index || empty($sapi_field)) {
+            return false;
         }
 
-        return $this->relationViewService->getOriginalNestedFields($index, $real_field) ?? [];
+        return $this->relationSearchService->titleFieldHasMatchingIdField($index, $sapi_field, $title_field_name);
     }
 
-    protected function getRealField(): ?string {
-        return $this->definition['search_api field'] ?? null;
+
+    protected function getSearchApiField(): ?string {
+        return $this->definition['search_api field'] ?? null; // With space - as such implemented in search api.
     }
 
-        protected function getDefaultRelationFieldOptions(){
+
+    protected function getDefaultRelationFieldOptions(){
         return [
             'field_settings' => [],
             'sort_by_field' => '',

commit fa5344fe4e709beb8909f81a88c3bbfb5447b966
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Fri Oct 10 00:02:00 2025 +0200

    basic views relation field display handler implemented... needs to be refined!!!

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
index 0eb1b77..1fb9924 100644
--- a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -10,9 +10,8 @@ use Symfony\Component\DependencyInjection\ContainerInterface;
 use Drupal\relationship_nodes_search\Service\RelationViewService;
 use Drupal\search_api\Entity\Index;
 
+
 /**
- * Field handler for nested relationship data with configurable sub-fields.
- *
  * @ViewsField("search_api_relationship_field")
  */
 class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
@@ -43,11 +42,10 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
     public function defineOptions() {
         $options = parent::defineOptions();
-        // Welke nested fields tonen
-        $options['relation_fields'] = ['default' => []];
-        $options['template'] = ['default' => 'relationship-field'];
-        $options['sort_by_field'] = ['default' => ''];
-        $options['group_by_field'] = ['default' => ''];
+
+        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
+            $options[$option] = ['default' => $default];
+        }
 
         return $options;
     }
@@ -65,6 +63,7 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             ];
             return;
         }
+
         $available_fields = $this->relationViewService->getCalculatedFields($index, $real_field);
         if (empty($available_fields)) {
             $form['info'] = [
@@ -73,31 +72,75 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             return;
         }
 
-        $form['field_settings'] = [
+        $form['relation_display_settings'] = [
             '#type' => 'details',
-            '#title' => $this->t('Relation fields'),
+            '#title' => $this->t('Relation display settings'),
             '#open' => TRUE,
         ];
 
+        $form['relation_display_settings']['field_settings'] = [
+            '#type' => 'fieldset',
+            '#title' => $this->t('Field configuration'),
+            '#description' => $this->t('Select fields to display and configure their appearance.'),
+            '#tree' => TRUE
+        ];
+
+        $field_settings = $this->options['field_settings'] ?? [];
 
-        $form['field_settings']['template'] = [
-            '#type' => 'textfield',
-            '#title' => $this->t('Template name'),
-            '#default_value' => $this->options['template'],
-            '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
+        foreach ($available_fields as $field_name) {
+            $is_enabled = !empty($field_settings[$field_name]['enabled']);
             
-        ];
+            $form['relation_display_settings']['field_settings'][$field_name] = [
+                '#type' => 'details',
+                '#title' => $field_name,
+                '#open' => $is_enabled,
+            ];
 
+            $form['relation_display_settings']['field_settings'][$field_name]['enabled'] = [
+                '#type' => 'checkbox',
+                '#title' => $this->t('Display this field'),
+                '#default_value' => $is_enabled,
+            ];
 
-        $form['field_settings']['relation_fields'] = [
-            '#type' => 'checkboxes',
-            '#title' => $this->t('Fields to pass to template'),
-            '#options' => array_combine($available_fields, $available_fields),
-            '#default_value' => $this->options['relation_fields'] ?? [],
-            '#description' => $this->t('Select which fields to make available in the template.'),
-        ];
+            $form['relation_display_settings']['field_settings'][$field_name]['label'] = [
+                '#type' => 'textfield',
+                '#title' => $this->t('Custom label'),
+                '#default_value' => $field_settings[$field_name]['label'] ?? $this->formatFieldLabel($field_name),
+                '#description' => $this->t('Custom label for this field.'),
+                '#size' => 30,
+                '#states' => [
+                    'disabled' => [
+                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
+                    ],
+                ],
+            ];
+
+            $form['relation_display_settings']['field_settings'][$field_name]['weight'] = [
+                '#type' => 'number',
+                '#title' => $this->t('Weight'),
+                '#default_value' => $field_settings[$field_name]['weight'] ?? 0,
+                '#description' => $this->t('Fields with lower weights appear first.'),
+                '#size' => 5,
+                '#states' => [
+                    'disabled' => [
+                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
+                    ],
+                ],
+            ];
+
+            $form['relation_display_settings']['field_settings'][$field_name]['hide_label'] = [
+                '#type' => 'checkbox',
+                '#title' => $this->t('Hide label in output'),
+                '#default_value' => $field_settings[$field_name]['hide_label'] ?? FALSE,
+                '#states' => [
+                    'disabled' => [
+                        ':input[name="options[relation_display_settings][field_settings][' . $field_name . '][enabled]"]' => ['checked' => FALSE],
+                    ],
+                ],
+            ];
+        }
 
-        $form['field_settings']['sort_by_field'] = [
+        $form['relation_display_settings']['sort_by_field'] = [
             '#type' => 'select',
             '#title' => $this->t('Sort by field'),
             '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
@@ -105,27 +148,33 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
             '#description' => $this->t('Sort relationships by this field value.'),
         ];
 
-        $form['field_settings']['group_by_field'] = [
+        $form['relation_display_settings']['group_by_field'] = [
             '#type' => 'select',
             '#title' => $this->t('Group by field'),
             '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
             '#default_value' => $this->options['group_by_field'],
             '#description' => $this->t('Group relationships by this field value.'),
         ];
-    }
 
+        $form['relation_display_settings']['template'] = [
+            '#type' => 'textfield',
+            '#title' => $this->t('Template name'),
+            '#default_value' => $this->options['template'],
+            '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
+        ];
+    }
 
+    
     public function submitOptionsForm(&$form, FormStateInterface $form_state) {
         parent::submitOptionsForm($form, $form_state);
-        
-        $field_settings = $form_state->getValue(['options', 'field_settings']);
-        if ($field_settings) {
-            foreach ($field_settings as $key => $value) {
-                $this->options[$key] = $value;
+        $relation_options = $form_state->getValue(['options', 'relation_display_settings']);
+        foreach($this->getDefaultRelationFieldOptions() as $option => $default){
+            if (isset($relation_options[$option])) {
+                $this->options[$option] = $relation_options[$option];
             }
         }
-        // Fixed: changed $options to $this->options
-    }
+    }  
+
 
     public function getValue(ResultRow $values, $field = NULL) {
         foreach (get_object_vars($values) as $property => $value) {
@@ -135,7 +184,6 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         }
         return parent::getValue($values, $field);
     }
-
     /**
      * Complex array data cannot be sorted directly.
      */
@@ -171,11 +219,10 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
 
     public function render(ResultRow $values) {
         $nested_data = $this->getValue($values);
-        
         if (empty($nested_data) || !is_array($nested_data)) {
             return '';
         }
-        
+
         $template_data = $this->prepareTemplateData($nested_data);
         $theme_hook = str_replace('-', '_', $this->options['template']);
         
@@ -193,27 +240,35 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
     }
 
     protected function prepareTemplateData(array $nested_data) {
-        $selected_fields = array_filter($this->options['relation_fields'] ?? []);
-        
-        // Filter data to only include selected fields
+
+        $selected_fields = array_filter($this->options['relation_fields'] ?? []); //weg
+
+
+        $field_settings = $this->options['field_settings'] ?? [];
+
         $relationships = [];
         foreach ($nested_data as $item) {
-            $filtered_item = [];
-            foreach ($selected_fields as $field_name) {
-                if (isset($item[$field_name])) {
-                    $filtered_item[$field_name] = $item[$field_name];
+            $item_with_values = [];
+            foreach ($field_settings as $field_name => $settings) {
+                if (!empty($settings['enabled']) && isset($item[$field_name])) {
+                    $item_with_values[$field_name] = $item[$field_name];
                 }
             }
-            if (!empty($filtered_item)) {
-                $relationships[] = $filtered_item;
+            if (!empty($item_with_values)) {
+                $relationships[] = $item_with_values;
             }
         }
-        
+
+
         // Sort if needed
         if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
             $sort_field = $this->options['sort_by_field'];
             usort($relationships, function($a, $b) use ($sort_field) {
-                return ($a[$sort_field] ?? '') <=> ($b[$sort_field] ?? '');
+                $val_a = $a[$sort_field] ?? '';
+                $val_b = $b[$sort_field] ?? '';
+                
+                // Case-insensitive vergelijking
+                return strcasecmp($val_a, $val_b);
             });
         }
         
@@ -229,24 +284,39 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
                 $grouped[$group_key][] = $item;
             }
         }
+
+        $fields = [];
+        foreach ($field_settings as $field_name => $settings) {
+            // Only include if enabled
+            if (empty($settings['enabled'])) {
+                continue;
+            }
+            
+            $fields[$field_name] = [
+                'name' => $field_name,
+                'label' => !empty($settings['label']) 
+                    ? $settings['label'] 
+                    : $this->formatFieldLabel($field_name),
+                'type' => $this->getFieldType($field_name),
+                'weight' => $settings['weight'] ?? 0,
+                'hide_label' => !empty($settings['hide_label']),
+            ];
+        }
+        
+        // Sort fields by weight
+        uasort($fields, function($a, $b) {
+            return $a['weight'] <=> $b['weight'];
+        });
         
         // Create summary data
         $summary = [
             'total' => count($relationships),
-            'fields' => array_keys($selected_fields),
+            'fields' => array_keys($fields),
             'has_groups' => !empty($grouped),
             'group_count' => count($grouped),
         ];
         
-        // Add field metadata
-        $fields = [];
-        foreach ($selected_fields as $field_name) {
-            $fields[$field_name] = [
-                'name' => $field_name,
-                'label' => $this->formatFieldLabel($field_name),
-                'type' => $this->getFieldType($field_name),
-            ];
-        }
+
         
         return [
             'relationships' => $relationships,
@@ -273,4 +343,40 @@ class RelationshipField extends SearchApiStandard implements ContainerFactoryPlu
         }
         return 'text';
     }
+
+        protected function getCalculatedFields():array {
+
+        $index = $this->getIndex();
+        $real_field = $this->getRealField();
+             
+        if (!$index instanceof Index || empty($real_field)) {
+            return [];
+        }
+
+        return $this->relationViewService->getCalculatedFields($index, $real_field) ?? [];
+    }
+
+    protected function getOriginalNestedFields(): array {
+        $index = $this->getIndex();
+        $real_field = $this->getRealField();
+             
+        if (!$index instanceof Index || empty($real_field)) {
+            return [];
+        }
+
+        return $this->relationViewService->getOriginalNestedFields($index, $real_field) ?? [];
+    }
+
+    protected function getRealField(): ?string {
+        return $this->definition['search_api field'] ?? null;
+    }
+
+        protected function getDefaultRelationFieldOptions(){
+        return [
+            'field_settings' => [],
+            'sort_by_field' => '',
+            'group_by_field' => '',
+            'template' => 'relationship-field',
+        ];
+    }
 }
\ No newline at end of file

commit 3ff66cc2e7eb4ea20c3a181bb33fb2027692611a
Author: Hans Blomme <hans.blomme@ugent.be>
Date:   Wed Oct 8 18:52:37 2025 +0200

    add missing fields + quick fix paragraphs interface - db serialization error

diff --git a/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
new file mode 100644
index 0000000..0eb1b77
--- /dev/null
+++ b/web/modules/custom/relationship_nodes_search/src/Plugin/views/field/RelationshipField.php
@@ -0,0 +1,276 @@
+<?php
+
+namespace Drupal\relationship_nodes_search\Plugin\views\field;
+
+use Drupal\Core\Form\FormStateInterface;
+use Drupal\search_api\Plugin\views\field\SearchApiStandard;
+use Drupal\views\ResultRow;
+use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
+use Symfony\Component\DependencyInjection\ContainerInterface;
+use Drupal\relationship_nodes_search\Service\RelationViewService;
+use Drupal\search_api\Entity\Index;
+
+/**
+ * Field handler for nested relationship data with configurable sub-fields.
+ *
+ * @ViewsField("search_api_relationship_field")
+ */
+class RelationshipField extends SearchApiStandard implements ContainerFactoryPluginInterface {
+    
+    protected RelationViewService $relationViewService;
+
+
+    public function __construct(
+        array $configuration,
+        string $plugin_id,
+        mixed $plugin_definition,
+        RelationViewService $relationViewService,
+    ) {
+        parent::__construct($configuration, $plugin_id, $plugin_definition);
+        $this->relationViewService = $relationViewService;
+    }
+    
+
+    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
+        return new static(
+            $configuration,
+            $plugin_id,
+            $plugin_definition,
+            $container->get('relationship_nodes_search.relation_view_service'),
+        );
+    }
+
+
+    public function defineOptions() {
+        $options = parent::defineOptions();
+        // Welke nested fields tonen
+        $options['relation_fields'] = ['default' => []];
+        $options['template'] = ['default' => 'relationship-field'];
+        $options['sort_by_field'] = ['default' => ''];
+        $options['group_by_field'] = ['default' => ''];
+
+        return $options;
+    }
+
+
+    public function buildOptionsForm(&$form, FormStateInterface $form_state) {
+        parent::buildOptionsForm($form, $form_state);
+        
+        $real_field = $this->definition['search_api field'] ?? '';
+        $index = $this->getIndex();
+        
+        if (!$index instanceof Index || empty($real_field)) {
+            $form['error'] = [
+                '#markup' => $this->t('Cannot load index or field configuration.'),
+            ];
+            return;
+        }
+        $available_fields = $this->relationViewService->getCalculatedFields($index, $real_field);
+        if (empty($available_fields)) {
+            $form['info'] = [
+                '#markup' => $this->t('No nested fields available. Please configure nested fields in the Search API index.'),
+            ];
+            return;
+        }
+
+        $form['field_settings'] = [
+            '#type' => 'details',
+            '#title' => $this->t('Relation fields'),
+            '#open' => TRUE,
+        ];
+
+
+        $form['field_settings']['template'] = [
+            '#type' => 'textfield',
+            '#title' => $this->t('Template name'),
+            '#default_value' => $this->options['template'],
+            '#description' => $this->t('Template file name without .html.twig extension. Will look for templates/[name].html.twig'),
+            
+        ];
+
+
+        $form['field_settings']['relation_fields'] = [
+            '#type' => 'checkboxes',
+            '#title' => $this->t('Fields to pass to template'),
+            '#options' => array_combine($available_fields, $available_fields),
+            '#default_value' => $this->options['relation_fields'] ?? [],
+            '#description' => $this->t('Select which fields to make available in the template.'),
+        ];
+
+        $form['field_settings']['sort_by_field'] = [
+            '#type' => 'select',
+            '#title' => $this->t('Sort by field'),
+            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
+            '#default_value' => $this->options['sort_by_field'],
+            '#description' => $this->t('Sort relationships by this field value.'),
+        ];
+
+        $form['field_settings']['group_by_field'] = [
+            '#type' => 'select',
+            '#title' => $this->t('Group by field'),
+            '#options' => ['' => $this->t('- None -')] + array_combine($available_fields, $available_fields),
+            '#default_value' => $this->options['group_by_field'],
+            '#description' => $this->t('Group relationships by this field value.'),
+        ];
+    }
+
+
+    public function submitOptionsForm(&$form, FormStateInterface $form_state) {
+        parent::submitOptionsForm($form, $form_state);
+        
+        $field_settings = $form_state->getValue(['options', 'field_settings']);
+        if ($field_settings) {
+            foreach ($field_settings as $key => $value) {
+                $this->options[$key] = $value;
+            }
+        }
+        // Fixed: changed $options to $this->options
+    }
+
+    public function getValue(ResultRow $values, $field = NULL) {
+        foreach (get_object_vars($values) as $property => $value) {
+            if (str_starts_with($property, 'relationship_info__') && is_array($value)) {
+                return $value;
+            }
+        }
+        return parent::getValue($values, $field);
+    }
+
+    /**
+     * Complex array data cannot be sorted directly.
+     */
+    public function clickSortable() {
+        return FALSE;
+    }
+
+    /**
+     * Override to prevent rendering of individual items.
+     * all rendering by the render() method.
+     */
+    public function renderItems($items) {
+        // Return empty - we handle rendering in render() method
+        return [];
+    }
+
+    /**
+     * Override advancedRender to prevent default field rendering.
+     * Our render() method returns a render array that should be used directly.
+     */
+    public function advancedRender(ResultRow $values) {
+        return $this->render($values);
+    }
+
+    /**
+     * Override render_item to prevent it from being called.
+     * This prevents the "array to string" error.
+     */
+    public function render_item($count, $item) {
+        // This should never be called because we return render array from render()
+        return '';
+    }
+
+    public function render(ResultRow $values) {
+        $nested_data = $this->getValue($values);
+        
+        if (empty($nested_data) || !is_array($nested_data)) {
+            return '';
+        }
+        
+        $template_data = $this->prepareTemplateData($nested_data);
+        $theme_hook = str_replace('-', '_', $this->options['template']);
+        
+        return [
+            '#theme' => $theme_hook,
+            '#relationships' => $template_data['relationships'],
+            '#grouped' => $template_data['grouped'],
+            '#summary' => $template_data['summary'],
+            '#fields' => $template_data['fields'],
+            '#row' => $values,
+            '#cache' => [
+                'contexts' => ['languages:language_content'],
+            ],
+        ];
+    }
+
+    protected function prepareTemplateData(array $nested_data) {
+        $selected_fields = array_filter($this->options['relation_fields'] ?? []);
+        
+        // Filter data to only include selected fields
+        $relationships = [];
+        foreach ($nested_data as $item) {
+            $filtered_item = [];
+            foreach ($selected_fields as $field_name) {
+                if (isset($item[$field_name])) {
+                    $filtered_item[$field_name] = $item[$field_name];
+                }
+            }
+            if (!empty($filtered_item)) {
+                $relationships[] = $filtered_item;
+            }
+        }
+        
+        // Sort if needed
+        if (!empty($this->options['sort_by_field']) && !empty($relationships)) {
+            $sort_field = $this->options['sort_by_field'];
+            usort($relationships, function($a, $b) use ($sort_field) {
+                return ($a[$sort_field] ?? '') <=> ($b[$sort_field] ?? '');
+            });
+        }
+        
+        // Group if needed
+        $grouped = [];
+        if (!empty($this->options['group_by_field']) && !empty($relationships)) {
+            $group_field = $this->options['group_by_field'];
+            foreach ($relationships as $item) {
+                $group_key = $item[$group_field] ?? 'ungrouped';
+                if (!isset($grouped[$group_key])) {
+                    $grouped[$group_key] = [];
+                }
+                $grouped[$group_key][] = $item;
+            }
+        }
+        
+        // Create summary data
+        $summary = [
+            'total' => count($relationships),
+            'fields' => array_keys($selected_fields),
+            'has_groups' => !empty($grouped),
+            'group_count' => count($grouped),
+        ];
+        
+        // Add field metadata
+        $fields = [];
+        foreach ($selected_fields as $field_name) {
+            $fields[$field_name] = [
+                'name' => $field_name,
+                'label' => $this->formatFieldLabel($field_name),
+                'type' => $this->getFieldType($field_name),
+            ];
+        }
+        
+        return [
+            'relationships' => $relationships,
+            'grouped' => $grouped,
+            'summary' => $summary,
+            'fields' => $fields,
+        ];
+    }
+
+    protected function formatFieldLabel($field_name) {
+        $label = str_replace(['calculated_', '_'], ['', ' '], $field_name);
+        return ucfirst(trim($label));
+    }
+
+    protected function getFieldType($field_name) {
+        if (str_ends_with($field_name, '_id')) {
+            return 'id';
+        }
+        if (str_ends_with($field_name, '_title') || str_ends_with($field_name, '_label')) {
+            return 'title';
+        }
+        if (str_ends_with($field_name, '_type')) {
+            return 'type';
+        }
+        return 'text';
+    }
+}
\ No newline at end of file
