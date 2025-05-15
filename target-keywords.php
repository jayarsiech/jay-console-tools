<?php
/*
Plugin Name: jay console tools
Description:تعریف کلیدواژه برای مقالاتو محصولات، مشاهده انکرتسکت های سایت، آمار کلیک های سایت
Version: 2.1
Author: jayarsiech
Author URI: https://instagram.com/jayarsiech
License: GPLv2 or later
Text Domain: jay-console-tools

*/
require_once plugin_dir_path(__FILE__) . 'includes/click-tracking-db.php';
register_activation_hook(__FILE__, 'tk_create_clicks_table');

require_once plugin_dir_path(__FILE__) . 'includes/click-tracking-endpoint.php';
require_once plugin_dir_path(__FILE__) . 'includes/jdf.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax/anchor-load-more.php';


// افزودن متاباکس به نوشته‌ها
function tk_add_keywords_metabox() {
    $screens = ['post', 'product']; // ← حالا هم برای نوشته، هم محصول

    foreach ($screens as $screen) {
        add_meta_box(
            'tk_keywords_box',
            '🎯 کلمات کلیدی هدف',
            'tk_render_metabox',
            $screen,
            'side',
            'high'
        );
    }
} 

add_action('add_meta_boxes', 'tk_add_keywords_metabox');

// افزودن فیلد به دسته‌ها
add_action('category_add_form_fields', 'tk_add_term_keywords_field');
add_action('category_edit_form_fields', 'tk_edit_term_keywords_field');
add_action('created_category', 'tk_save_term_keywords');
add_action('edited_category', 'tk_save_term_keywords');


function tk_render_metabox($post) {
    $keywords = get_post_meta($post->ID, '_tk_keywords', true);
    wp_nonce_field('tk_save_keywords', 'tk_keywords_nonce');
    $all_keywords = tk_get_all_keywords_except($post->ID);

    echo '<input name="tk_keywords" id="tk_keywords_input" value="' . esc_attr($keywords) . '">';
   // نمایش پست‌های مرتبط
$related_posts = tk_get_related_posts_by_keywords($post->ID);
if (!empty($related_posts)) {
echo '<div style="margin-top:15px;"><strong>🔗 پست‌های مرتبط برای لینک‌سازی:</strong><ul>';
foreach ($related_posts as $related) {
    $permalink = esc_url(get_permalink($related->ID));
    $title = esc_html($related->post_title);

    echo '<li><span class="tk-copy-related" data-link="' . $permalink . '" style="cursor:pointer; color:#2271b1; text-decoration:underline;">' . $title . '</span></li>';
}
echo '</ul><div id="tk-copy-feedback" style="color:green; margin-top:5px; display:none;">✅ لینک کپی شد</div></div>';

}
// بررسی کلمات تکراری
if (!empty($all_keywords)) {
    echo '<div id="tk-duplicate-warning" style="margin-top: 10px; color: #cc0000; font-weight:bold;"></div>';
    
}
echo '<script>window.tk_existing_keywords = ' . json_encode($all_keywords) . ';</script>';
 ?>


    <p>هر کلمه رو با Enter اضافه کن (مثلاً: آموزش وردپرس ↵ سئو داخلی ↵)</p>
    <?php
}
function tk_clear_anchor_cache_on_save($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!in_array(get_post_type($post_id), ['post', 'product'])) return;

    delete_transient('jay_anchor_analysis_data');
}
add_action('save_post', 'tk_clear_anchor_cache_on_save');

// پست های مرتبط
function tk_get_related_posts_by_keywords($current_post_id) {
    $current_keywords = get_post_meta($current_post_id, '_tk_keywords', true);
    $current_array = json_decode($current_keywords, true);

    if (!is_array($current_array) || empty($current_array)) return [];

    $search_keywords = array_map(function($item) {
        return sanitize_text_field($item['value']);
    }, $current_array);

    // جستجوی پست‌هایی که حداقل یکی از این کلمات رو دارن
    $meta_query = ['relation' => 'OR'];
    foreach ($search_keywords as $kw) {
        $meta_query[] = [
            'key' => '_tk_keywords',
            'value' => $kw,
            'compare' => 'LIKE'
        ];
    }

    $args = [
        'post_type' => ['post', 'product'],
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'post__not_in' => [$current_post_id],
        'meta_query' => $meta_query
    ];

    return get_posts($args);
}

