<?php

/**
 * Plugin Name: WPAgent
 * Plugin URI: https://http://localhost:3000
    private $api_endpoint = 'http://localhost:3000;
 * Description: Allows creators to monetize content by sending it to decentralised storage and rewarding them in USDC.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function cm_sanitize_amount($value) {
    $value = floatval($value);
    return ($value >= 0.01) ? $value : 0.01;
}

class ContentMonetizerPlugin
{

    private $plugin_name = 'content-monetizer';
    private $version = '1.0.0';

    public function __construct()
    {
        add_action('init', array($this, 'init'));
    }

    public function init()
    {
        // Hook into WordPress
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_footer', array($this, 'add_monetize_button'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_monetize_content', array($this, 'handle_monetize_request'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    // Add admin menu page
    public function add_admin_menu()
    {
        add_options_page(
            'WPX402 Settings',
            'WPX402',
            'manage_options',
            'content-monetizer',
            array($this, 'admin_page')
        );
    }

    // Hardcoded API endpoint - Point to Next.js, not Express directly
    private $api_endpoint = 'http://localhost:3000/api/send';

    

    // Register plugin settings
    public function register_settings()
    {
        // register_setting('content_monetizer_settings', 'cm_wallet_address');
        // register_setting('content_monetizer_settings', 'cm_amount');
        register_setting('content_monetizer_settings', 'cm_wallet_address', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
        
        register_setting('content_monetizer_settings', 'cm_amount', array(
            'type' => 'number',
            'sanitize_callback' => 'cm_sanitize_amount',
            'default' => '0.01',
        ));
    }

    // Admin settings page
    public function admin_page()
    {
?>
        <div class="wrap">
            <h1>WPAgent Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('content_monetizer_settings');
                do_settings_sections('content_monetizer_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Wallet Address</th>
                        <td>
                            <input type="text" name="cm_wallet_address" value="<?php echo esc_attr(get_option('cm_wallet_address')); ?>" class="regular-text" required />
                            <p class="description">Receive your rewards through wallet address</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Amount</th>
                        <td>
                            <input type="number" min="0.01" step="0.01" name="cm_amount" value="<?php echo esc_attr(get_option('cm_amount','0.01')); ?>" class="regular-text" required />
                            <p class="description">Enter the amount (USDC)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    // Enqueue scripts and styles
    public function enqueue_scripts()
    {
        if (current_user_can('administrator') && !is_admin()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'content-monetizer-js',
                plugin_dir_url(__FILE__) . 'content-monetizer.js',
                array('jquery'),
                $this->version,
                true
            );

            // Localize script for AJAX
            wp_localize_script('content-monetizer-js', 'cm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cm_monetize_nonce'),
                'current_url' => get_permalink(),
                'loading_text' => 'Processing...',
                'success_text' => 'Content monetized successfully!',
                'error_text' => 'Error occurred. Please try again.'
            ));

            // Add inline CSS
            wp_add_inline_style('wp-admin', $this->get_button_css());
        }
    }

    // Add monetize button to frontend (visible only to admins)
    public function add_monetize_button()
    {
        if (current_user_can('administrator') && !is_admin()) {
            $wallet_address = get_option('cm_wallet_address');
            $amount = get_option('cm_wallet_address');
            if (empty($wallet_address)) {
                echo '<div id="cm-setup-notice" style="position: fixed; top: 32px; right: 20px; background: #fff; border: 1px solid #ccc; padding: 10px; z-index: 9999; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                    <p><strong>WPX402:</strong> Please <a href="' . esc_url(admin_url('options-general.php?page=content-monetizer')) . '">set up your wallet address</a> first.</p>
                </div>';
                return;
            }

            if (empty($amount)) {
                echo '<div id="cm-setup-notice" style="position: fixed; top: 32px; right: 20px; background: #fff; border: 1px solid #ccc; padding: 10px; z-index: 9999; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                    <p><strong>WPX402:</strong> Please <a href="' . esc_url(admin_url('options-general.php?page=content-monetizer')) . '">set up your monetization fee</a> first.</p>
                </div>';
                return;
            }

            echo '<div id="cm-monetize-container">
                <button id="cm-monetize-btn" type="button">
                    <span id="cm-btn-text">💰 Monetize</span>
                    <span id="cm-btn-loader" style="display: none;">⏳ Processing...</span>
                </button>
            </div>';
        }
    }

    // Handle AJAX request for monetization
    public function handle_monetize_request()
    {
        // Verify nonce and user permissions
        if (!wp_verify_nonce($_POST['nonce'], 'cm_monetize_nonce') || !current_user_can('administrator')) {
            wp_die('Unauthorized access');
        }

        $current_url = esc_url_raw($_POST['current_url']);
        $wallet_address = get_option('cm_wallet_address');
        $amount = get_option('cm_amount');
        $api_endpoint = $this->api_endpoint;

        // Get the post content
        $post_id = url_to_postid($current_url);
        if ($post_id) {
            $post = get_post($post_id);
            $content = $post->post_content;
            $title = $post->post_title;
        } else {
            // For pages that aren't posts, get the page content
            $content = $this->get_page_content($current_url);
            $title = get_the_title();
        }

        // Prepare data to send to API
        $api_data = array(
            'content' => wp_strip_all_tags($content),
            'wallet_address' => $wallet_address,
            'url' => $current_url,
            'title' => $title,
            'amount' => floatval($amount),
            'timestamp' => current_time('mysql'),
            'site_name' => get_bloginfo('name')
        );

        // Send to REST API
        $response = wp_remote_post($api_endpoint, array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-ContentMonetizer/' . $this->version
            ),
            'body' => json_encode($api_data)
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to connect to API: ' . $response->get_error_message()
            ));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 200 && $response_code < 300) {
            wp_send_json_success(array(
                'message' => 'Content successfully monetized!',
                'api_response' => json_decode($response_body, true)
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'API returned error: ' . $response_code,
                'details' => $response_body
            ));
        }
    }

    // Get page content for non-post pages
    private function get_page_content($url)
    {
        global $wp_query;

        // This is a simplified approach - you might need to enhance this
        // based on your specific needs for different page types
        if (is_page()) {
            global $post;
            return $post->post_content;
        } elseif (is_single()) {
            global $post;
            return $post->post_content;
        } else {
            // For archive pages, category pages, etc.
            return 'Archive or listing page content';
        }
    }

    // CSS for the monetize button
    private function get_button_css()
    {
        return '
        #cm-monetize-container {
            position: fixed;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            z-index: 9999;
        }
        
        #cm-monetize-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            min-width: 120px;
        }
        
        #cm-monetize-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        #cm-monetize-btn:active {
            transform: translateY(0);
        }
        
        #cm-monetize-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        #cm-setup-notice {
            border-left: 4px solid #ffba00;
        }
        ';
    }
}

new ContentMonetizerPlugin();

register_activation_hook(__FILE__, 'cm_activation_redirect');

function cm_activation_redirect()
{
    add_option('cm_activation_redirect', true);
}

add_action('admin_init', 'cm_activation_redirect_handler');

function cm_activation_redirect_handler()
{
    if (get_option('cm_activation_redirect', false)) {
        delete_option('cm_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_redirect(admin_url('options-general.php?page=content-monetizer'));
            exit;
        }
    }
}
?>