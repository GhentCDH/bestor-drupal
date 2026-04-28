# cytoscape_egonetwork

Ego network visualization using Cytoscape.js.

## Purpose

Renders an interactive graph of a node's relationships: the viewed node as the root, all directly related nodes as level-1 peers, and relationships between peers as internal edges. Relationship nodes are collapsed — they become edge labels in the graph, never visible graph nodes.

## Architecture overview

```
src/
├── Service/EgoNetworkBuilder.php             — builds Cytoscape.js graph data from relation nodes
├── Plugin/Field/FieldFormatter/
│   └── EgoNetworkFormatter.php               — field formatter; attaches graph data to drupalSettings
└── Form/EgoNetworkSettingsForm.php           — admin settings form
js/cytoscape-egonetwork.js                    — Cytoscape.js initialization and rendering
config/install/cytoscape_egonetwork.settings.yml
config/schema/cytoscape_egonetwork.schema.yml
```

## Graph model

- **Root node**: the node being viewed
- **Level-1 nodes**: all nodes connected to the root via a relation node
- **Edges**: each relation node becomes one edge between its two referenced nodes, with the relation type term name as the edge label
- **Internal edges**: relationships between two level-1 nodes (no new nodes are added)
- Duplicate edges (same relation node ID) are deduplicated

## Language handling

Each relation (and its referenced peers) is classified by availability:

| State | Meaning | Behaviour |
|-------|---------|-----------|
| `AVAILABLE` | Peer has a published translation in the current language | Shown normally |
| `LANGUAGE_UNAVAILABLE` | Peer exists and is published, but not in the current language | Depends on `language_unavailable` formatter setting |
| `UNAVAILABLE` | Peer has no published translation in any language | Never shown |

The `language_unavailable` setting (configurable per field formatter instance) is either `'hide'` or `'fade'`. `'fade'` passes `langUnavailable: true` to the JS layer so nodes can be styled differently.

## Field formatter (`EgoNetworkFormatter`)

Applied to a boolean field named `show_ego_network`. When the field value is TRUE, the formatter calls `EgoNetworkBuilder::build()`, serializes the result as JSON into `drupalSettings.cytoscapeEgonetwork`, and attaches the `cytoscape_egonetwork/egonetwork` library. The JS layer reads from `drupalSettings` and renders the graph.

Note: the formatter instantiates `EgoNetworkBuilder` via `\Drupal::service()` rather than constructor injection. This is a known pattern for field formatters in Drupal and acceptable here.

## Configuration

Admin form at the path defined in `cytoscape_egonetwork.links.menu.yml`. Settings are stored in `cytoscape_egonetwork.settings` config. The `language_unavailable` option is also available per-formatter-instance in the field display settings.

## Dependencies

- `relationship_nodes:relationship_nodes` (via `EgoNetworkBuilder` services)
- Cytoscape.js library (declared in `cytoscape_egonetwork.libraries.yml`)