// ذخیره کردن کلیدواژه‌ها
function tk_save_keywords($post_id) {
    if (!isset($_POST['tk_keywords_nonce']) || !wp_verify_nonce($_POST['tk_keywords_nonce'], 'tk_save_keywords')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $keywords = sanitize_text_field($_POST['tk_keywords']);
    update_post_meta($post_id, '_tk_keywords', $keywords);
}
add_action('save_post', 'tk_save_keywords');

// اضافه کردن آیتم منوی مدیریت
function tk_add_admin_menu() {
    add_menu_page(
        'کلمات کلیدی هدف',
        'جی کنسول
        <br><br>',
        'manage_options',
        'jay_keywords_list',
        'tk_render_admin_page',
        plugin_dir_url(__FILE__) . 'assets/jayconsoletools32.png', 
        26
    );
    add_submenu_page(
    'jay_keywords_list',
    'تحلیل انکر تکست‌ها',
    ' انکر تکست‌ها',
    'manage_options',
    'jay_anchor_analysis',
    'tk_render_anchor_analysis_page'
);
add_submenu_page(
    'jay_keywords_list', // زیرمنوی صفحه اصلی افزونه
    'آمار کلیک‌ها',
    '📊 آمار کلیک‌ها',
    'manage_options',
    'jay_click_stats',
    'tk_render_click_stats_page'
);
add_submenu_page(
    null,
    'جزئیات کلیک‌ها',
    'جزئیات کلیک‌ها',
    'manage_options',
    'jay-click-details',
    function () {
        include plugin_dir_path(__FILE__) . 'admin/jay-click-details.php';
    }
);
}
add_action('admin_menu', 'tk_add_admin_menu');
// تغییر عنوان زیرمنوی اول بدون تکرار منوی اصلی
add_action('admin_menu', function() {
    global $submenu;
    if (isset($submenu['jay_keywords_list'][0])) {
        $submenu['jay_keywords_list'][0][0] = 'کلمات کلیدی مطالب';
    }
}, 999);

function tk_render_click_stats_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/click-stats-page.php';
}


// تنظیمات افزونه: افزودن زیرمنو
function tk_add_click_settings_submenu() {
    add_submenu_page(
        'jay_keywords_list',
        'تنظیمات کلیک',
        '🎯 تنظیمات کلیک',
        'manage_options',
        'tk_click_settings',
        'tk_render_click_settings_page'
    );
}
add_action('admin_menu', 'tk_add_click_settings_submenu');

function tk_render_click_settings_page() {
    if (isset($_POST['tk_click_storage'])) {
        update_option('tk_click_storage_method', sanitize_text_field($_POST['tk_click_storage']));
        echo '<div class="notice notice-success"><p>✅ ذخیره شد.</p></div>';
    }

    $current = get_option('tk_click_storage_method', 'session'); // پیش‌فرض session

    ?>
    <div class="wrap">
        <h1>⚙️ تنظیمات ذخیره‌سازی کلیک</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">نوع ذخیره‌سازی کلیک‌ها</th>
                    <td>
                        <label><input type="radio" name="tk_click_storage" value="session" <?php checked($current, 'session'); ?>> فقط در هر نشست (sessionStorage)</label><br>
                        <label><input type="radio" name="tk_click_storage" value="local" <?php checked($current, 'local'); ?>> یکبار در هر روز از یک لینک(localStorage)</label>
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary">💾 ذخیره</button></p>
        </form>
    </div>
    <?php
}


// تحلیل
function tk_render_anchor_analysis_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/anchor-analysis-page.php';
}


// لود کردن فایل صفحه مدیریت
function tk_render_admin_page() {
    require_once plugin_dir_path(__FILE__) . 'admin/keywords-list-page.php';
}

