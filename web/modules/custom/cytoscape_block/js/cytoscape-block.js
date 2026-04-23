(function (Drupal, once) {

  Drupal.behaviors.cytoscapeBlock = {
    attach(context) {
      once('cytoscape-block', '.cytoscape-block', context).forEach(el => {
        const data = drupalSettings.cytoscape_block_data ?? null;
        const layout = drupalSettings.cytoscapeBlock?.layout ?? 'cose';

        if (!data) {
          return;
        }

        const bundleColors = data.meta?.bundleColors ?? {};

        const cy = cytoscape({
          container: el,
          elements: data.elements,
          style: buildStyle(bundleColors),
          layout: buildLayout(layout),
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
          'font-size': 11,
          width: 70,
          height: 70,
          'border-width': node => node.data('isRoot') ? 4 : 0,
          'border-color': '#ffffff',
        },
      },
      {
        selector: 'edge',
        style: {
          label: 'data(label)',
          'font-size': 9,
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

  // Highlight neighbours on hover, fade the rest.
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

  // Naive hex lightener for hover highlight — keeps it dependency-free.
  function lighten(hex) {
    const n = parseInt(hex.replace('#', ''), 16);
    const r = Math.min(255, ((n >> 16) & 0xff) + 40);
    const g = Math.min(255, ((n >> 8) & 0xff) + 40);
    const b = Math.min(255, (n & 0xff) + 40);
    return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
  }

}(Drupal, once));