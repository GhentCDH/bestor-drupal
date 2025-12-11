<?php

namespace Drupal\bestor_content_helper\Service;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\bestor_content_helper\Service\NodeContentAnalyzer;
use Drupal\node\NodeInterface;

/**
 * Service for analyzing and formatting node content.
 * 
 * Provides utilities for extracting key data from lemma nodes,
 * calculating reading times, and formatting field values.
 */
class CurrentPageAnalyzer {

  protected RouteMatchInterface $routeMatch;
  protected NodeContentAnalyzer $nodeAnalyzer;

  /**
   * Constructs a NodeContentAnalyzer object.
   *
   * @param RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(RouteMatchInterface $route_match, NodeContentAnalyzer $nodeAnalyzer) {
    $this->routeMatch = $route_match;
    $this->nodeAnalyzer = $nodeAnalyzer;
  }


  /**
   * Get current node from route.
   *
   * @return NodeInterface|null
   *   The current node or NULL.
   */
  public function getCurrentNode(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
  }


  /**
   * Check if current page is a lemma node.
   *
   * @return bool
   *   TRUE if current page is a lemma node.
   */
  public function currentPageIsLemma(): bool {
    $node = $this->getCurrentNode();
    
    if (!$node) {
      return FALSE;
    }

    return $this->nodeAnalyzer->isLemma($node->bundle());
  }


  public function isMainSearchView(): bool {
    $current_view = $this->routeMatch->getParameter('view_id');
    
    if (
      $this->routeMatch->getParameter('view_id') !== 'database' ||
      $this->routeMatch->getParameter('display_id') !== 'page_1'
    ) {
      return FALSE;
    }

    return TRUE;
  }

  public function getPageVariant(): string {
    if ($this->getCurrentNode()) {
      return 'lemma';
    } elseif ($this->isMainSearchView()) {
      return 'search';
    }
    return 'default';
  }


}  