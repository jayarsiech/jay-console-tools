<?php
// تنظیمات اولیه
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 10;
$offset = ($paged - 1) * $per_page;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// لیست ترکیبی برای پست‌ها و دسته‌ها
$combined_items = [];

// گرفتن همه پست‌ها با متای خاص
$all_posts = get_posts([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => '_tk_keywords',
            'compare' => 'EXISTS',
        ]
    ]
]);

foreach ($all_posts as $post) {
    $title     = $post->post_title;
    $keywords  = get_post_meta($post->ID, '_tk_keywords', true);

// رد کن اگر کلمات کلیدی نداشت
if (empty($keywords)) continue;

$decoded = json_decode($keywords, true);
if (!is_array($decoded) || count(array_filter($decoded, function($k) {
    return !empty($k['value']);
})) === 0) {
    continue;
}

if (!empty($search)) {
    $match_title    = stripos($title, $search) !== false;
    $match_keywords = stripos($keywords, $search) !== false;
    if (!$match_title && !$match_keywords) {
        continue;
    }
}

$combined_items[] = [
    'type'     => 'post',
    'title'    => $title,
    'keywords' => $keywords,
    'link'     => get_permalink($post->ID),
    'edit'     => get_edit_post_link($post->ID),
];
  
}


// گرفتن دسته‌هایی که متای خاص دارند
$all_terms = get_terms([
    'taxonomy'   => 'category',
    'hide_empty' => false,
    'meta_query' => [
        [
            'key'     => '_tk_keywords',
            'compare' => 'EXISTS'
        ]
    ]
]);

foreach ($all_terms as $term) {
$keywords = get_term_meta($term->term_id, '_tk_keywords', true);

// رد کن اگر کلیدواژه نداشت
if (empty($keywords)) continue;

$decoded = json_decode($keywords, true);
if (!is_array($decoded) || count(array_filter($decoded, function($k) {
    return !empty($k['value']);
})) === 0) {
    continue;
}

if (!empty($search) && stripos($term->name, $search) === false && stripos($keywords, $search) === false) {
    continue;
}

$combined_items[] = [
    'type'     => 'term',
    'title'    => $term->name . ' (دسته)',
    'keywords' => $keywords,
    'link'     => get_term_link($term),
    'edit'     => get_edit_term_link($term->term_id),
];

}

// مرتب‌سازی بر اساس عنوان
usort($combined_items, function($a, $b) {
    return strcmp($a['title'], $b['title']);
});

// صفحه‌بندی
$total_items = count($combined_items);
$total_pages = ceil($total_items / $per_page);
$combined_items = array_slice($combined_items, $offset, $per_page);

// شمارنده ردیف
$row_index = $offset + 1;

?>
<div class="wrap">
    <h1>🎯 کلمات کلیدی هدف نوشته‌ها</h1>
<form id="tk-search-form" method="get" action="">
    <input type="hidden" name="page" value="jay_keywords_list">
    <input type="text" id="tk-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در کلمات کلیدی..." style="width: 300px; margin-bottom: 20px;" />
    <button type="submit" class="button">جستجو</button>
</form>


    <table class="widefat fixed" id="tk-table">
        <thead>
            <tr>
                <th>ردیف</th>
                <th>عنوان نوشته</th>
                <th>کلمات کلیدی هدف</th>
                <th>پیوند یکتا</th>
                <th>لینک</th>
                
            </tr>
        </thead>
        <tbody>
          

<?php foreach ($combined_items as $item): ?>
<tr>
    <td><?php echo $row_index++; ?></td>
    <td><?php echo esc_html($item['title']); ?></td>
    <td>
        <?php
        $tags = json_decode($item['keywords'], true);
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                echo '<span style="display:inline-block; background:#f3f3f3; border:1px solid #ccc; border-radius:4px; padding:2px 6px; margin:2px; font-size:12px;">' . esc_html($tag['value']) . '</span>';
            }
        } else {
            $fallback_tags = explode(',', $item['keywords']);
            foreach ($fallback_tags as $tag) {
                $tag = trim($tag);
                if ($tag !== '') {
                    echo '<span style="display:inline-block; background:#f3f3f3; border:1px solid #ccc; border-radius:4px; padding:2px 6px; margin:2px; font-size:12px;">' . esc_html($tag) . '</span>';
                }
            }
        }
        ?>
    </td>
    <td>
        <a href="<?php echo esc_url($item['link']); ?>" target="_blank">لینک</a>
        <button class="tk-copy-button" data-link="<?php echo esc_url($item['link']); ?>" style="margin-left:5px;">📋</button>
    </td>
    <td>
        <a href="<?php echo esc_url($item['edit']); ?>">ویرایش</a>
    </td>
</tr>
<?php endforeach; ?>


        </tbody>
        
    </table>
<?php if ($total_pages > 1): ?>
    <div id="tk-keyword-pagination" style="text-align:center; margin-top:20px;">
        <?php
        $base_url = remove_query_arg(['paged', 's']);
        $range = 2; // چند شماره صفحه اطراف صفحه جاری نمایش داده بشه

        // دکمه قبلی
        if ($paged > 1) {
            $prev_url = add_query_arg(['paged' => $paged - 1, 's' => $search], $base_url);
            echo '<a class="button" href="' . esc_url($prev_url) . '">◀ قبلی</a>';
        }

        // اعداد صفحه
        for ($i = max(1, $paged - $range); $i <= min($total_pages, $paged + $range); $i++) {
            $url = add_query_arg(['paged' => $i, 's' => $search], $base_url);
            $class = ($i === $paged) ? 'button button-primary' : 'button';
            echo '<a class="' . $class . '" href="' . esc_url($url) . '" style="margin: 0 3px;">' . $i . '</a>';
        }

        // دکمه بعدی
        if ($paged < $total_pages) {
            $next_url = add_query_arg(['paged' => $paged + 1, 's' => $search], $base_url);
            echo '<a class="button" href="' . esc_url($next_url) . '">بعدی ▶</a>';
        }
        ?>
    </div>
<?php endif; ?>


</div>
