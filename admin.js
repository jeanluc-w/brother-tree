jQuery(document).ready(function ($) {
  function ajaxRequest(action, data, successCallback, errorCallback) {
      $.ajax({
          url: ajaxurl,
          method: 'POST',
          data: {
              action: 'manage_nodes',
              crud_action: action,
              data: data,
          },
          success: successCallback,
          error: errorCallback || function (err) {
              console.error('Error:', err);
          },
      });
  }

  function fetchAndRenderNodes() {
      const searchQuery = $('#search-input').val();
      const filterPledgeClass = $('#filter-pledge-class').val();
      const filterGradYear = $('#filter-grad-year').val();
      const filterMajors = $('#filter-majors').val();
      const filterLines = $('#filter-lines').val();

      ajaxRequest(
          'fetch',
          { 
              search: searchQuery, 
              pledge_class: filterPledgeClass, 
              graduation_year: filterGradYear,
              majors: filterMajors,
              lines: filterLines,
          },
          function (response) {
              const tbody = $('#nodes-table tbody');
              tbody.empty();

              response.nodes.forEach((node) => {
                  const row = `<tr>
                      <td>${node.first_name} ${node.last_name}</td>
                      <td>${node.pledge_class}</td>
                      <td>${node.graduation_year}</td>
                      <td>
                          <button class="edit-node" data-id="${node.node_id}">Edit</button>
                          <button class="delete-node" data-id="${node.node_id}">Delete</button>
                      </td>
                  </tr>`;
                  tbody.append(row);
              });
          }
      );
  }

  // Create or update node
  $('#node-form').on('submit', function (e) {
      e.preventDefault();

      const data = $(this).serializeArray().reduce((acc, field) => {
          acc[field.name] = field.value;
          return acc;
      }, {});

      const action = data.node_id ? 'update' : 'create';

      if ($('#founding-member').is(':checked')) {
          data.founding_member = true;
      }

      ajaxRequest(action, data, function (response) {
          alert('Node saved successfully.');
          fetchAndRenderNodes();
          $('#node-form')[0].reset();
      });
  });

  // Delete node
  $(document).on('click', '.delete-node', function () {
      const nodeId = $(this).data('id');

      if (confirm('Are you sure you want to delete this node?')) {
          ajaxRequest(
              'delete',
              { node_id: nodeId },
              function () {
                  alert('Node deleted successfully.');
                  fetchAndRenderNodes();
              }
          );
      }
  });

  // Edit node
  $(document).on('click', '.edit-node', function () {
      const nodeId = $(this).data('id');

      ajaxRequest(
          'fetch_single',
          { node_id: nodeId },
          function (response) {
              const node = response.node;

              $('#node-id').val(node.node_id);
              $('#first-name').val(node.first_name);
              $('#last-name').val(node.last_name);
              $('#pledge-class').val(node.pledge_class);
              $('#graduation-year').val(node.graduation_year);
              $('#photo-url').val(node.photo_url);

              $('#founding-member').prop('checked', node.is_founding_member);
          }
      );
  });

  // Assign or reassign parent node
  $('#assign-parent-form').on('submit', function (e) {
      e.preventDefault();

      const data = {
          node_id: $('#node-id-assign').val(),
          parent_node_id: $('#parent-node-id').val(),
      };

      ajaxRequest('assign_parent', data, function (response) {
          alert(response.message);
          fetchAndRenderNodes();
      });
  });

  // Create a new line
  $('#line-form').on('submit', function (e) {
      e.preventDefault();

      const data = {
          line_name: $('#line-name').val(),
          description: $('#description').val(),
      };

      ajaxRequest('create_line', data, function (response) {
          alert(response.message);
      });
  });

  // Advanced filters (multi-select for majors or lines)
  $('#search-input, #filter-pledge-class, #filter-grad-year, #filter-majors, #filter-lines').on('change', function () {
      fetchAndRenderNodes();
  });

  // Initial fetch
  fetchAndRenderNodes();
});