// بررسی کلمات تکراری
function tk_get_all_keywords_except($exclude_post_id) {
    $args = [
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'post__not_in' => [$exclude_post_id],
        'meta_query' => [
            [
                'key' => '_tk_keywords',
                'compare' => 'EXISTS'
            ]
        ]
    ];

    $posts = get_posts($args);
    $keywords = [];

    foreach ($posts as $post) {
        $meta = get_post_meta($post->ID, '_tk_keywords', true);
        $data = json_decode($meta, true);
        if (is_array($data)) {
            foreach ($data as $item) {
                $val = strtolower(trim($item['value']));
                if (!empty($val)) {
                    $keywords[] = $val;
                }
            }
        }
    }

    return array_unique($keywords);
}

// برای دسته بندی ها
function tk_add_term_keywords_field($taxonomy) {
    ?>
    <div class="form-field term-group">
        <label for="tk_keywords"><?php _e('🎯 کلمات کلیدی هدف', 'tk'); ?></label>
        <input type="text" name="tk_keywords" id="tk_keywords_input_term">
        <p class="description">هر کلمه را با Enter جدا کن</p>
    </div>
    <?php
    echo '<script>document.addEventListener("DOMContentLoaded",function(){new Tagify(document.querySelector("#tk_keywords_input_term"));});</script>';
}

function tk_edit_term_keywords_field($term) {
    $meta = get_term_meta($term->term_id, '_tk_keywords', true);
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="tk_keywords"><?php _e('🎯 کلمات کلیدی هدف', 'tk'); ?></label></th>
        <td>
            <input type="text" name="tk_keywords" id="tk_keywords_input_term" value="<?php echo esc_attr($meta); ?>">
            <p class="description">هر کلمه را با Enter جدا کن</p>
        </td>
    </tr>
    <?php
    echo '<script>document.addEventListener("DOMContentLoaded",function(){new Tagify(document.querySelector("#tk_keywords_input_term"));});</script>';
}

function tk_save_term_keywords($term_id) {
    if (isset($_POST['tk_keywords'])) {
        // حذف sanitize_text_field چون JSON رو خراب می‌کنه
        update_term_meta($term_id, '_tk_keywords', wp_unslash($_POST['tk_keywords']));
    }
}



// افزودن ستون به post و product
function tk_add_custom_column($columns) {
    $columns['tk_keywords'] = '🎯 کلمات کلیدی هدف';
    return $columns;
}
add_filter('manage_post_posts_columns', 'tk_add_custom_column');
add_filter('manage_product_posts_columns', 'tk_add_custom_column');

// مقداردهی به ستون
function tk_render_custom_column($column, $post_id) {
    if ($column == 'tk_keywords') {
        $meta = get_post_meta($post_id, '_tk_keywords', true);
        $decoded = json_decode($meta, true);

        if (is_array($decoded) && !empty($decoded)) {
            foreach ($decoded as $item) {
                echo '<span style="background:#f3f3f3; border:1px solid #ccc; border-radius:4px; padding:2px 6px; margin:1px; display:inline-block;">' . esc_html($item['value']) . '</span>';
            }
        } else {
            echo '<span style="color:red;">🔴 بدون کلیدواژه</span>';
        }
    }
}
add_action('manage_post_posts_custom_column', 'tk_render_custom_column', 10, 2);
add_action('manage_product_posts_custom_column', 'tk_render_custom_column', 10, 2);


