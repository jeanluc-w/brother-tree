<?php
/*
* Plugin Name: Fraternity Tree Manager
* Description: A simple plugin adding a database and shortcode to create a brother node graph. 
* Version: 1.0
* Author: jeanluc.williams@proton.me
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class FraternityTreeManager {
    public function __construct() {
      register_activation_hook(__FILE__, [$this, 'create_tables']);
      register_uninstall_hook(__FILE__, [__CLASS__, 'delete_tables']);
  
      // Admin page and scripts
      add_action('admin_menu', [$this, 'register_admin_pages']);
      add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
  
      // Frontend scripts
      add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
  
      // Shortcode
      add_shortcode('fraternity_tree', [$this, 'render_tree_shortcode']);
  
      // AJAX actions
      add_action('wp_ajax_manage_nodes', [$this, 'handle_admin_crud']);
      add_action('wp_ajax_nopriv_manage_nodes', [$this, 'handle_admin_crud']);
  
      // REST API for fetching tree data
      add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    // Create database tables
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            "CREATE TABLE {$wpdb->prefix}fraternity_trees (
                tree_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                tree_name VARCHAR(255) NOT NULL,
                tree_description TEXT,
                tree_metadata JSON NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}fraternity_tree_nodes (
                node_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                tree_id BIGINT NOT NULL,
                parent_node_id BIGINT NULL,
                first_name VARCHAR(100) NOT NULL,
                middle_name VARCHAR(100) NULL,
                last_name VARCHAR(100) NOT NULL,
                pledge_class VARCHAR(20) NOT NULL,
                brother_number INT NOT NULL,
                graduation_year INT NULL,
                majors JSON NULL,
                degrees JSON NULL,
                photo_url VARCHAR(2083) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (tree_id) REFERENCES {$wpdb->prefix}fraternity_trees(tree_id) ON DELETE CASCADE,
                FOREIGN KEY (parent_node_id) REFERENCES {$wpdb->prefix}fraternity_tree_nodes(node_id) ON DELETE SET NULL
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}fraternity_lines (
                line_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                line_name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}fraternity_line_members (
                line_member_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                line_id BIGINT NOT NULL,
                node_id BIGINT NOT NULL,
                FOREIGN KEY (line_id) REFERENCES {$wpdb->prefix}fraternity_lines(line_id) ON DELETE CASCADE,
                FOREIGN KEY (node_id) REFERENCES {$wpdb->prefix}fraternity_tree_nodes(node_id) ON DELETE CASCADE
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    // Delete database tables on plugin deletion
    public static function delete_tables() {
        global $wpdb;

        $tables = [
            "{$wpdb->prefix}fraternity_trees",
            "{$wpdb->prefix}fraternity_tree_nodes",
            "{$wpdb->prefix}fraternity_lines",
            "{$wpdb->prefix}fraternity_line_members"
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table;");
        }
    }

    // Register admin pages
    public function register_admin_pages() {
        add_menu_page(
            'Fraternity Tree Manager',
            'Fraternity Manager',
            'manage_options',
            'fraternity-manager',
            [$this, 'admin_page_content'],
            'dashicons-networking',
            25
        );
    }

    // Admin page content
    public function admin_page_content() {
      ?>
      <div class="wrap">
          <h1>Fraternity Tree Manager</h1>
  
          <div id="node-management">
              <h2>Manage Nodes</h2>
              <form id="node-form">
                  <input type="hidden" id="node-id" name="node_id">
                  <label>First Name: <input type="text" id="first-name" name="first_name" required></label>
                  <label>Last Name: <input type="text" id="last-name" name="last_name" required></label>
                  <label>Pledge Class: <input type="text" id="pledge-class" name="pledge_class" required></label>
                  <label>Graduation Year: <input type="number" id="graduation-year" name="graduation_year"></label>
                  <label>Majors (comma-separated): <input type="text" id="majors" name="majors"></label>
                  <label>Photo URL: <input type="url" id="photo-url" name="photo_url"></label>
                  <label>Founding Member: <input type="checkbox" id="founding-member" name="founding_member"></label>
                  <button type="submit" class="button button-primary">Save Node</button>
              </form>
          </div>
  
          <div id="assign-parent">
              <h2>Assign/Reassign Parent Node</h2>
              <form id="assign-parent-form">
                  <label>Node ID: <input type="number" id="node-id-assign" name="node_id" required></label>
                  <label>Parent Node ID: <input type="number" id="parent-node-id" name="parent_node_id" required></label>
                  <button type="submit" class="button button-primary">Assign Parent</button>
              </form>
          </div>
  
          <div id="line-management">
              <h2>Manage Lines</h2>
              <form id="line-form">
                  <label>Line Name: <input type="text" id="line-name" name="line_name" required></label>
                  <label>Description: <textarea id="description" name="description"></textarea></label>
                  <button type="submit" class="button button-primary">Create Line</button>
              </form>
          </div>
  
          <div id="node-list">
              <h2>Node List</h2>
              <input type="text" id="search-input" placeholder="Search nodes...">
              <button id="search-button" class="button">Search</button>
              <table id="nodes-table" class="widefat">
                  <thead>
                      <tr>
                          <th>Name</th>
                          <th>Pledge Class</th>
                          <th>Graduation Year</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody>
                      <!-- Nodes will be dynamically loaded here -->
                  </tbody>
              </table>
          </div>
      </div>
      <?php
  }

    // Export tables as a CSV file
    public function export_tables() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        global $wpdb;

        $tables = [
            "{$wpdb->prefix}fraternity_trees",
            "{$wpdb->prefix}fraternity_tree_nodes",
            "{$wpdb->prefix}fraternity_lines",
            "{$wpdb->prefix}fraternity_line_members"
        ];

        $zip = new ZipArchive();
        $file_name = 'fraternity_data_export.zip';

        $temp_file = tempnam(sys_get_temp_dir(), 'zip');
        $zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($tables as $table) {
            $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);

            $csv_content = '';
            if (!empty($results)) {
                $headers = array_keys($results[0]);
                $csv_content .= implode(',', $headers) . "\n";
                foreach ($results as $row) {
                    $csv_content .= implode(',', array_map(function ($value) {
                        return '"' . str_replace('"', '""', $value) . '"';
                    }, $row)) . "\n";
                }
            }

            $zip->addFromString(basename($table) . '.csv', $csv_content);
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($temp_file));

        readfile($temp_file);
        unlink($temp_file);
        exit;
    }

    // Import ZIP file to restore data
    public function import_tables() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die('File upload failed.');
        }

        $zip = new ZipArchive();
        if ($zip->open($_FILES['import_file']['tmp_name']) !== true) {
            wp_die('Failed to open ZIP file.');
        }

        global $wpdb;
        $temp_dir = sys_get_temp_dir() . '/fraternity_import_' . wp_generate_uuid4();
        mkdir($temp_dir);

        $zip->extractTo($temp_dir);
        $zip->close();

        foreach (glob($temp_dir . '/*.csv') as $file) {
            $table_name = basename($file, '.csv');
            $table_full_name = "{$wpdb->prefix}$table_name";

            if (!$wpdb->get_var("SHOW TABLES LIKE '$table_full_name'")) {
                continue;
            }

            $wpdb->query("TRUNCATE TABLE $table_full_name");

            $csv_data = array_map('str_getcsv', file($file));
            $headers = array_shift($csv_data);

            foreach ($csv_data as $row) {
                $data = array_combine($headers, $row);
                $wpdb->insert($table_full_name, $data);
            }
        }

        array_map('unlink', glob("$temp_dir/*"));
        rmdir($temp_dir);

        wp_redirect(admin_url('admin.php?page=fraternity-manager&import=success'));
        exit;
    }

    // Handle CRUD operations for admin
    public function handle_admin_crud() {
      if (!current_user_can('manage_options')) {
          wp_send_json_error(['message' => 'Unauthorized'], 403);
      }
  
      global $wpdb;
      $action = sanitize_text_field($_POST['crud_action']);
      $data = $_POST['data'];
  
      switch ($action) {
          case 'fetch':
              $query = "SELECT * FROM {$wpdb->prefix}fraternity_tree_nodes WHERE 1=1";
              $params = [];
  
              if (!empty($data['search'])) {
                  $query .= " AND (first_name LIKE %s OR last_name LIKE %s)";
                  $params[] = '%' . $wpdb->esc_like($data['search']) . '%';
                  $params[] = '%' . $wpdb->esc_like($data['search']) . '%';
              }
  
              if (!empty($data['pledge_class'])) {
                  $query .= " AND pledge_class = %s";
                  $params[] = sanitize_text_field($data['pledge_class']);
              }
  
              if (!empty($data['graduation_year'])) {
                  $query .= " AND graduation_year = %d";
                  $params[] = intval($data['graduation_year']);
              }
  
              $nodes = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
              wp_send_json_success(['nodes' => $nodes]);
              break;
  
          case 'fetch_single':
              $node_id = intval($data['node_id']);
              $node = $wpdb->get_row($wpdb->prepare(
                  "SELECT * FROM {$wpdb->prefix}fraternity_tree_nodes WHERE node_id = %d",
                  $node_id
              ), ARRAY_A);
  
              wp_send_json_success(['node' => $node]);
              break;
  
          case 'create':
              if (!empty($data['founding_member'])) {
                  // Create a new tree
                  $wpdb->insert("{$wpdb->prefix}fraternity_trees", [
                      'tree_name' => sanitize_text_field($data['first_name'] . ' ' . $data['last_name']),
                      'tree_description' => 'Automatically created for founding member',
                  ]);
                  $data['tree_id'] = $wpdb->insert_id;
              }
  
              $wpdb->insert("{$wpdb->prefix}fraternity_tree_nodes", [
                  'tree_id' => intval($data['tree_id']),
                  'parent_node_id' => empty($data['parent_node_id']) ? null : intval($data['parent_node_id']),
                  'first_name' => sanitize_text_field($data['first_name']),
                  'last_name' => sanitize_text_field($data['last_name']),
                  'pledge_class' => sanitize_text_field($data['pledge_class']),
                  'brother_number' => intval($data['brother_number']),
                  'graduation_year' => empty($data['graduation_year']) ? null : intval($data['graduation_year']),
                  'majors' => empty($data['majors']) ? null : json_encode($data['majors']),
                  'photo_url' => esc_url_raw($data['photo_url']),
              ]);
              wp_send_json_success(['message' => 'Node created']);
              break;
  
          case 'update':
              $node_id = intval($data['node_id']);
              $wpdb->update("{$wpdb->prefix}fraternity_tree_nodes", [
                  'first_name' => sanitize_text_field($data['first_name']),
                  'last_name' => sanitize_text_field($data['last_name']),
                  'pledge_class' => sanitize_text_field($data['pledge_class']),
                  'brother_number' => intval($data['brother_number']),
                  'graduation_year' => empty($data['graduation_year']) ? null : intval($data['graduation_year']),
                  'majors' => empty($data['majors']) ? null : json_encode($data['majors']),
                  'photo_url' => esc_url_raw($data['photo_url']),
              ], ['node_id' => $node_id]);
  
              wp_send_json_success(['message' => 'Node updated']);
              break;
  
          case 'delete':
              $node_id = intval($data['node_id']);
              $wpdb->delete("{$wpdb->prefix}fraternity_tree_nodes", ['node_id' => $node_id]);
              wp_send_json_success(['message' => 'Node deleted']);
              break;
          case 'assign_parent':
              $node_id = intval($data['node_id']);
              $parent_id = intval($data['parent_node_id']);
    
              $wpdb->update(
                  "{$wpdb->prefix}fraternity_tree_nodes",
                  ['parent_node_id' => $parent_id],
                  ['node_id' => $node_id]
              );
    
              wp_send_json_success(['message' => 'Parent node updated successfully.']);
              break;
    
          case 'create_line':
              $line_name = sanitize_text_field($data['line_name']);
              $description = sanitize_text_field($data['description']);
    
              $wpdb->insert("{$wpdb->prefix}fraternity_lines", [
                  'line_name' => $line_name,
                  'description' => $description,
              ]);
    
              wp_send_json_success(['message' => 'Line created successfully.', 'line_id' => $wpdb->insert_id]);
              break;
  
          default:
              wp_send_json_error(['message' => 'Invalid action'], 400);
              break;
      }
    }

    // Shortcode to render tree
    public function render_tree_shortcode($atts) {
        $atts = shortcode_atts(['tree_id' => 0], $atts, 'fraternity_tree');
        ob_start();
        ?>
        <div id="fraternity-tree-container" data-tree-id="<?php echo esc_attr($atts['tree_id']); ?>"></div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                renderTree('<?php echo esc_attr($atts['tree_id']); ?>');
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function enqueue_scripts() {
      wp_enqueue_script(
        'd3',
        'https://d3js.org/d3.v7.min.js',
        [],
        '7.0.0',
        true
      );
      
      // Enqueue frontend JavaScript
      wp_enqueue_script(
          'fraternity-tree',
          plugins_url('tree.js', __FILE__),
          ['jquery', 'd3'], // Include 'd3' if you're using D3.js from WordPress or a CDN
          null,
          true
      );
  
      // Enqueue frontend CSS
      wp_enqueue_style(
          'fraternity-tree-style',
          plugins_url('tree.css', __FILE__)
      );
    }
  
    public function enqueue_admin_scripts() {
      // Enqueue admin JavaScript
      wp_enqueue_script(
          'fraternity-admin',
          plugins_url('admin.js', __FILE__),
          ['jquery'],
          null,
          true
      );
  
      // Enqueue admin CSS
      wp_enqueue_style(
          'fraternity-admin-style',
          plugins_url('admin.css', __FILE__)
      );
    }

    public function register_rest_routes() {
      register_rest_route('fraternity-tree/v1', '/tree/(?P<id>\d+)', [
          'methods' => 'GET',
          'callback' => [$this, 'get_tree_data'],
          'permission_callback' => '__return_true', // Adjust permissions as needed
      ]);
  }
  
  public function get_tree_data($request) {
      $tree_id = intval($request['id']);
      global $wpdb;
  
      // Fetch nodes
      $nodes = $wpdb->get_results($wpdb->prepare(
          "SELECT node_id AS id, first_name, last_name, pledge_class, graduation_year, majors, parent_node_id FROM {$wpdb->prefix}fraternity_tree_nodes WHERE tree_id = %d",
          $tree_id
      ), ARRAY_A);
  
      // Process nodes and links
      $links = [];
      foreach ($nodes as &$node) {
          $node['details'] = $node; // Include details for popup
          if ($node['parent_node_id']) {
              $links[] = [
                  'source' => $node['parent_node_id'],
                  'target' => $node['id'],
              ];
          }
      }
  
      return [
          'nodes' => $nodes,
          'links' => $links,
      ];
  }
}

// Initialize plugin
new FraternityTreeManager();