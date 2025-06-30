<?php
/*
Plugin Name: Brother Tree
Description: A plugin for managing fraternity brotherhood tree data.
Version: 1.0
Author: Jean-Luc Williams
*/

// Activation and deactivation
register_activation_hook(__FILE__, 'brother_tree_activate');
register_uninstall_hook(__FILE__, 'brother_tree_uninstall');

function brother_tree_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = file_get_contents(plugin_dir_path(__FILE__) . 'create-db.sql');
    $queries = array_filter(array_map('trim', explode(";", $sql)));

    foreach ($queries as $query) {
        dbDelta($query . ';');
    }
}

function brother_tree_uninstall() {
    global $wpdb;
    $tables = [
        'wp_fraternity_member_degrees',
        'wp_fraternity_member_minors',
        'wp_fraternity_member_majors',
        'wp_fraternity_member_line',
        'wp_fraternity_lines',
        'wp_fraternity_members'
    ];
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}

// Admin menu and page
add_action('admin_menu', 'brother_tree_add_admin_menu');

function brother_tree_add_admin_menu() {
    add_menu_page(
        'Brother Tree',
        'Brother Tree',
        'manage_options',
        'brother-tree',
        'brother_tree_admin_page',
        'dashicons-networking',
        6
    );
}

function brother_tree_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'fraternity_members';
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'members';
    echo '<style>
        #bt-form-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.3);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #bt-form-modal {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        #bt-big-suggestions {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: absolute;
            width: 200px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 2px;
            z-index: 10001;
        }

        #bt-big-suggestions div {
            padding: 6px 10px;
            border-bottom: 1px solid #f1f1f1;
            cursor: pointer;
        }

        #bt-big-suggestions div:hover {
            background-color: #f0f0f0;
        }

        #bt-big-search {
            width: 100%;
            box-sizing: border-box;
        }
    </style>';
    echo '<div class="wrap"><h1>Brother Tree Management</h1>';
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="?page=brother-tree&tab=members" class="nav-tab ' . ($current_tab == 'members' ? 'nav-tab-active' : '') . '">Brothers</a>';
    echo '<a href="?page=brother-tree&tab=lines" class="nav-tab ' . ($current_tab == 'lines' ? 'nav-tab-active' : '') . '">Lines</a>';
    echo '</nav>';

    if ($current_tab === 'members') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bt_save_brother'])) {
            $data = [
                'first_name' => sanitize_text_field($_POST['first_name']),
                'last_name' => sanitize_text_field($_POST['last_name']),
                'pledge_year' => intval($_POST['pledge_year']),
                'pledge_semester' => sanitize_text_field($_POST['pledge_semester']),
                'grad_year' => intval($_POST['grad_year']),
                'grad_semester' => sanitize_text_field($_POST['grad_semester']),
                'big_brother_id' => $_POST['big_brother_id'] ? intval($_POST['big_brother_id']) : null,
                'photo_url' => esc_url_raw($_POST['photo_url'])
            ];
            if (!empty($_POST['member_id'])) {
                $wpdb->update($table, $data, ['member_id' => intval($_POST['member_id'])]);
            } else {
                $wpdb->insert($table, $data);
            }
        }

        if (isset($_GET['delete'])) {
            $wpdb->delete($table, ['member_id' => intval($_GET['delete'])]);
            echo '<div class="updated"><p>Brother deleted.</p></div>';
        }

        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $pledge_semester = isset($_GET['pledge_semester']) ? sanitize_text_field($_GET['pledge_semester']) : '';
        $pledge_year = isset($_GET['pledge_year']) ? intval($_GET['pledge_year']) : '';
        $grad_semester = isset($_GET['grad_semester']) ? sanitize_text_field($_GET['grad_semester']) : '';
        $grad_year = isset($_GET['grad_year']) ? intval($_GET['grad_year']) : '';

        echo '<form method="get" style="margin: 10px 0;">';
        echo '<input type="hidden" name="page" value="brother-tree">';
        echo '<input type="hidden" name="tab" value="members">';
        echo '<input type="text" name="s" placeholder="Search name..." value="' . esc_attr($search) . '">';
        echo '<label style="margin: 0 4px 0 10px;">Pledge Information:</label>';
        echo '<select name="pledge_semester"><option value="">All Semesters</option>';
        foreach (["Spring","Summer","Fall"] as $sem) {
            echo '<option value="' . $sem . '" ' . selected($pledge_semester, $sem, false) . '>' . $sem . '</option>';
        }
        echo '</select>';
        echo '<input type="text" name="pledge_year" placeholder="Year" value="' . esc_attr($pledge_year) . '">';
        echo '<label style="margin: 0 4px 0 10px;">Graduation Information:</label>';
        echo '<select name="grad_semester"><option value="">All Semesters</option>';
        foreach (["Spring","Summer","Fall"] as $sem) {
            echo '<option value="' . $sem . '" ' . selected($grad_semester, $sem, false) . '>' . $sem . '</option>';
        }
        echo '</select>';
        echo '<input type="text" name="grad_year" placeholder="Year" value="' . esc_attr($grad_year) . '">';
        submit_button('Filter', 'secondary', '', false);
        echo '</form>';

        echo '<button id="bt-add-brother" class="button button-primary">Add Brother</button>';


        $editing = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $adding = isset($_GET['modal']) && $_GET['modal'] === 'add';
        $edit_row = $editing ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE member_id = %d", $editing)) : null;
        $modal_visible = $editing || $adding;
        
        if ($modal_visible): ?>
            <div id="bt-form-overlay">
                <div id="bt-form-modal">
                    <h2><?php echo $editing ? 'Edit Brother' : 'Add New Brother'; ?></h2>
                    <form method="post">
                        <input type="hidden" name="bt_save_brother" value="1">
                        <input type="hidden" name="member_id" value="<?php echo $editing; ?>">
                        <table class="form-table"><tbody>
                            <tr><th>First Name</th><td><input name="first_name" type="text" required value="<?php echo esc_attr($edit_row->first_name ?? ''); ?>"></td></tr>
                            <tr><th>Last Name</th><td><input name="last_name" type="text" required value="<?php echo esc_attr($edit_row->last_name ?? ''); ?>"></td></tr>
                            <tr><th>Pledge Semester</th><td>
                                <select name="pledge_semester">
                                    <?php foreach (["Spring","Summer","Fall"] as $sem): ?>
                                        <option value="<?php echo $sem; ?>" <?php selected($edit_row->pledge_semester ?? '', $sem); ?>><?php echo $sem; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td></tr>
                            <tr><th>Pledge Year</th><td><input name="pledge_year" type="number" required value="<?php echo esc_attr($edit_row->pledge_year ?? ''); ?>"></td></tr>
                            <tr><th>Grad Semester</th><td>
                                <select name="grad_semester">
                                    <option value="">--</option>
                                    <?php foreach (["Spring","Summer","Fall"] as $sem): ?>
                                        <option value="<?php echo $sem; ?>" <?php selected($edit_row->grad_semester ?? '', $sem); ?>><?php echo $sem; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td></tr>
                            <tr><th>Grad Year</th><td><input name="grad_year" type="number" value="<?php echo esc_attr($edit_row->grad_year ?? ''); ?>"></td></tr>
                            <tr><th>Big Brother</th>
                            <td>
                                <input type="text" id="bt-big-search" placeholder="Type name..." autocomplete="off" value="">
                                <input type="hidden" name="big_brother_id" id="bt-big-id" value="<?php echo esc_attr($edit_row->big_brother_id ?? ''); ?>">
                                <div id="bt-big-suggestions" style="position: absolute; background: #fff; border: 1px solid #ccc; display: none; max-height: 200px; overflow-y: auto; z-index: 9999;"></div>
                                <?php if ($edit_row && $edit_row->big_brother_id): ?>
                                    <script>
                                        window.initialBigBrotherLabel = <?php echo json_encode(
                                            $wpdb->get_var($wpdb->prepare("
                                                SELECT CONCAT(first_name, ' ', last_name, ' (Pledge Semester/Year: ', pledge_semester, ' ', pledge_year, 
                                                    IFNULL(CONCAT(' | Grad Semester/Year: ', grad_semester, ' ', grad_year), '') ,')')
                                                FROM {$wpdb->prefix}fraternity_members
                                                WHERE member_id = %d
                                            ", $edit_row->big_brother_id))
                                        ); ?>;
                                    </script>
                                <?php endif; ?>
                            </td></tr>
                            <tr><th>Photo URL</th><td><input name="photo_url" type="url" value="<?php echo esc_url($edit_row->photo_url ?? ''); ?>"></td></tr>
                        </tbody></table>
                        <p class="submit">
                            <button type="submit" class="button-primary">Save Brother</button>
                            <button type="button" id="bt-cancel" class="button">Cancel</button>
                        </p>
                    </form>
                </div>
            </div>
        <?php endif;

        $where = 'WHERE 1=1';
        if ($search) $where .= $wpdb->prepare(" AND (first_name LIKE %s OR last_name LIKE %s)", "%$search%", "%$search%");
        if ($pledge_semester) $where .= $wpdb->prepare(" AND pledge_semester = %s", $pledge_semester);
        if ($pledge_year) $where .= $wpdb->prepare(" AND pledge_year = %d", $pledge_year);
        if ($grad_semester) $where .= $wpdb->prepare(" AND grad_semester = %s", $grad_semester);
        if ($grad_year) $where .= $wpdb->prepare(" AND grad_year = %d", $grad_year);

        $per_page = 10;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
        $pages = ceil($total / $per_page);

        $rows = $wpdb->get_results("SELECT * FROM $table $where ORDER BY pledge_year DESC, pledge_semester DESC LIMIT $offset, $per_page");

        echo '<table class="widefat fixed striped"><thead><tr><th>ID</th><th>Name</th><th>Pledge</th><th>Graduation</th><th>Big</th><th>Photo</th><th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo "<td>{$r->member_id}</td>";
            echo "<td>{$r->first_name} {$r->last_name}</td>";
            echo "<td>{$r->pledge_semester} {$r->pledge_year}</td>";
            echo "<td>" . ($r->grad_year ? "{$r->grad_semester} {$r->grad_year}" : '-') . "</td>";
            echo "<td>" . ($r->big_brother_id ?: '—') . "</td>";
            echo "<td>" . ($r->photo_url ? '<img src="' . esc_url($r->photo_url) . '" width="50"/>' : '—') . "</td>";
            echo '<td>';
            echo '<a href="' . esc_url(add_query_arg(['edit' => $r->member_id])) . '" class="button">Edit</a>';
            echo '<a href="?page=brother-tree&tab=members&delete=' . $r->member_id . '" class="button delete-confirm">Delete</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($i = 1; $i <= $pages; $i++) {
            $url = esc_url(add_query_arg(['paged' => $i]));
            echo '<a class="' . ($i === $paged ? 'current-page' : '') . ' page-numbers" href="' . $url . '">' . $i . '</a> ';
        }
        echo '</div></div>';
    }

    echo '</div>';
    echo '<script>
        document.querySelectorAll(".delete-confirm").forEach(btn => btn.addEventListener("click", e => {
            if (!confirm("Are you sure you want to delete this brother?")) e.preventDefault();
        }));

        const modal = document.getElementById("bt-form-modal");
        const addBtn = document.getElementById("bt-add-brother");
        const cancelBtn = document.getElementById("bt-cancel");

        // Use ?modal=add for a clean new form
        if (addBtn) {
            addBtn.addEventListener("click", () => {
                window.location.href = "?page=brother-tree&tab=members&modal=add";
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener("click", () => {
                window.location.href = "?page=brother-tree&tab=members";
            });
        }

        // Preload Big Brother label on edit
        if (typeof window.initialBigBrotherLabel !== "undefined") {
            document.getElementById("bt-big-search").value = window.initialBigBrotherLabel;
        }

        // Live search for Big Brother
        const input = document.getElementById("bt-big-search");
        const hidden = document.getElementById("bt-big-id");
        const results = document.getElementById("bt-big-suggestions");

        let debounce;
        input.addEventListener("input", () => {
            clearTimeout(debounce);
            const query = input.value.trim();
            if (!query) {
                results.style.display = "none";
                return;
            }
            debounce = setTimeout(() => {
                fetch(ajaxurl + "?action=bt_search_big_brothers&term=" + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        results.innerHTML = "";
                        data.forEach(item => {
                            const div = document.createElement("div");
                            div.textContent = item.label;
                            div.style.padding = "5px";
                            div.style.cursor = "pointer";
                            div.addEventListener("click", () => {
                                input.value = item.label;
                                hidden.value = item.id;
                                results.style.display = "none";
                            });
                            results.appendChild(div);
                        });
                        results.style.display = "block";
                    });
            }, 200);
        });
        </script>';

}

add_action('wp_ajax_bt_search_big_brothers', function () {
    global $wpdb;
    $term = sanitize_text_field($_GET['term'] ?? '');
    $results = [];
    if ($term) {
        $rows = $wpdb->get_results(
            $wpdb->prepare("
                SELECT member_id, first_name, last_name, pledge_year, pledge_semester, grad_year, grad_semester
                FROM {$wpdb->prefix}fraternity_members
                WHERE first_name LIKE %s OR last_name LIKE %s
                ORDER BY last_name ASC LIMIT 10
            ", "%$term%", "%$term%")
        );
        foreach ($rows as $r) {
            $label = "{$r->first_name} {$r->last_name} | Pledged ({$r->pledge_semester} {$r->pledge_year}";
            $label .= $r->grad_year ? " | Graduated {$r->grad_semester} {$r->grad_year}" : '';
            $label .= ")";
            $results[] = ['id' => $r->member_id, 'label' => $label];
        }
    }
    wp_send_json($results);
});


?>
