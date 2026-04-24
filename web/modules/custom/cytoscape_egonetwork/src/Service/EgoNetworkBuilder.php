<?php

namespace Drupal\cytoscape_egonetwork\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\relationship_nodes\Display\RelationAvailability;
use Drupal\relationship_nodes\Display\RelationshipDataBuilder;
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
 *
 * Language handling:
 * - AVAILABLE: peer has translation in current language → shown normally.
 * - LANGUAGE_UNAVAILABLE: peer exists but not in current language → depends on
 *   $langUnavailableSetting ('hide' or 'fade').
 * - UNAVAILABLE: peer has no published translation → never shown.
 */
class EgoNetworkBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RelationInfo $relationInfo,
    private readonly FieldNameResolver $fieldNameResolver,
    private readonly LanguageManagerInterface $languageManager,
    private readonly RelationshipDataBuilder $relationshipDataBuilder,
  ) {}

  /**
   * Builds graph data for the given root node.
   *
   * @param NodeInterface $root
   *   The node being viewed.
   * @param string $langUnavailableSetting
   *   How to handle relations unavailable in the current language:
   *   - 'hide': exclude them from the graph entirely.
   *   - 'fade': include them but mark as langUnavailable for the JS layer.
   *
   * @return array{elements: array, meta: array}
   */
  public function build(NodeInterface $root, string $langUnavailableSetting = 'hide'): array {
    $langcode         = $this->languageManager->getCurrentLanguage()->getId();
    $allRelationNodes = $this->relationInfo->getAllReferencingRelations($root);

    if (empty($allRelationNodes)) {
      return ['elements' => [], 'meta' => []];
    }

    $rootNid     = (int) $root->id();
    $level1Nodes = [];  // nid => ['node' => NodeInterface, 'langUnavailable' => bool]
    $edges       = [];

    // Step 1: collect level-1 peers and root↔peer edges.
    foreach ($allRelationNodes as $bundle => $relationNodes) {
      foreach ($relationNodes as $relationNode) {
        $endpoints = $this->relationInfo->getRelatedEntityValues($relationNode);
        if (empty($endpoints)) {
          continue;
        }

        $allNids  = array_merge(...array_values($endpoints));
        $peerNids = array_filter($allNids, fn($nid) => (int) $nid !== $rootNid);

        foreach ($peerNids as $peerNid) {
          $peerNid = (int) $peerNid;

          if (!isset($level1Nodes[$peerNid])) {
            $peer = $this->entityTypeManager->getStorage('node')->load($peerNid);
            if (!$peer instanceof NodeInterface) {
              continue;
            }

            $availability = $this->relationshipDataBuilder->getRelationAvailability($relationNode, $langcode);

            // Never show completely unavailable relations.
            if ($availability->isUnavailable()) {
              continue;
            }

            $langUnavailable = $availability->isLanguageUnavailable();

            // Respect the hide/fade setting.
            if ($langUnavailable && $langUnavailableSetting === 'hide') {
              continue;
            }

            $level1Nodes[$peerNid] = [
              'node'            => $peer,
              'langUnavailable' => $langUnavailable,
            ];
          }

          $label   = $this->edgeLabel($relationNode, $langcode);;
          $edges[] = $this->buildEdgeElement($rootNid, $peerNid, $label, (int) $relationNode->id());
        }
      }
    }

    if (empty($level1Nodes)) {
      return ['elements' => [], 'meta' => []];
    }

    // Step 2: find relationships between level-1 nodes (no new nodes).
    if (count($level1Nodes) > 1) {
      $level1NidSet = array_keys($level1Nodes);

      foreach ($level1Nodes as $peerNid => $peerData) {
        $level1RelationNodes = $this->relationInfo->getAllReferencingRelations($peerData['node']);

        foreach ($level1RelationNodes as $bundle => $relationNodes) {
          foreach ($relationNodes as $relationNode) {
            $endpoints = $this->relationInfo->getRelatedEntityValues($relationNode);
            if (empty($endpoints)) {
              continue;
            }

            $allNids = array_merge(...array_values($endpoints));
            $inSet   = array_filter($allNids, fn($nid) => in_array((int) $nid, $level1NidSet));

            if (count($inSet) < 2) {
              continue;
            }

            [$aNid, $bNid] = array_values($inSet);
            $label   = $this->edgeLabel($relationNode, $langcode);
            $edges[] = $this->buildEdgeElement((int) $aNid, (int) $bNid, $label, (int) $relationNode->id());
          }
        }
      }
    }

    $edges = $this->deduplicateEdges($edges);

    // Step 3: assemble node elements.
    $bundleColors = [];
    $nodeElements = [];

    $nodeElements[]                 = $this->buildNodeElement($root, isRoot: TRUE, langUnavailable: FALSE, langcode: $langcode);
    $bundleColors[$root->bundle()] ??= $this->bundleColor($root->bundle());

    foreach ($level1Nodes as $peerNid => $peerData) {
      $node            = $peerData['node'];
      $langUnavailable = $peerData['langUnavailable'];

      $nodeElements[]              = $this->buildNodeElement($node, isRoot: FALSE, langUnavailable: $langUnavailable, langcode: $langcode);
      $bundleColors[$node->bundle()] ??= $this->bundleColor($node->bundle());
    }

    return [
      'elements' => array_merge($nodeElements, $edges),
      'meta'     => ['bundleColors' => $bundleColors],
    ];
  }

  // ---------------------------------------------------------------------------
  // Element builders
  // ---------------------------------------------------------------------------

  private function buildNodeElement(NodeInterface $node, bool $isRoot, bool $langUnavailable, string $langcode): array {
    // Use the translated version if available.
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }

    return [
      'data' => [
        'id'              => 'n' . $node->id(),
        'label'           => $node->getTitle(),
        'bundle'          => $node->bundle(),
        'url'             => $node->toUrl()->toString(),
        'isRoot'          => $isRoot,
        'langUnavailable' => $langUnavailable,
      ],
    ];
  }

  private function buildEdgeElement(int $sourceNid, int $targetNid, string $label, int $relationNid): array {
    return [
      'data' => [
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

  private function edgeLabel(NodeInterface $relationNode, string $langcode): string {
    $typeField = $this->fieldNameResolver->getRelationTypeField();

    if (!$relationNode->hasField($typeField) || $relationNode->get($typeField)->isEmpty()) {
      return '';
    }

    $ref = $relationNode->get($typeField)->first();
    if (!$ref->entity instanceof \Drupal\Core\Entity\EntityInterface) {
      return '';
    }

    $term = $ref->entity;
    if ($term->hasTranslation($langcode)) {
      $term = $term->getTranslation($langcode);
    }

    return (string) $term->label();
  }

  private function deduplicateEdges(array $edges): array {
    $seen   = [];
    $unique = [];

    foreach ($edges as $edge) {
      $id = $edge['data']['id'];
      if (!isset($seen[$id])) {
        $seen[$id] = TRUE;
        $unique[]  = $edge;
      }
    }

    return $unique;
  }

  private function bundleColor(string $bundle): string {
    $hash = crc32($bundle);
    $hue  = abs($hash) % 360;
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