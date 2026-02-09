<?php
/**
 * Plugin Name: Custom Attribute Selector for WooCommerce
 * Description: Replace default attribute swatches with an interactive slide-out sidebar
 * Version: 1.0.1
 * License: MIT
 * Author: SDkid
 */

if (!defined('ABSPATH')) exit;

define('FABRIC_SELECTOR_VERSION', '1.0.1');
define('FABRIC_SELECTOR_PATH', plugin_dir_path(__FILE__));
define('FABRIC_SELECTOR_URL', plugin_dir_url(__FILE__));

class Fabric_Selector_Plugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook early to ensure it runs
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Fabric Selector requires WooCommerce!</p></div>';
            });
            return;
        }
        
        // Load everything
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 99);
        add_action('wp_footer', array($this, 'render_fabric_sidebar'), 99);
        add_action('wp_footer', array($this, 'debug_output'), 999);
        
        // Hook into variations display
        add_filter('woocommerce_dropdown_variation_attribute_options_html', array($this, 'modify_fabric_attribute'), 100, 2);
        
        // AJAX
        add_action('wp_ajax_get_fabric_data', array($this, 'ajax_get_fabric_data'));
        add_action('wp_ajax_nopriv_get_fabric_data', array($this, 'ajax_get_fabric_data'));
    }
    
    public function debug_output() {
        if (!is_product()) return;
        
        global $product;
        
        echo '<!-- FABRIC SELECTOR DEBUG START -->';
        echo '<!-- Product ID: ' . get_the_ID() . ' -->';
        echo '<!-- Product Type: ' . ($product ? $product->get_type() : 'null') . ' -->';
        
        if ($product && $product->is_type('variable')) {
            $attributes = $product->get_variation_attributes();
            echo '<!-- Attributes: ' . print_r($attributes, true) . ' -->';
            echo '<!-- Has pa_fabric: ' . (isset($attributes['pa_fabric']) ? 'YES' : 'NO') . ' -->';
        }
        
        echo '<!-- Plugin Path: ' . FABRIC_SELECTOR_PATH . ' -->';
        echo '<!-- Plugin URL: ' . FABRIC_SELECTOR_URL . ' -->';
        echo '<!-- FABRIC SELECTOR DEBUG END -->';
    }
    
    public function enqueue_assets() {
        if (!is_product()) return;
        
        global $product;
        
        // Always enqueue on product pages for testing
        wp_enqueue_style(
            'fabric-selector-css',
            FABRIC_SELECTOR_URL . 'assets/css/fabric-selector.css',
            array(),
            FABRIC_SELECTOR_VERSION
        );
        
        wp_enqueue_script(
            'fabric-selector-js',
            FABRIC_SELECTOR_URL . 'assets/js/fabric-selector.js',
            array('jquery'),
            FABRIC_SELECTOR_VERSION,
            true
        );
        
        wp_localize_script('fabric-selector-js', 'fabricSelectorData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fabric_selector_nonce'),
            'productId' => get_the_ID(),
            'debug' => true
        ));
        
        // Add inline CSS for testing
        wp_add_inline_style('fabric-selector-css', '
            .fabric-selector-test {
                background: #ffffffff !important;
                padding: 20px !important;
                margin: 20px 0 !important;
                border: 1px solid #acacacff !important;
                border-radius: 5px;
            }
        ');
    }
    
    public function modify_fabric_attribute($html, $args) {
        // Debug log
        error_log('FABRIC SELECTOR: modify_fabric_attribute called');
        error_log('Attribute: ' . $args['attribute']);
        
        // Check all possible attribute name variations
        $is_fabric = (
            $args['attribute'] === 'pa_fabric' || 
            $args['attribute'] === 'attribute_pa_fabric' ||
            strpos($args['attribute'], 'fabric') !== false
        );
        
        if (!$is_fabric) {
            return $html;
        }
        
        error_log('FABRIC SELECTOR: Fabric attribute detected! Modifying HTML...');
        
        // Get selected value
        $selected = $args['selected'];
        $selected_name = 'Choose a fabric';
        
        if ($selected) {
            $term = get_term_by('slug', $selected, 'pa_fabric');
            if ($term && !is_wp_error($term)) {
                $selected_name = $term->name;
            }
        } elseif (!empty($args['options'])) {
            $first_option = reset($args['options']);
            $term = get_term_by('slug', $first_option, 'pa_fabric');
            if ($term && !is_wp_error($term)) {
                $selected_name = $term->name;
                $selected = $first_option;
            }
        }
        
        // Create our custom HTML
        ob_start();
        ?>
        <!-- FABRIC SELECTOR INJECTED -->
        <div class="fabric-selector-wrapper fabric-selector-test">
            <p style="color: black; font-weight: bold;">Select Your Fabric</p>
            
            <input type="hidden" 
                   name="<?php echo esc_attr($args['name']); ?>" 
                   id="<?php echo esc_attr($args['id']); ?>" 
                   class="fabric-selector-hidden-input"
                   value="<?php echo esc_attr($selected); ?>"
                   data-attribute_name="attribute_pa_fabric">
            
            <div class="fabric-selector-display">
                <div class="fabric-selector-current">
                    <span class="fabric-label">Selected Fabric:</span>
                    <span class="fabric-name"><?php echo esc_html($selected_name); ?></span>
                </div>
                <button type="button" class="fabric-selector-btn" data-product-id="<?php echo esc_attr($args['product_id'] ?? get_the_ID()); ?>">
                    Change Fabric →
                </button>
            </div>
        </div>
        <?php
        
        $output = ob_get_clean();
        error_log('FABRIC SELECTOR: Custom HTML generated');
        
        return $output;
    }
    
    public function render_fabric_sidebar() {
        if (!is_product()) return;
        
        ?>
        <!-- FABRIC SELECTOR SIDEBAR -->
        <div id="fabric-selector-sidebar" class="fabric-sidebar">
            <div class="fabric-sidebar-overlay"></div>
            
            <div class="fabric-sidebar-content">
                <div class="fabric-sidebar-header">
                    <h3>Select Your Fabric</h3>
                    <button type="button" class="fabric-sidebar-close">✕</button>
                </div>
                
                <div class="fabric-sidebar-body">
                    <div class="fabric-loader">
                        <div class="fabric-spinner"></div>
                        <p>Loading fabrics...</p>
                    </div>
                    
                    <div class="fabric-list"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_get_fabric_data() {
        check_ajax_referer('fabric_selector_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => 'Invalid product ID'));
            return;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error(array('message' => 'Invalid product'));
            return;
        }
        
        $attributes = $product->get_variation_attributes();
        
        if (!isset($attributes['pa_fabric'])) {
            wp_send_json_error(array('message' => 'No fabric attribute found'));
            return;
        }
        
        $fabrics = array();
        
        foreach ($attributes['pa_fabric'] as $fabric_slug) {
            $term = get_term_by('slug', $fabric_slug, 'pa_fabric');
            
            if (!$term || is_wp_error($term)) {
                continue;
            }
            
            // Get term image - try multiple methods
            $thumbnail_id = get_term_meta($term->term_id, 'product_attribute_image', true);
            
            if (!$thumbnail_id) {
                $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            }
            
            $image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'medium') : wc_placeholder_img_src('medium');
            
            $fabrics[] = array(
                'slug' => $term->slug,
                'name' => $term->name,
                'description' => $term->description ?: 'Premium quality fabric',
                'image' => $image_url,
            );
        }
        
        wp_send_json_success(array('fabrics' => $fabrics));
    }
}

// Initialize
add_action('plugins_loaded', function() {
    Fabric_Selector_Plugin::get_instance();

});

