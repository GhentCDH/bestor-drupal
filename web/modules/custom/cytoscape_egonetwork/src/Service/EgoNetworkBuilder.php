<?php

namespace Drupal\cytoscape_egonetwork\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\relationship_nodes\RelationData\NodeHelper\RelationInfo;
use Drupal\relationship_nodes\RelationField\FieldNameResolver;

/**
 * Builds Cytoscape.js graph data for the ego network of a node.
 *
 * Graph structure:
 * - Root node: the node being viewed.
 * - Level-1 nodes: all nodes connected to the root via a relationship node.
 * - Relationship nodes are collapsed: they become edge labels, never graph nodes.
 * - Internal edges: relationships between level-1 nodes (no new nodes added).
 */
class EgoNetworkBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RelationInfo $relationInfo,
    private readonly FieldNameResolver $fieldNameResolver,
  ) {}

  /**
   * Builds graph data for the given root node.
   *
   * @return array{elements: array, meta: array}
   */
  public function build(NodeInterface $root): array {
    // getAllReferencingRelations returns ['bundle_id' => [nid => Node, ...], ...]
    // where the values are the relationship nodes (not the peers).
    $allRelationNodes = $this->relationInfo->getAllReferencingRelations($root);

    if (empty($allRelationNodes)) {
      return ['elements' => [], 'meta' => []];
    }

    $rootNid     = (int) $root->id();
    $level1Nodes = [];  // nid => NodeInterface
    $edges       = [];  // Cytoscape edge arrays

    // Step 1: collect all level-1 peers and root↔peer edges.
    foreach ($allRelationNodes as $bundle => $relationNodes) {
      foreach ($relationNodes as $relationNode) {
        $endpoints = $this->relationInfo->getRelatedEntityValues($relationNode);
        if (empty($endpoints)) {
          continue;
        }

        // Flatten all endpoint nids and find the peer (the one that is not root).
        $allNids = array_merge(...array_values($endpoints));
        $peerNids = array_filter($allNids, fn($nid) => (int) $nid !== $rootNid);

        foreach ($peerNids as $peerNid) {
          $peerNid = (int) $peerNid;
          if (!isset($level1Nodes[$peerNid])) {
            $peer = $this->entityTypeManager->getStorage('node')->load($peerNid);
            if (!$peer instanceof NodeInterface) {
              continue;
            }
            $level1Nodes[$peerNid] = $peer;
          }

          $label    = $this->edgeLabel($relationNode);
          $edges[]  = $this->buildEdgeElement($rootNid, $peerNid, $label, (int) $relationNode->id());
        }
      }
    }

    if (empty($level1Nodes)) {
      return ['elements' => [], 'meta' => []];
    }

    // Step 2: find relationships between level-1 nodes (no new nodes).
    if (count($level1Nodes) > 1) {
      $level1NidSet = array_keys($level1Nodes);

      foreach ($level1Nodes as $level1Node) {
        $level1RelationNodes = $this->relationInfo->getAllReferencingRelations($level1Node);

        foreach ($level1RelationNodes as $bundle => $relationNodes) {
          foreach ($relationNodes as $relationNode) {
            $endpoints = $this->relationInfo->getRelatedEntityValues($relationNode);
            if (empty($endpoints)) {
              continue;
            }

            $allNids = array_merge(...array_values($endpoints));

            // Both endpoints must be within the level-1 set.
            $inSet = array_filter($allNids, fn($nid) => in_array((int) $nid, $level1NidSet));
            if (count($inSet) < 2) {
              continue;
            }

            [$aNid, $bNid] = array_values($inSet);
            $label   = $this->edgeLabel($relationNode);
            $edges[] = $this->buildEdgeElement((int) $aNid, (int) $bNid, $label, (int) $relationNode->id());
          }
        }
      }
    }

    $edges = $this->deduplicateEdges($edges);

    // Step 3: assemble node elements and collect bundle colours.
    $bundleColors = [];
    $nodeElements = [];

    $nodeElements[]                      = $this->buildNodeElement($root, isRoot: TRUE);
    $bundleColors[$root->bundle()]      ??= $this->bundleColor($root->bundle());

    foreach ($level1Nodes as $node) {
      $nodeElements[]                    = $this->buildNodeElement($node, isRoot: FALSE);
      $bundleColors[$node->bundle()]    ??= $this->bundleColor($node->bundle());
    }

    return [
      'elements' => array_merge($nodeElements, $edges),
      'meta'     => ['bundleColors' => $bundleColors],
    ];
  }

  // ---------------------------------------------------------------------------
  // Element builders
  // ---------------------------------------------------------------------------

  private function buildNodeElement(NodeInterface $node, bool $isRoot): array {
    return [
      'data' => [
        'id'     => 'n' . $node->id(),
        'label'  => $node->getTitle(),
        'bundle' => $node->bundle(),
        'url'    => $node->toUrl()->toString(),
        'isRoot' => $isRoot,
      ],
    ];
  }

  private function buildEdgeElement(int $sourceNid, int $targetNid, string $label, int $relationNid): array {
    return [
      'data' => [
        // Edge id includes relation node id so the same pair can have multiple
        // labelled edges when connected via different relationship nodes.
        'id'     => 'e' . $relationNid,
        'source' => 'n' . $sourceNid,
        'target' => 'n' . $targetNid,
        'label'  => $label,
      ],
    ];
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Returns the edge label for a collapsed relationship node.
   *
   * Uses the relation type field value when present, falls back to node title.
   */
  private function edgeLabel(NodeInterface $relationNode): string {
    $typeField = $this->fieldNameResolver->getRelationTypeField();

    if ($relationNode->hasField($typeField) && !$relationNode->get($typeField)->isEmpty()) {
      $ref = $relationNode->get($typeField)->first();
      // The field may be a taxonomy term reference — use the term label.
      if ($ref->entity instanceof \Drupal\Core\Entity\EntityInterface) {
        return (string) $ref->entity->label();
      }
      // Plain text value (getString works for text, list, etc.).
      $value = $ref->getString();
      if ($value !== '') {
        return $value;
      }
    }

    return $relationNode->getTitle();
  }

  /**
   * Removes duplicate edges for the same node pair.
   *
   * When two level-1 nodes are connected by multiple relationship nodes, each
   * relationship gets its own edge (different id + label). True duplicates
   * (exact same edge id) are removed.
   */
  private function deduplicateEdges(array $edges): array {
    $seen   = [];
    $unique = [];

    foreach ($edges as $edge) {
      $id = $edge['data']['id'];
      if (!isset($seen[$id])) {
        $seen[$id]  = TRUE;
        $unique[]   = $edge;
      }
    }

    return $unique;
  }

  /**
   * Returns a deterministic hex colour for a bundle name.
   *
   * Colours are spread around the hue wheel so different bundles get visually
   * distinct colours without any configuration.
   */
  private function bundleColor(string $bundle): string {
    // Map the bundle string to a 0–359 hue value.
    $hash = crc32($bundle);
    $hue  = abs($hash) % 360;

    // Convert HSL (fixed saturation + lightness) to hex.
    return $this->hslToHex($hue, 55, 45);
  }

  private function hslToHex(int $h, int $s, int $l): string {
    $s /= 100;
    $l /= 100;

    $a = $s * min($l, 1 - $l);
    $f = function (int $n) use ($h, $l, $a): int {
      $k = ($n + $h / 30) % 12;
      return (int) round(($l - $a * max(-1, min($k - 3, 9 - $k, 1))) * 255);
    };

    return sprintf('#%02x%02x%02x', $f(0), $f(8), $f(4));
  }

}
