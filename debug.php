<?php
/**
 * Debug Helper for Fabric Selector
 * Add to main plugin file temporarily
 */

// Add this to your main plugin file after the class definition
add_action('wp_footer', function() {
    if (!is_product()) return;
    
    global $product;
    ?>
    <script>
    console.log('=== FABRIC SELECTOR DEBUG ===');
    console.log('Product ID:', <?php echo get_the_ID(); ?>);
    console.log('Is Variable:', <?php echo $product && $product->is_type('variable') ? 'true' : 'false'; ?>);
    console.log('Attributes:', <?php echo json_encode($product && $product->is_type('variable') ? $product->get_variation_attributes() : []); ?>);
    </script>
    <?php
}, 999);