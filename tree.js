document.addEventListener('DOMContentLoaded', function () {
  async function fetchTreeData(treeId) {
    try {
      const response = await fetch(`/wp-json/fraternity-tree/v1/tree/${treeId}`);
      if (!response.ok) {
        throw new Error(`Failed to fetch tree data: ${response.statusText}`);
      }
      return await response.json();
    } catch (error) {
      console.error(error);
      alert('Failed to load tree data. Please try again later.');
      return null;
    }
  }

  function createPopup(details) {
    const modal = document.createElement('div');
    modal.className = 'popup-modal';

    const overlay = document.createElement('div');
    overlay.className = 'popup-overlay';
    overlay.onclick = () => modal.remove();

    const content = document.createElement('div');
    content.className = 'popup-content';

    const closeBtn = document.createElement('button');
    closeBtn.innerText = 'Close';
    closeBtn.onclick = () => modal.remove();

    const table = document.createElement('table');
    const fields = [
      ['First Name', details.first_name || 'N/A'],
      ['Last Name', details.last_name || 'N/A'],
      ['Pledge Class', details.pledge_class || 'N/A'],
      ['Graduation Year', details.graduation_year || 'N/A'],
      ['Majors', details.majors ? JSON.parse(details.majors).join(', ') : 'N/A'],
    ];

    fields.forEach(([label, value]) => {
      const row = table.insertRow();
      row.insertCell().innerText = label;
      row.insertCell().innerText = value;
    });

    content.appendChild(closeBtn);
    content.appendChild(table);
    modal.appendChild(overlay);
    modal.appendChild(content);
    document.body.appendChild(modal);

    // Trap focus within modal
    content.focus();
  }

  function renderTree(treeId) {
    fetchTreeData(treeId).then((data) => {
      if (!data) return; // Exit if data fetch failed

      const container = d3.select('#fraternity-tree-container');
      container.selectAll('*').remove(); // Clear previous content

      const width = 800;
      const height = 600;

      const svg = container.append('svg').attr('width', width).attr('height', height);

      const simulation = d3
        .forceSimulation(data.nodes)
        .force('link', d3.forceLink(data.links).id((d) => d.id).distance(100))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width / 2, height / 2));

      const link = svg
        .append('g')
        .selectAll('line')
        .data(data.links)
        .enter()
        .append('line')
        .attr('stroke-width', 2)
        .attr('stroke', '#999');

      const node = svg
        .append('g')
        .selectAll('circle')
        .data(data.nodes)
        .enter()
        .append('circle')
        .attr('r', 10)
        .attr('fill', '#69b3a2')
        .call(
          d3
            .drag()
            .on('start', (event, d) => {
              if (!event.active) simulation.alphaTarget(0.3).restart();
              d.fx = d.x;
              d.fy = d.y;
            })
            .on('drag', (event, d) => {
              d.fx = event.x;
              d.fy = event.y;
            })
            .on('end', (event, d) => {
              if (!event.active) simulation.alphaTarget(0);
              d.fx = null;
              d.fy = null;
            })
        )
        .on('click', (event, d) => {
          createPopup(d.details);
        });

      simulation.on('tick', () => {
        link
          .attr('x1', (d) => d.source.x)
          .attr('y1', (d) => d.source.y)
          .attr('x2', (d) => d.target.x)
          .attr('y2', (d) => d.target.y);

        node.attr('cx', (d) => d.x).attr('cy', (d) => d.y);
      });
    });
  }

  const treeContainer = document.getElementById('fraternity-tree-container');
  if (treeContainer) {
    const treeId = treeContainer.dataset.treeId;
    renderTree(treeId);
  }
});
