<?php
/**
 * Plugin Name: Woo Custom Thank You Page
 * Description: Set a global or product-specific custom Thank You page in WooCommerce.
 * Version: 1.0.0
 * Author: Mahfuz Hasan
 * Author URI: https://showrav.com
 * Plugin URI: https://github.com/khmahfuzhasan/woo-custom-thank-you-page
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Custom_Thank_You_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_plugin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'add_product_meta_box']);
        add_action('save_post_product', [$this, 'save_product_thankyou_page']);
        add_action('template_redirect', [$this, 'redirect_thankyou_page']);
    }

    // Add submenu item to WooCommerce settings menu
    public function add_plugin_menu() {
        add_submenu_page(
            'woocommerce',
            'Woo Custom Thank You',
            'Thank You Page',
            'manage_woocommerce',
            'woo-custom-thank-you',
            [$this, 'settings_page']
        );
    }

    // Register plugin settings
    public function register_settings() {
        register_setting('woo_custom_thankyou_settings', 'woo_global_thankyou_redirect_page');
    }

    // Settings page
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Woo Custom Thank You Page</h1>
            <p class="description" style="max-width: 700px; font-size: 14px; color: #555;">
                Set a <strong>Global Thank You Page</strong> that applies to all products unless a specific Thank You Page is selected at the product level. This allows you to have either a unified experience for all customers or a tailored message per product.
            </p>
            <form method="post" action="options.php">
                <?php
                settings_fields('woo_custom_thankyou_settings');
                do_settings_sections('woo_custom_thankyou_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="woo_global_thankyou_redirect_page">
                                Global Thank You Page
                                <span class="dashicons dashicons-editor-help" id="help-tip-global-thankyou" title="This page will be used after checkout unless a product-specific thank you page is defined."></span>
                            </label>
                        </th>
                        <td>
                            <select name="woo_global_thankyou_redirect_page">
                                <option value="">-- Select Page --</option>
                                <?php
                                $selected = get_option('woo_global_thankyou_redirect_page');
                                foreach (get_pages() as $page) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        $page->ID,
                                        selected($selected, $page->ID, false),
                                        esc_html($page->post_title)
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // Add custom meta box to the product page for setting a specific Thank You page
    public function add_product_meta_box() {
        add_meta_box(
            'woo_product_thankyou_page',
            'Thank You Page Redirect',
            [$this, 'render_product_meta_box'],
            'product',
            'side',
            'default'
        );
    }

    // Render product-level meta box for selecting a Thank You page
    public function render_product_meta_box($post) {
        wp_nonce_field('woo_save_product_thankyou', 'woo_thankyou_nonce');
        $selected = get_post_meta($post->ID, '_woo_product_thankyou_page', true);
        echo '<label for="woo_product_thankyou_page">Select a Thank You Page:</label><br />';
        echo '<select name="woo_product_thankyou_page" id="woo_product_thankyou_page">
                <option value="">-- Default (Global) --</option>';
        foreach (get_pages() as $page) {
            printf(
                '<option value="%s" %s>%s</option>',
                $page->ID,
                selected($selected, $page->ID, false),
                esc_html($page->post_title)
            );
        }
        echo '</select>';
    }

    // Save product-specific Thank You page setting
    public function save_product_thankyou_page($post_id) {
        if (!isset($_POST['woo_thankyou_nonce']) || !wp_verify_nonce($_POST['woo_thankyou_nonce'], 'woo_save_product_thankyou')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['woo_product_thankyou_page'])) {
            update_post_meta($post_id, '_woo_product_thankyou_page', sanitize_text_field($_POST['woo_product_thankyou_page']));
        }
    }

    // Redirect to the custom Thank You page after checkout
    public function redirect_thankyou_page() {
        if (!is_order_received_page()) return;

        $order_id = absint(get_query_var('order-received'));
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Check if there's a product-specific Thank You page
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $custom_page_id = get_post_meta($product_id, '_woo_product_thankyou_page', true);
            if ($custom_page_id) {
                wp_redirect(get_permalink($custom_page_id));
                exit;
            }
        }

        // If no product-specific page, use the global Thank You page
        $global_page_id = get_option('woo_global_thankyou_redirect_page');
        if ($global_page_id) {
            wp_redirect(get_permalink($global_page_id));
            exit;
        }
    }
}

// Initialize the plugin class
new Woo_Custom_Thank_You_Page();

// Add settings link on plugin page in /wp-admin/plugins.php
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    // Add the "Settings" link that directs to the plugin's settings page
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=woo-custom-thank-you')) . '">Settings</a>';
    array_unshift($links, $settings_link); // Ensure the link appears first
    return $links;
});

