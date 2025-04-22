<?php
/*
Plugin Name: Impact Section
Description: Display impact counters with icons in a responsive grid
Version: 1.0
Author: Viva Kasingye
Author URI: https://x.xom/vivakasingye1
*/

if (!defined('ABSPATH')) exit;

class ImpactSectionPlugin {
    private $default_items = array(
        array(
            'title' => 'Young People Reached',
            'content' => 'Young people reached with integrated SRHR information and services',
            'icon' => 'fas fa-users',
            'counter' => '139812'
        ),
        array(
            'title' => 'Underserved Communities',
            'content' => 'Clients from underserved communities reached with SRH information and services',
            'icon' => 'fas fa-users-cog',
            'counter' => '298585'
        ),
        array(
            'title' => 'Maternal Deaths Averted',
            'content' => 'Number of maternal deaths averted',
            'icon' => 'fas fa-baby',
            'counter' => '74581'
        ),
        array(
            'title' => 'Family Planning Services',
            'content' => 'Clients who received Family Planning services',
            'icon' => 'fas fa-pills',
            'counter' => '92761'
        )
    );

    public function __construct() {
        // Register hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('init', array($this, 'register_post_type'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_shortcode('impact_section', array($this, 'shortcode'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta'));
        add_filter('manage_impact_item_posts_columns', array($this, 'custom_columns'));
        add_action('manage_impact_item_posts_custom_column', array($this, 'custom_column_data'), 10, 2);
    }

    public function activate() {
        $this->register_post_type();
        flush_rewrite_rules();
        
        // Create default items if none exist
        if (!get_posts(array('post_type' => 'impact_item'))) {
            foreach ($this->default_items as $item) {
                $post_id = wp_insert_post(array(
                    'post_title' => $item['title'],
                    'post_content' => $item['content'],
                    'post_type' => 'impact_item',
                    'post_status' => 'publish'
                ));
                
                if ($post_id) {
                    update_post_meta($post_id, 'impact_icon', $item['icon']);
                    update_post_meta($post_id, 'impact_counter', $item['counter']);
                }
            }
        }
    }

    public function register_post_type() {
        register_post_type('impact_item',
            array(
                'labels' => array(
                    'name' => __('Impact Items'),
                    'singular_name' => __('Impact Item')
                ),
                'public' => true,
                'has_archive' => false,
                'show_in_menu' => true,
                'menu_icon' => 'dashicons-chart-bar',
                'supports' => array('title', 'editor', 'custom-fields'),
                'show_in_rest' => true,
            )
        );
    }

    public function enqueue_assets() {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css');
        
        wp_enqueue_style('impact-section-style', plugin_dir_url(__FILE__) . 'impact-section.css');
        
        wp_enqueue_script('impact-section-script', plugin_dir_url(__FILE__) . 'impact-section.js', array(), '1.0', true);
    }

