<?php
/**
 * Gutenberg-compatible WYSIWYG editor for metaboxes
 * Single initialization path - no competing scripts
 */

class CFM_Gutenberg_WYSIWYG {
    
    private static $editor_count = 0;
    private static $script_added = false;
    
    public function __construct() {
        add_action('admin_footer', array($this, 'add_global_init_script'), 999);
    }
    
    /**
     * Add ONE global script that handles ALL editors
     */
    public function add_global_init_script() {
        if (self::$script_added) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('post', 'post-new'))) {
            return;
        }
        
        self::$script_added = true;
        ?>
        <script type="text/javascript">
        (function($) {
            var CFM_EditorManager = {
                initialized: false,
                editorQueue: [],
                
                init: function() {
                    if (this.initialized) return;
                    
                    var self = this;
                    
                    // Check if we're in Gutenberg
                    var isGutenberg = typeof wp !== 'undefined' && 
                                     wp.data && 
                                     wp.data.select('core/editor');
                    
                    if (isGutenberg) {
                        // Wait for Gutenberg to be fully ready
                        this.waitForGutenberg(function() {
                            self.initAllEditors();
                            self.initialized = true;
                        });
                    } else {
                        // Classic editor - init immediately
                        this.initAllEditors();
                        this.initialized = true;
                    }
                    
                    // Handle Visual/Text tab switching
                    $(document).on('click', '.wp-switch-editor', function() {
                        setTimeout(function() {
                            self.reinitAllEditors();
                        }, 150);
                    });
                    
                    // Handle metabox collapse/expand
                    $(document).on('click', '.postbox .handlediv, .postbox .hndle', function() {
                        var $metabox = $(this).closest('.postbox');
                        setTimeout(function() {
                            if (!$metabox.hasClass('closed')) {
                                self.reinitAllEditors();
                            }
                        }, 350);
                    });
                },
                
                waitForGutenberg: function(callback) {
                    var attempts = 0;
                    var maxAttempts = 20;
                    
                    var checkReady = setInterval(function() {
                        attempts++;
                        
                        var editor = wp.data && wp.data.select('core/editor');
                        var isReady = editor && typeof editor.getEditedPostContent === 'function';
                        
                        if (isReady || attempts >= maxAttempts) {
                            clearInterval(checkReady);
                            // Extra delay to ensure DOM is stable
                            setTimeout(callback, 300);
                        }
                    }, 250);
                },
                
                initAllEditors: function() {
                    var self = this;
                    
                    if (typeof tinymce === 'undefined') {
                        console.warn('TinyMCE not loaded yet');
                        return;
                    }
                    
                    $('.cfm-wysiwyg-wrapper').each(function() {
                        var $wrapper = $(this);
                        var editorId = $wrapper.data('editor-id');
                        
                        if (!editorId) return;
                        
                        self.initSingleEditor(editorId);
                    });
                },
                
                reinitAllEditors: function() {
                    var self = this;
                    
                    $('.cfm-wysiwyg-wrapper').each(function() {
                        var editorId = $(this).data('editor-id');
                        if (editorId) {
                            self.reinitSingleEditor(editorId);
                        }
                    });
                },
                
                initSingleEditor: function(editorId) {
                    if (typeof tinymce === 'undefined') return;
                    
                    // Clean up existing instance
                    var existingEditor = tinymce.get(editorId);
                    if (existingEditor) {
                        try {
                            tinymce.execCommand('mceRemoveEditor', false, editorId);
                        } catch(e) {
                            console.warn('Error removing editor:', e);
                        }
                    }
                    
                    // Add editor
                    setTimeout(function() {
                        try {
                            tinymce.execCommand('mceAddEditor', false, editorId);
                        } catch(e) {
                            console.warn('Error adding editor:', e);
                        }
                    }, 100);
                },
                
                reinitSingleEditor: function(editorId) {
                    if (typeof tinymce === 'undefined') return;
                    
                    var editor = tinymce.get(editorId);
                    
                    // Only reinit if editor exists but isn't working
                    if (editor) {
                        try {
                            tinymce.execCommand('mceRemoveEditor', false, editorId);
                            setTimeout(function() {
                                tinymce.execCommand('mceAddEditor', false, editorId);
                            }, 100);
                        } catch(e) {
                            console.warn('Error reinitializing editor:', e);
                        }
                    } else {
                        // Editor doesn't exist, initialize it
                        this.initSingleEditor(editorId);
                    }
                }
            };
            
            // Initialize on DOM ready
            $(document).ready(function() {
                CFM_EditorManager.init();
            });
            
        })(jQuery);
        </script>
        <?php
    }
    
    public function render_wysiwyg($field, $value, $field_id, $field_name, $options) {
        // Increment counter for unique IDs
        self::$editor_count++;
        
        // Create unique, sanitized editor ID
        $editor_id = sanitize_key($field_id) . '_ed_' . self::$editor_count;
        
        // Ensure editor assets are loaded
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }
        
        $editor_settings = array(
            'textarea_name' => $field_name,
            'textarea_rows' => !empty($options['rows']) ? intval($options['rows']) : 10,
            'editor_height' => !empty($options['height']) ? intval($options['height']) : 300,
            'media_buttons' => !empty($options['media_upload']) ? true : false,
            'editor_class' => 'cfm-wysiwyg-editor',
            'tinymce' => array(
                'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_adv',
                'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                'wp_autoresize_on' => false,
                'relative_urls' => false,
                'remove_script_host' => false,
                'content_css' => false, // Prevent CSS loading issues
            ),
            'quicktags' => true,
            'wpautop' => true,
            'default_editor' => 'tinymce',
        );
        
        echo '<div class="cfm-wysiwyg-wrapper" data-editor-id="' . esc_attr($editor_id) . '">';
        wp_editor($value, $editor_id, $editor_settings);
        echo '</div>';
        
        // NO inline scripts here - all handled by global script
    }
}

// Usage in your existing code:
// Replace your render_wysiwyg function with this class approach
// 
// In your main plugin file or where you initialize:
// $cfm_wysiwyg = new CFM_Gutenberg_WYSIWYG();
// 
// Then when rendering:
// $cfm_wysiwyg->render_wysiwyg($field, $value, $field_id, $field_name, $options);