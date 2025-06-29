<?php
/*
Plugin Name: Brother Tree
Description: A simple plugin for displaying fraternity brotherhood tree information.
Version: 1.0
Author: Jean-Luc Williams
*/

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'brother_tree_activate');
register_deactivation_hook(__FILE__, 'brother_tree_deactivate');

function brother_tree_activate() {
    // Actions to perform on activation
}

function brother_tree_deactivate() {
    // Actions to perform on deactivation
}

// Add admin menu
add_action('admin_menu', 'brother_tree_add_admin_menu');

function brother_tree_add_admin_menu() {
    add_menu_page(
        'Brother Tree', // Page title
        'Brother Tree', // Menu title
        'manage_options', // Capability
        'brother-tree', // Menu slug
        'brother_tree_admin_page', // Function to display page
        'dashicons-networking', // Icon
        6 // Position
    );
}

function brother_tree_admin_page() {
    echo '<div class="wrap"><h1>Brother Tree</h1><p>Welcome to the Brother Tree admin page.</p></div>';
}

?>