    public function admin_assets($hook) {
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            global $post_type;
            if ('impact_item' === $post_type) {
                wp_enqueue_style('impact-section-admin', plugin_dir_url(__FILE__) . 'impact-section-admin.css');
                wp_enqueue_script('impact-section-admin', plugin_dir_url(__FILE__) . 'impact-section-admin.js', array('jquery'), '1.0', true);
            }
        }
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Our Impact'
        ), $atts);
        
        $impact_items = get_posts(array(
            'post_type' => 'impact_item',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        if (empty($impact_items)) {
            return '<p>No impact items found. Please add some in the admin area.</p>';
        }
        
        ob_start();
        ?>
        <section class="impact-section">
            <div class="impact-title"><?php echo esc_html($atts['title']); ?></div>
            <div class="row g-4">
                <?php foreach ($impact_items as $item): 
                    $icon = get_post_meta($item->ID, 'impact_icon', true);
                    $counter = get_post_meta($item->ID, 'impact_counter', true);
                ?>
                <div class="col-md-3 col-sm-6">
                    <div class="counter-item">
                        <?php if ($icon): ?>
                            <i class="<?php echo esc_attr($icon); ?> icon"></i>
                        <?php endif; ?>
                        <div><?php echo wp_kses_post($item->post_content); ?></div>
                        <?php if ($counter): ?>
                            <div class="counter-box" data-target="<?php echo esc_attr($counter); ?>">0</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    public function add_meta_box() {
        add_meta_box(
            'impact_item_details',
            'Impact Item Details',
            array($this, 'meta_box_callback'),
            'impact_item',
            'normal',
            'high'
        );
    }

    public function meta_box_callback($post) {
        wp_nonce_field('impact_section_save_meta', 'impact_section_meta_nonce');
        
        $icon = get_post_meta($post->ID, 'impact_icon', true);
        $counter = get_post_meta($post->ID, 'impact_counter', true);
        ?>
        <div class="impact-meta-box">
            <div class="form-group">
                <label for="impact_icon">Font Awesome Icon Class:</label>
                <input type="text" id="impact_icon" name="impact_icon" value="<?php echo esc_attr($icon); ?>" class="widefat">
                <p class="description">Enter Font Awesome icon class (e.g. "fas fa-users"). See <a href="https://fontawesome.com/icons" target="_blank">available icons</a>.</p>
            </div>
            
            <div class="form-group">
                <label for="impact_counter">Counter Value:</label>
                <input type="number" id="impact_counter" name="impact_counter" value="<?php echo esc_attr($counter); ?>" class="widefat">
                <p class="description">The target number for the counter animation.</p>
            </div>
            
            <div class="icon-preview" <?php if (!$icon) echo 'style="display:none;"'; ?>>
                <p>Icon Preview:</p>
                <i class="<?php echo esc_attr($icon ? $icon : 'fas fa-question'); ?> icon-preview-icon"></i>
            </div>
        </div>
        <?php
    }

    public function save_meta($post_id) {
        if (!isset($_POST['impact_section_meta_nonce']) || 
            !wp_verify_nonce($_POST['impact_section_meta_nonce'], 'impact_section_save_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['impact_icon'])) {
            update_post_meta($post_id, 'impact_icon', sanitize_text_field($_POST['impact_icon']));
        }
        
        if (isset($_POST['impact_counter'])) {
            update_post_meta($post_id, 'impact_counter', sanitize_text_field($_POST['impact_counter']));
        }
    }

    public function custom_columns($columns) {
        $new_columns = array(
            'cb' => $columns['cb'],
            'title' => $columns['title'],
            'icon' => __('Icon'),
            'counter' => __('Counter Value'),
            'date' => $columns['date']
        );
        return $new_columns;
    }

    public function custom_column_data($column, $post_id) {
        switch ($column) {
            case 'icon':
                $icon = get_post_meta($post_id, 'impact_icon', true);
                if ($icon) {
                    echo '<i class="' . esc_attr($icon) . '"></i> ' . esc_html($icon);
                }
                break;
            case 'counter':
                echo esc_html(get_post_meta($post_id, 'impact_counter', true));
                break;
        }
    }
}

new ImpactSectionPlugin();

// CSS and JS are included as files in the same directory
// Create impact-section.css file
function impact_section_create_css_file() {
    $css_file = plugin_dir_path(__FILE__) . 'impact-section.css';
    if (!file_exists($css_file)) {
        $css_content = '.impact-section {
    text-align: center;
    border-radius: 10px;
    width: 100%;
    margin: 30px 0;
}

.impact-title {
    font-size: 2.5em;
    color: #31bdee;
    margin-bottom: 40px;
    font-weight: bold;
}

.counter-item {
    font-size: 17px;
    color: #555;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 20px;
    height: 100%;
}

.counter-box {
    font-size: 3em;
    font-weight: 900;
    color: #d9534f;
    margin-top: 10px;
}

.icon {
    font-size: 4em;
    color: #31bdee;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .counter-item {
        width: 100%;
        margin-bottom: 30px;
    }
    
    .impact-title {
        font-size: 2em;
    }
}';
        file_put_contents($css_file, $css_content);
    }
}

// Create admin CSS file
function impact_section_create_admin_css_file() {
    $css_file = plugin_dir_path(__FILE__) . 'impact-section-admin.css';
    if (!file_exists($css_file)) {
        $css_content = '.impact-meta-box .form-group {
    margin-bottom: 20px;
}

.impact-meta-box label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.impact-meta-box input[type="text"],
.impact-meta-box input[type="number"] {
    width: 100%;
    padding: 8px;
}

.impact-meta-box .description {
    color: #666;
    font-size: 13px;
    margin-top: 5px;
}

.icon-preview {
    margin-top: 20px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 4px;
}

.icon-preview p {
    margin: 0 0 10px 0;
    font-weight: 600;
}

.icon-preview-icon {
    font-size: 40px;
    color: #31bdee;
}

.post-type-impact_item .column-icon,
.post-type-impact_item .column-counter {
    width: 20%;
}';
        file_put_contents($css_file, $css_content);
    }
}

// Create JS file
function impact_section_create_js_file() {
    $js_file = plugin_dir_path(__FILE__) . 'impact-section.js';
    if (!file_exists($js_file)) {
        $js_content = 'document.addEventListener("DOMContentLoaded", function() {
    const counters = document.querySelectorAll(".counter-box");
    if (!counters.length) return;

    const speed = 200;

    const animateCounter = (counter) => {
        const target = +counter.getAttribute("data-target");
        let count = 0;
        const increment = target / speed;

        const updateCounter = () => {
            count += increment;
            if (count < target) {
                counter.textContent = Math.ceil(count);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        };
        updateCounter();
    };

    const options = {
        root: null,
        threshold: 0.5
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, options);

    counters.forEach(counter => {
        observer.observe(counter);
    });
});';
        file_put_contents($js_file, $js_content);
    }
}

// Create admin JS file
function impact_section_create_admin_js_file() {
    $js_file = plugin_dir_path(__FILE__) . 'impact-section-admin.js';
    if (!file_exists($js_file)) {
        $js_content = 'jQuery(document).ready(function($) {
    // Update icon preview when icon input changes
    $("#impact_icon").on("input", function() {
        const iconClass = $(this).val();
        $(".icon-preview-icon").attr("class", iconClass + " icon-preview-icon");
        
        if (iconClass) {
            $(".icon-preview").show();
        } else {
            $(".icon-preview").hide();
        }
    });
});';
        file_put_contents($js_file, $js_content);
    }
}

// Create necessary files on plugin activation
register_activation_hook(__FILE__, 'impact_section_create_css_file');
register_activation_hook(__FILE__, 'impact_section_create_admin_css_file');
register_activation_hook(__FILE__, 'impact_section_create_js_file');
register_activation_hook(__FILE__, 'impact_section_create_admin_js_file');