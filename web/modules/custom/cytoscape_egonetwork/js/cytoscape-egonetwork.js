(function (Drupal, once) {

  Drupal.behaviors.cytoscapeEgonetwork = {
    attach(context) {
      once('cytoscape-egonetwork', '.cytoscape-egonetwork', context).forEach(el => {
        const settings = drupalSettings.cytoscapeEgonetwork ?? null;
        if (!settings?.graph?.elements?.length) {
          return;
        }

        const { graph, layout: layoutName } = settings;
        const bundleColors = graph.meta?.bundleColors ?? {};

        const cy = cytoscape({
          container: el,
          elements: graph.elements,
          style: buildStyle(bundleColors),
          layout: buildLayout(layoutName ?? 'cose'),
        });

        cy.on('tap', 'node', e => {
          const url = e.target.data('url');
          if (url) {
            window.location.href = url;
          }
        });

        attachHoverBehaviour(cy);
      });
    }
  };

  function buildStyle(bundleColors) {
    return [
      {
        selector: 'node',
        style: {
          label: 'data(label)',
          'background-color': node => bundleColors[node.data('bundle')] ?? '#888888',
          color: '#ffffff',
          'text-valign': 'center',
          'text-halign': 'center',
          'text-wrap': 'wrap',
          'text-max-width': '80px',
          'font-size': 9,
          width: 70,
          height: 70,
          'border-width': node => node.data('isRoot') ? 4 : 0,
          'border-color': '#ffffff',
        },
      },
      {
        // Language-unavailable nodes: faded with dashed border.
        selector: 'node[?langUnavailable]',
        style: {
          opacity: 0.45,
          'border-width': 2,
          'border-style': 'dashed',
          'border-color': '#aaaaaa',
        },
      },
      {
        selector: 'edge',
        style: {
          label: ele => ele.data('label') || '',
          'font-size': 8,
          'curve-style': 'bezier',
          'target-arrow-shape': 'triangle',
          'line-color': '#aaaaaa',
          'target-arrow-color': '#aaaaaa',
          color: '#555555',
          'text-rotation': 'autorotate',
          'text-margin-y': -8,
        },
      },
      {
        selector: 'node:selected',
        style: {
          'border-width': 4,
          'border-color': '#ffcc00',
        },
      },
      {
        selector: 'node.highlighted',
        style: {
          'border-width': 3,
          'border-color': '#ffcc00',
          'background-color': node => lighten(bundleColors[node.data('bundle')] ?? '#888888'),
        },
      },
      {
        selector: 'edge.highlighted',
        style: {
          'line-color': '#ffcc00',
          'target-arrow-color': '#ffcc00',
        },
      },
      {
        selector: '.faded',
        style: {
          opacity: 0.2,
        },
      },
      {
        selector: 'node[?isRoot]',
        style: {
          shape: 'star',
          width: 100,
          height: 100,
        },
      },
    ];
  }

  function buildLayout(name) {
    const shared = { animate: false, padding: 40 };
    switch (name) {
      case 'breadthfirst':
        return { ...shared, name: 'breadthfirst', directed: true, spacingFactor: 1.5 };
      case 'circle':
        return { ...shared, name: 'circle' };
      case 'grid':
        return { ...shared, name: 'grid' };
      case 'concentric':
        return { ...shared, name: 'concentric', concentric: n => n.degree(), levelWidth: () => 2 };
      case 'cose':
      default:
        return { ...shared, name: 'cose', nodeRepulsion: 8000, idealEdgeLength: 100 };
    }
  }

  function attachHoverBehaviour(cy) {
    cy.on('mouseover', 'node', e => {
      const node = e.target;
      const neighbourhood = node.closedNeighborhood();
      cy.elements().addClass('faded');
      neighbourhood.removeClass('faded').addClass('highlighted');
    });

    cy.on('mouseout', 'node', () => {
      cy.elements().removeClass('faded highlighted');
    });
  }

  function lighten(hex) {
    const n = parseInt(hex.replace('#', ''), 16);
    const r = Math.min(255, ((n >> 16) & 0xff) + 40);
    const g = Math.min(255, ((n >> 8) & 0xff) + 40);
    const b = Math.min(255, (n & 0xff) + 40);
    return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
  }

}(Drupal, once));