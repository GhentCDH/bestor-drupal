# relationship_nodes — TODO

## Features

- **Nested field display**: `computed_relationshipfield__*` fields show the relation node, but
  displaying fields *of* the relation node (nested field display) is not yet implemented.

- **Enable relation nodes on new content types**: the form alter hooks target
  `node_type_edit_form` and `taxonomy_vocabulary_edit_form`; `node_type_add_form` and
  `taxonomy_vocabulary_form` are not handled. The vocabulary add form shows the option but does
  not save it.

- **Auto-title defaults**: provide sensible display/form mode defaults when auto-title is enabled
  (title field hidden, IEF widget pre-configured).


## Refactoring

- **`BundleInfoService`** mixes live-site query methods with CIM (config-import) query methods.
  Consider splitting or at minimum grouping and commenting the two sets of methods clearly.

- **`RelationInlineEntityForm::getTableFields()`**: custom override is a near-copy of the parent.
  Verify whether the override is still necessary and simplify or document why it diverges.


## relationship_nodes_search

- **Field type recognition**: child fields from relation nodes need to be mapped to their correct
  Search API data types before indexing.

- **Autocomplete widget**: the search-filter autocomplete widget is not yet implemented.
