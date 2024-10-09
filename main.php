<?php
/*
* Plugin Name: Brother Tree Shortcode
* Description: A simple plugin adding a shortcode to create a page displaying a brother node graph. 
* Version: 1.0
* Author: jeanluc.williams@proton.me
*/

function brother_tree_shortcode_func() {
?>
<style>
 .testdiv {
     border: 1px solid black;
     width: 300px;
     height: 300px;
 }
</style>

<div class="testdiv"></div>
<?php
}
add_shortcode('display-brother-tree','brother_tree_shortcode_func');
?>