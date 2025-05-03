<?php
/**
 * Plugin Name: Woo Custom Thank You Page
 * Description: Customize the WooCommerce Thank You page with a default design or redirect to a selected WordPress page. Includes URL check functionality.
 * Version: 1.0
 * Author: Mahfuz Hasan
 */

if (!defined('ABSPATH')) exit;

class Woo_Custom_Thank_You_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('woocommerce_get_checkout_order_received_url', [$this, 'redirect_thankyou_page'], 10, 2);
        add_action('wp_ajax_check_custom_url', [$this, 'ajax_check_custom_url']);
        add_action('template_redirect', [$this, 'render_default_thankyou']);
    }

    public function add_settings_menu() {
        add_submenu_page(
            'woocommerce',
            'Woo Custom Thank You Page',
            'Custom Thank You',
            'manage_options',
            'woo-custom-thank-you',
            [$this, 'settings_page']
        );
    }

    public function register_settings() {
        register_setting('woo_custom_thankyou_group', 'woo_thankyou_use_default');
        register_setting('woo_custom_thankyou_group', 'woo_thankyou_redirect_page');
        register_setting('woo_custom_thankyou_group', 'woo_thankyou_custom_url');
        register_setting('woo_custom_thankyou_group', 'woo_thankyou_message');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Woo Custom Thank You Page Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('woo_custom_thankyou_group');
                do_settings_sections('woo_custom_thankyou_group');

                $use_default = get_option('woo_thankyou_use_default', 'yes');
                $redirect_page = get_option('woo_thankyou_redirect_page');
                $custom_url = get_option('woo_thankyou_custom_url');
                $custom_message = get_option('woo_thankyou_message', 'Thank you for your purchase!');
                ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Use Default Thank You Page?</th>
                        <td>
                            <input type="checkbox" name="woo_thankyou_use_default" value="yes" <?php checked('yes', $use_default); ?> />
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Select Redirect Page</th>
                        <td>
                            <select name="woo_thankyou_redirect_page">
                                <option value="">-- Select Page --</option>
                                <?php
                                $pages = get_pages();
                                foreach ($pages as $page) {
                                    echo '<option value="' . $page->ID . '" ' . selected($redirect_page, $page->ID, false) . '>' . $page->post_title . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Custom Thank You URL</th>
                        <td>
                            <input type="text" id="woo_thankyou_custom_url" name="woo_thankyou_custom_url" value="<?php echo esc_attr($custom_url); ?>" />
                            <button type="button" id="check_url_btn" class="button">Check URL</button>
                            <p id="url_check_result"></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Custom Message (for Default Page)</th>
                        <td>
                            <textarea name="woo_thankyou_message" rows="4" cols="50"><?php echo esc_textarea($custom_message); ?></textarea>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        document.getElementById('check_url_btn').addEventListener('click', function () {
            var url = document.getElementById('woo_thankyou_custom_url').value;
            var result = document.getElementById('url_check_result');

            result.textContent = 'Checking...';

            fetch(ajaxurl + '?action=check_custom_url&url=' + encodeURIComponent(url))
                .then(res => res.json())
                .then(data => {
                    if (data.exists) {
                        result.style.color = 'red';
                        result.textContent = 'This URL is already used by another page.';
                    } else {
                        result.style.color = 'green';
                        result.textContent = 'URL is available!';
                    }
                });
        });
        </script>
        <?php
    }

    public function ajax_check_custom_url() {
        $url = isset($_GET['url']) ? sanitize_text_field($_GET['url']) : '';
        $exists = false;

        if ($url) {
            $pages = get_pages();
            foreach ($pages as $page) {
                if (urldecode($url) === trim(urldecode(get_page_uri($page)))) {
                    $exists = true;
                    break;
                }
            }
        }

        wp_send_json(['exists' => $exists]);
    }

    public function redirect_thankyou_page($url, $order) {
        if ('yes' === get_option('woo_thankyou_use_default')) {
            $custom_url = trim(get_option('woo_thankyou_custom_url'));
            if (!empty($custom_url)) {
                return home_url('/' . untrailingslashit($custom_url));
            } else {
                return home_url('/woo-custom-thank-you?order=' . $order->get_id());
            }
        } else {
            $page_id = get_option('woo_thankyou_redirect_page');
            if (!empty($page_id)) {
                return get_permalink($page_id);
            }
        }

        return $url;
    }

    public function render_default_thankyou() {
        if (is_page() || is_admin()) return;

        if (isset($_GET['order']) && strpos($_SERVER['REQUEST_URI'], 'woo-custom-thank-you') !== false) {
            $message = get_option('woo_thankyou_message', 'Thank you for your order!');
            wp_head();
            echo '<!DOCTYPE html><html><head><title>Thank You</title></head><body style="text-align:center;padding:80px;font-family:sans-serif;background:#f9f9f9;">';
            echo '<div style="max-width:600px;margin:auto;background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">';
            echo '<h1 style="color:#28a745;">Thank You!</h1>';
            echo '<p style="font-size:18px;">' . esc_html($message) . '</p>';
            echo '<a href="' . home_url() . '" style="display:inline-block;margin-top:20px;padding:10px 20px;background:#0073aa;color:#fff;text-decoration:none;border-radius:5px;">Continue Shopping</a>';
            echo '</div></body></html>';
            wp_footer();
            exit;
        }
    }
}

new Woo_Custom_Thank_You_Page();
