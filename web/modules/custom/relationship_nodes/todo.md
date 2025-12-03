todo:
RELATIONSHIP NODES
- display van nested fields nog niet functioneel
- testing and debugging
- relationship nodes can only be enabled for already created content types, not for node_type_add_form. Examine if this can be fixed. Same goes for taxopnomy_vocabulary-form (where the option is shown, but not stored)
- add constraint: a realtion node cannot refer to itself.
- eventueel defaults bij form en display van relation nodes en relation vocabs aanpassen?

RELATIONSHIP NODES SEARCH
- move relationhsip_nodes_search from a separate module to a submodule of relationship_nodes
- recognize field types of child fields (mapping before indexing)
- implement autocomplete widget
- disabling the module can produce index errors: Drupal\search_api\SearchApiException: An error occurred updating settings for index [xxx]. in Drupal\elasticsearch_connector\SearchAPI\BackendClient->updateSettings()
- testing and debugging