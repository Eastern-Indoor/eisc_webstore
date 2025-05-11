<?php
/**
 * Plugin Name: WooCommerce Stock Manager
 * Description: Adds a Stock menu under WooCommerce to manage product stock quantities inline.
 * Version: 1.0.0
 * Author: Josh Dowling
 * Text Domain: wc-stock-manager
 */

if (! defined('ABSPATH')) {
    exit;
}

// Add submenu page under WooCommerce
add_action('admin_menu', 'wcsm_add_admin_menu');
function wcsm_add_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __('Stock', 'wc-stock-manager'),
        __('Stock', 'wc-stock-manager'),
        'manage_woocommerce',
        'wc-stock-manager',
        'wcsm_stock_manager_page'
    );
}

// Stock manager page callback: outputs table, inline CSS & JS
function wcsm_stock_manager_page() {
    // Fetch all products
    $products = wc_get_products(array('limit' => -1));
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Stock Manager', 'wc-stock-manager'); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Image', 'wc-stock-manager'); ?></th>
                    <th><?php esc_html_e('Product', 'wc-stock-manager'); ?></th>
                    <th><?php esc_html_e('Category', 'wc-stock-manager'); ?></th>
                    <th><?php esc_html_e('SKU', 'wc-stock-manager'); ?></th>
                    <th><?php esc_html_e('Stock', 'wc-stock-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product) :
                    $id         = $product->get_id();
                    $thumb      = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
                    $img_url    = $thumb ? $thumb[0] : wc_placeholder_img_src();
                    $name       = $product->get_name();
                    $cats       = wp_get_post_terms($id, 'product_cat', array('fields' => 'names'));
                    $sku        = $product->get_sku();
                    $stock_qty  = $product->get_stock_quantity();
                ?>
                <tr data-product-id="<?php echo esc_attr($id); ?>">
                    <td><img src="<?php echo esc_attr($img_url); ?>" width="50" alt=""></td>
                    <td><?php echo esc_html($name); ?></td>
                    <td><?php echo esc_html(implode(', ', $cats)); ?></td>
                    <td><?php echo esc_html($sku); ?></td>
                    <td>
                        <button class="wcsm-decrease button">-</button>
                        <input type="number" class="wcsm-stock-input" value="<?php echo esc_attr($stock_qty); ?>" min="0" style="width:60px; text-align:center;">
                        <button class="wcsm-increase button">+</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <style>
    .wcsm-stock-input { margin: 0 5px; }
    </style>
    <script>
    (function($){
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce   = '<?php echo wp_create_nonce('wcsm_nonce'); ?>';

        function updateStock(row, qty) {
            var pid = row.data('product-id');
            $.post(ajaxUrl, {
                action: 'wcsm_update_stock',
                nonce: nonce,
                product_id: pid,
                stock: qty
            }, function(res) {
                if (! res.success) {
                    alert('Error updating stock: ' + res.data);
                }
            });
        }

        $(document).on('click', '.wcsm-increase, .wcsm-decrease', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var input = row.find('.wcsm-stock-input');
            var val = parseInt(input.val(), 10) || 0;
            if ($(this).hasClass('wcsm-increase')) {
                val++;
            } else {
                val = val > 0 ? val - 1 : 0;
            }
            input.val(val);
            updateStock(row, val);
        });

        $(document).on('change', '.wcsm-stock-input', function() {
            var row = $(this).closest('tr');
            var val = parseInt($(this).val(), 10) || 0;
            $(this).val(val);
            updateStock(row, val);
        });
    })(jQuery);
    </script>
    <?php
}

// AJAX handler: update stock
add_action('wp_ajax_wcsm_update_stock', 'wcsm_update_stock');
function wcsm_update_stock() {
    check_ajax_referer('wcsm_nonce', 'nonce');
    if (! current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }
    $pid   = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $product = wc_get_product($pid);
    if (! $product) {
        wp_send_json_error('Invalid product');
    }
    $product->set_manage_stock(true);
    $product->set_stock_quantity($stock);
    $product->save();
    wp_send_json_success(array('stock' => $stock));
}

