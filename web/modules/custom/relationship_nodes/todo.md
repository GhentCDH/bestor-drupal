# relationship_nodes — TODO

## Bugs

- **`relationship_nodes.install` line 19**: `$errorFormatter` is used but never defined.
  `$validation_result->getFormattedErrors($errorFormatter, 'relationship_nodes')` will throw an
  undefined-variable notice. Should be:
  `\Drupal::service('relationship_nodes.validation_result_formatter')`.

- **`SettingsCleanupService::unsetRnEntitySettings()`**: assigns `$updated` after the save call
  but never uses it. Either remove the assignment or act on the return value.

- **`ValidRelationReferenceConstraintValidator`** and **`AvailableMirrorTermConstraintValidator`**:
  both use `\Drupal::service()` for dependency lookup instead of constructor injection.
  The constraint validator factory supports DI — these should declare their services properly.


## Missing features / known gaps

- **Nested field display not functional**: `computed_relationshipfield__*` fields show the
  relation node, but displaying fields *of* the relation node (nested field display) is not yet
  implemented.

- **Relation nodes can only be enabled on existing content types**: the form alter hooks target
  `node_type_edit_form` and `taxonomy_vocabulary_edit_form`; `node_type_add_form` and
  `taxonomy_vocabulary_form` are not handled. The vocabulary add form shows the option but does
  not save it.

- **Self-reference constraint missing**: a relation node should not be able to reference the same
  entity in both `rn_related_entity_1` and `rn_related_entity_2`. The constraint class skeleton
  (`ValidRelationReferenceConstraint`) exists but the validator does not yet enforce this.

- **`RelationInlineEntityForm::getTableFields()`**: custom override is a near-copy of the parent
  implementation. Revisit whether the override is still necessary and either simplify or document
  why it diverges.

- **Auto-title defaults**: consider providing sensible display/form mode defaults when auto-title
  is enabled (title field hidden, IEF widget pre-configured).


## Code quality / technical debt

- **`fromConfigToEntities()` in `ConfigImportSubscriber`**: missing return type annotation (`array`).

- **`RelationNode::generateTitle()`**: uses the fully-qualified class name
  `\Drupal\Core\Entity\EntityTypeManagerInterface` inline instead of a `use` import.

- **`RelationIefWidget::__construct()`**: missing `{@inheritdoc}` docblock.

- **`RelationWeightManager::getAllWeights()`**: missing return type annotation.

- **`RelationFormHelper`**: uses hard tabs throughout instead of the two-space indentation
  required by Drupal coding standards.

- **`RelationInlineEntityForm::getTableFields()`**: indentation switches from tabs to four spaces
  partway through the method; should be normalised to two spaces.

- **`RendererInterface $renderer` in `RelationshipTwigFormatter`**: injected but may be unused
  inside the class itself (the renderer call may be fully delegated to the twig extension).
  Verify and remove if not needed.

- **Two deprecated methods in `ValidationService`**: `getFieldStorageValidationErrors()` and
  `getFieldConfigValidationErrors()` are marked `@deprecated` but have no removal target version.
  Add a target or remove them once callers are confirmed gone.

- **`BundleInfoService`** mixes live-site query methods with CIM (config-import) query methods in
  one class. Consider splitting into `BundleInfoService` (runtime) and a dedicated CIM helper,
  or at minimum group and comment the two sets of methods clearly.

- **`RelationWeightManager` key pattern**: the storage key `{nid}.{reference_field}` is
  constructed in two different places (`getKey()` and implicitly in callers). Centralise the
  pattern so a future change only needs one edit.


## relationship_nodes_search

- **Field type recognition**: child fields coming from relation nodes need to be mapped to their
  correct Search API data types before indexing.

- **Autocomplete widget**: the search-filter autocomplete widget is not yet implemented.

- **Index errors on module disable**: disabling the module while an Elasticsearch index is
  active can trigger `SearchApiException` inside
  `ElasticsearchConnector\SearchAPI\BackendClient->updateSettings()`. Add a pre-uninstall hook
  that removes relation-node fields from active indexes before the module is torn down.