// فایل ها
function tk_enqueue_metabox_scripts($hook) {
    $screen = get_current_screen();
    $base = plugin_dir_path(__FILE__);
    $url  = plugin_dir_url(__FILE__);

    // شرط برای پست‌ها و محصولات
    $is_post_edit = ($hook === 'post.php' || $hook === 'post-new.php');

    // شرط برای دسته‌بندی‌ها
    $is_category_edit = (
        ($hook === 'term.php' || $hook === 'edit-tags.php') &&
        isset($screen->taxonomy) &&
        $screen->taxonomy === 'category'
    );

    if ($is_post_edit || $is_category_edit) {
        wp_enqueue_script('tagify-js', 'https://cdn.jsdelivr.net/npm/@yaireo/tagify', [], null, true);
        wp_enqueue_style('tagify-css', 'https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css');

        wp_enqueue_script(
            'tk-metabox',
            $url . 'js/metabox.js',
            ['tagify-js'],
            filemtime($base . 'js/metabox.js'),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'tk_enqueue_metabox_scripts');


function tk_enqueue_admin_page_scripts($hook) {
    if ($hook !== 'toplevel_page_jay_keywords_list') return;

    $base = plugin_dir_path(__FILE__);
    $url  = plugin_dir_url(__FILE__);

    wp_enqueue_script(
        'tk-admin-list',
        $url . 'js/admin-list.js',
        [],
        filemtime($base . 'js/admin-list.js'),
        true
    );
    wp_enqueue_style('tk-anchor-css', $url . 'css/anchor-analysis.css', [], filemtime($base . 'css/anchor-analysis.css'));

}

add_action('admin_enqueue_scripts', 'tk_enqueue_admin_page_scripts');

//فایل ها
function tk_enqueue_anchor_analysis_assets($hook) {
    if (strpos($hook, 'jay_anchor_analysis') !== false) {
        $base = plugin_dir_path(__FILE__);
        $url  = plugin_dir_url(__FILE__);

        wp_enqueue_script('tk-anchor-js', $url . 'js/anchor-analysis.js', [], filemtime($base . 'js/anchor-analysis.js'), true);
        wp_enqueue_style('tk-anchor-css', $url . 'css/anchor-analysis.css', [], filemtime($base . 'css/anchor-analysis.css'));
    }
}
add_action('admin_enqueue_scripts', 'tk_enqueue_anchor_analysis_assets');

//amar
function tk_enqueue_click_analysis_assets() {
   
        $base = plugin_dir_path(__FILE__);
        $url  = plugin_dir_url(__FILE__);

        wp_enqueue_script('tk-anchor-js', $url . 'js/amarclick_admin.js', [], filemtime($base . 'js/amarclick_admin.js'), true);
        wp_enqueue_style('tk-anchor-css', $url . 'css/amarclick_admin.css', [], filemtime($base . 'css/amarclick_admin.css'));
    
}
add_action('admin_enqueue_scripts', 'tk_enqueue_click_analysis_assets');

// فایل کلیک
function tk_enqueue_front_click_tracker() {
    wp_enqueue_script(
        'tk-click-tracker',
        plugin_dir_url(__FILE__) . 'js/click-tracker.js',
        [],
        filemtime(plugin_dir_path(__FILE__) . 'js/click-tracker.js'),
        true
    );

    wp_localize_script('tk-click-tracker', 'tk_click_tracker', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'storage'  => get_option('tk_click_storage_method', 'session')

    ]);
}
add_action('wp_enqueue_scripts', 'tk_enqueue_front_click_tracker');


//فایل تاریخ
function add_persian_datepicker_assets($hook) {
    // فقط در صفحه آمار کلیک‌ها
   
if (strpos($hook, 'jay_click_stats') !== false || strpos($hook, 'jay-click-details') !== false) {
    

    wp_enqueue_style('persian-datepicker-style', plugin_dir_url(__FILE__) . 'css/persianDatepicker.css');
    wp_enqueue_script('persian-datepicker-script', plugin_dir_url(__FILE__) . 'js/persianDatepicker.min.js', ['jquery'], null, true);

    wp_add_inline_script('persian-datepicker-script', "
        function initPersianDatepicker(selector) {
            jQuery(selector).persianDatepicker({
                initialValue: false,
                format: 'YYYY/MM/DD',
                minDate: null,
                endDate: '1424/05/05'
            });
        } 

        jQuery(document).ready(function($) {
            initPersianDatepicker('.persian-date');
        });
    ");
}

}
add_action('admin_enqueue_scripts', 'add_persian_datepicker_assets');

