(function($) {
    'use strict';
    
    console.log('🔵 Fabric Selector JS Loading...');
    
    // Test jQuery
    if (typeof $ === 'undefined') {
        console.error('❌ jQuery not loaded!');
        return;
    }
    
    console.log('✅ jQuery loaded');
    console.log('📦 Fabric Selector Data:', typeof fabricSelectorData !== 'undefined' ? fabricSelectorData : 'NOT FOUND');
    
    class FabricSelector {
        constructor() {
            console.log('🟢 FabricSelector Constructor Called');
            
            this.sidebar = null;
            this.list = null;
            this.loader = null;
            this.fabrics = [];
            
            this.init();
        }
        
        init() {
            console.log('🟢 FabricSelector Init');
            
            // Wait for DOM
            const self = this;
            $(document).ready(function() {
                self.cacheDom();
                self.bindEvents();
                self.testElements();
            });
        }
        
        cacheDom() {
            console.log('🔍 Caching DOM elements...');
            
            this.sidebar = $('#fabric-selector-sidebar');
            this.list = $('.fabric-list');
            this.loader = $('.fabric-loader');
            
            console.log('Sidebar found:', this.sidebar.length);
            console.log('List found:', this.list.length);
            console.log('Loader found:', this.loader.length);
        }
        
        testElements() {
            console.log('🧪 Testing Elements:');
            console.log('- Wrapper:', $('.fabric-selector-wrapper').length);
            console.log('- Button:', $('.fabric-selector-btn').length);
            console.log('- Hidden Input:', $('.fabric-selector-hidden-input').length);
            console.log('- Sidebar:', $('#fabric-selector-sidebar').length);
            
            // Log button details
            $('.fabric-selector-btn').each(function(i) {
                console.log(`Button ${i}:`, {
                    text: $(this).text(),
                    productId: $(this).data('product-id'),
                    visible: $(this).is(':visible')
                });
            });
        }
        
        bindEvents() {
            const self = this;
            
            console.log('🔗 Binding Events...');
            
            // Test click
            $(document).on('click', '.fabric-selector-btn', function(e) {
                e.preventDefault();
                console.log('🎯 BUTTON CLICKED!');
                console.log('Product ID:', $(this).data('product-id'));
                
                self.openSidebar($(this).data('product-id'));
            });
            
            // Close events
            $(document).on('click', '.fabric-sidebar-overlay, .fabric-sidebar-close', function(e) {
                e.preventDefault();
                console.log('❌ Close clicked');
                self.closeSidebar();
            });
            
            // Fabric selection
            $(document).on('click', '.fabric-item', function() {
                const slug = $(this).data('slug');
                const name = $(this).data('name');
                console.log('👗 Fabric selected:', name, slug);
                self.selectFabric(slug, name);
            });
            
            console.log('✅ Events bound');
        }
        
        openSidebar(productId) {
            console.log('📂 Opening sidebar for product:', productId);
            
            $('body').css('overflow', 'hidden');
            this.sidebar.addClass('active');
            
            if (this.fabrics.length === 0) {
                this.loadFabrics(productId);
            }
        }
        
        closeSidebar() {
            console.log('📁 Closing sidebar');
            $('body').css('overflow', '');
            this.sidebar.removeClass('active');
        }
        
        loadFabrics(productId) {
            const self = this;
            
            console.log('⬇️ Loading fabrics via AJAX...');
            console.log('Ajax URL:', fabricSelectorData.ajaxUrl);
            console.log('Product ID:', productId);
            
            this.loader.show();
            this.list.hide();
            
            $.ajax({
                url: fabricSelectorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_fabric_data',
                    nonce: fabricSelectorData.nonce,
                    product_id: productId || fabricSelectorData.productId
                },
                success: function(response) {
                    console.log('✅ AJAX Success:', response);
                    
                    if (response.success) {
                        self.fabrics = response.data.fabrics;
                        self.renderFabrics();
                    } else {
                        console.error('❌ AJAX Error:', response.data);
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX Failed:', status, error);
                    console.error('Response:', xhr.responseText);
                    self.showError('Failed to load fabrics');
                }
            });
        }
        
        renderFabrics() {
            console.log('🎨 Rendering fabrics:', this.fabrics.length);
            
            let html = '';
            
            this.fabrics.forEach(fabric => {
                html += `
                    <div class="fabric-item" data-slug="${fabric.slug}" data-name="${fabric.name}">
                        <div class="fabric-image">
                            <img src="${fabric.image}" alt="${fabric.name}">
                        </div>
                        <div class="fabric-info">
                            <h4>${fabric.name}</h4>
                            <p>${fabric.description}</p>
                        </div>
                    </div>
                `;
            });
            
            this.list.html(html);
            this.loader.hide();
            this.list.addClass('loaded').show();
            
            console.log('✅ Fabrics rendered');
        }
        
        selectFabric(slug, name) {
            console.log('✨ Selecting fabric:', name, slug);
            
            // Update hidden input
            const $input = $('.fabric-selector-hidden-input');
            $input.val(slug);
            console.log('Input updated:', $input.val());
            
            // Update display
            $('.fabric-name').text(name);
            
            // Update selected state
            this.list.find('.fabric-item').removeClass('selected');
            this.list.find(`.fabric-item[data-slug="${slug}"]`).addClass('selected');
            
            // Try to trigger variation change
            this.triggerVariationChange(slug);
            
            // Close sidebar
            setTimeout(() => this.closeSidebar(), 300);
        }
        
        triggerVariationChange(slug) {
            console.log('🔄 Triggering variation change for:', slug);
            
            const $input = $('.fabric-selector-hidden-input');
            const $form = $('form.variations_form');
            
            console.log('Form found:', $form.length);
            console.log('Input:', $input.attr('name'), '=', $input.val());
            
            // Trigger multiple events
            $input.trigger('change');
            $form.trigger('check_variations');
            $form.trigger('woocommerce_variation_select_change');
            
            console.log('✅ Variation events triggered');
        }
        
        showError(message) {
            this.loader.html(`
                <div style="text-align: center; padding: 40px;">
                    <p style="color: red;">${message}</p>
                </div>
            `);
        }
    }
    
    // Initialize
    $(document).ready(function() {
        console.log('📄 Document Ready');
        console.log('Wrapper exists:', $('.fabric-selector-wrapper').length > 0);
        
        if ($('.fabric-selector-wrapper').length > 0) {
            console.log('🚀 Initializing Fabric Selector...');
            window.fabricSelector = new FabricSelector();
        } else {
            console.warn('⚠️ No fabric selector wrapper found on this page');
        }
    });
    
    console.log('✅ Fabric Selector JS Loaded');
    
})(jQuery);