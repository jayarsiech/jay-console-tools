<?php
// بررسی کش
$refresh = isset($_GET['tk_refresh']);

if ($refresh) {
    delete_transient('jay_anchor_analysis_data');
}

$cached_data = get_transient('jay_anchor_analysis_data');

if ($refresh || !$cached_data) {
    $args = [
        'post_type' => ['post', 'product'],
        'post_status' => 'publish',
        'posts_per_page' => -1
    ];

    $posts = get_posts($args);
    $anchors = [];

   foreach ($posts as $post) {
    $post_title = get_the_title($post);
    $post_link  = get_permalink($post->ID);

    $sources = [];

    // بررسی محتوای اصلی
    if (!empty($post->post_content)) {
        $sources[] = [
            'type'    => 'content',
            'content' => $post->post_content
        ];
    }

    // بررسی فیلدهای ACF
// بررسی فیلدهای ACF مربوط به مقاله جاری
// بررسی فیلدهای ACF
if (function_exists('get_fields')) {
    $acf_fields = get_fields($post->ID);
    if (is_array($acf_fields)) {
        foreach ($acf_fields as $field_name => $value) {
            if (is_string($value) && strpos($value, '<a') !== false) {
                $sources[] = [
                    'type'    => 'acf',
                    'content' => $value
                ];
            } elseif (is_array($value)) {
                // فیلدهای انعطاف‌پذیر و تکرارشونده
                foreach ($value as $sub_field) {
                    if (is_array($sub_field)) {
                        foreach ($sub_field as $sub_value) {
                            if (is_string($sub_value) && strpos($sub_value, '<a') !== false) {
                                $sources[] = [
                                    'type'    => 'acf',
                                    'content' => $sub_value
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
}

    foreach ($sources as $source) {
        preg_match_all('#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#si', $source['content'], $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $href        = esc_url($match[1]);
            $anchor_text = wp_strip_all_tags(trim($match[2]));
            if (!$anchor_text) continue;

            $key = md5(mb_strtolower($anchor_text));

            if (!isset($anchors[$key])) {
                $anchors[$key] = [
                    'text'  => $anchor_text,
                    'links' => []
                ];
            }

            $anchors[$key]['links'][] = [
                'source_title' => $post_title,
                'source_url'   => $post_link,
                'target_url'   => $href,
                'source_type'  => $source['type']
            ];
        }
    }
}


    set_transient('jay_anchor_analysis_data', $anchors, HOUR_IN_SECONDS);
} else {
    $anchors = $cached_data;
}
// صفحه
$per_page = 10;
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($paged - 1) * $per_page;

$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';

if (isset($_GET['orderby']) && $_GET['orderby'] === 'usage') {
    uasort($anchors, function ($a, $b) use ($order) {
        if ($order === 'asc') {
            return count($a['links']) - count($b['links']);
        } else {
            return count($b['links']) - count($a['links']);
        }
    });
} elseif (isset($_GET['orderby']) && $_GET['orderby'] === 'duplicate') {
    uasort($anchors, function ($a, $b) use ($order) {
        $a_unexpected = count(array_filter($a['links'], function($link) use ($a) {
            $link_counts = array_count_values(array_column($a['links'], 'target_url'));
            arsort($link_counts);
            $main_target = key($link_counts);
            return $link['target_url'] !== $main_target;
        }));

        $b_unexpected = count(array_filter($b['links'], function($link) use ($b) {
            $link_counts = array_count_values(array_column($b['links'], 'target_url'));
            arsort($link_counts);
            $main_target = key($link_counts);
            return $link['target_url'] !== $main_target;
        }));

        if ($order === 'asc') {
            return $a_unexpected - $b_unexpected;
        } else {
            return $b_unexpected - $a_unexpected;
        }
    });
}
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

if (!empty($search_query)) {
    $anchors = array_filter($anchors, function ($anchor) use ($search_query) {
        return stripos($anchor['text'], $search_query) !== false;
    });
}

$total_rows = count($anchors);
$total_pages = ceil($total_rows / $per_page);

$paged_anchors = array_slice($anchors, $offset, $per_page, true);

?>

<div class="wrap">
    <h1>📎 تحلیل انکر تکست‌ها</h1>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=jay_anchor_analysis&tk_refresh=1')); ?>" class="button">
            ♻️ تجزیه و تحلیل مجدد
        </a>
    </p>

    <form method="get">
    <input type="hidden" name="page" value="jay_anchor_analysis">
    <input type="text" name="s" id="tk-search-anchor" placeholder="جستجو در انکر تکست..." style="margin-bottom:15px; width:300px; padding:6px;" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>">
    <button type="submit" class="button">جستجو</button>
</form>

    <?php if (!empty($anchors)): ?>
        <table class="widefat fixed striped" id="tk-anchor-table">
            <thead>
                <tr>
                    <th>ردیف</th>
                    <th>انکر تکست</th>
<th id="tk-sort-usage" style="cursor: pointer; color: #0073aa;" title="مرتب‌سازی تعداد استفاده">
    تعداد استفاده

</th>
<th id="tk-sort-duplicate" style="cursor:pointer; color:#0073aa;" title="مرتب‌سازی لینک‌های نادرست">
    لینک اشتباه
</th>


                    <th>مشاهده</th>
                </tr>
            </thead>
            <tbody>
<?php 
$row_index = $offset + 1;
foreach ($paged_anchors as $key => $data): 
?>
<?php
$link_counts = array_count_values(array_column($data['links'], 'target_url'));
arsort($link_counts);
$main_target = key($link_counts);
$unexpected_links = array_filter($data['links'], function($link) use ($main_target) {
    return $link['target_url'] !== $main_target;
});
$unexpected_count = count($unexpected_links);
?>
<tr data-usage="<?php echo count($data['links']); ?>" data-duplicate="<?php echo $unexpected_count; ?>">
        <td><?php echo $row_index++; ?></td>

                        <td><strong><?php echo esc_html($data['text']); ?></strong></td>
                        <td><?php echo count($data['links']); ?> بار</td>
<td>
<?php
$link_counts = array_count_values(array_column($data['links'], 'target_url'));
arsort($link_counts); // مرتب‌سازی نزولی
$main_target = key($link_counts); // مقصد اصلی = پرکاربردترین URL
$unexpected_links = array_filter($data['links'], function($link) use ($main_target) {
    return $link['target_url'] !== $main_target;
});
$unexpected_count = count($unexpected_links);
?>
<button class="tk-show-duplicates button" data-target="<?php echo esc_attr($key); ?>">
    <?php echo $unexpected_count . ' بار'; ?>
</button>

</td>



                        <td>
                            <button class="tk-show-details button" data-target="<?php echo esc_attr($key); ?>">
                                مشاهده
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
<?php if ($total_pages > 1): ?>
    <div style="margin-top: 20px; text-align:center;display: flex;justify-content: center;align-items: center;gap: 5px;">
        <?php
        $base_url = remove_query_arg('paged');
        $range = 2;

        // دکمه قبلی
        if ($paged > 1) {
            echo '<a class="button" href="' . add_query_arg('paged', $paged - 1, $base_url) . '">◀ قبلی</a>';
        }

        // اعداد وسط
        for ($i = max(1, $paged - $range); $i <= min($total_pages, $paged + $range); $i++) {
            $class = ($i == $paged) ? 'button button-primary' : 'button';
            echo '<a class="' . $class . '" href="' . add_query_arg('paged', $i, $base_url) . '">' . $i . '</a>';
        }

        // دکمه بعدی
        if ($paged < $total_pages) {
            echo '<a class="button" href="' . add_query_arg('paged', $paged + 1, $base_url) . '">بعدی ▶</a>';
        }
        ?>
    </div>
<?php endif; ?>


<?php foreach ($paged_anchors as $key => $data): ?>
    <div class="tk-anchor-modal" id="tk-modal-<?php echo esc_attr($key); ?>">
        <div class="tk-modal-content">
            <div class="tk-modal-header">
                <h3>تحلیل برای: "<?php echo esc_html($data['text']); ?>"</h3>
            </div>
            <div class="tk-modal-body">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ردیف</th>
                            <th>مبدا</th>
                            <th>مقصد</th>
                        </tr>
                    </thead>
                <tbody id="tk-modal-body-<?php echo esc_attr($key); ?>" data-key="<?php echo esc_attr($key); ?>">
    <?php
$links = $data['links'];
foreach (array_slice($links, 0, 5) as $index => $link):
    $row_number = $offset + $index + 1;
?>
<tr>
    <td><?php echo $row_number; ?></td>

            <td>
                <a href="<?php echo esc_url($link['source_url']); ?>" target="_blank">
                    <?php echo esc_html($link['source_title']); ?>
                </a>
                <br>
                <small style="color:#666;">منبع: <?php echo $link['source_type'] === 'acf' ? 'ACF' : 'محتوا'; ?></small>
            </td>
            <td>
                <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank">
                    <?php echo esc_html(urldecode($link['target_url'])); ?>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

                </table>
                
               
            </div>
            <div class="tk-modal-footer">
                <?php if (count($links) > 5): ?>
                       <button class="button tk-load-more" data-offset="5" data-key="<?php echo esc_attr($key); ?>">
                    بارگذاری بیشتر...
                </button>
                <?php endif; ?>
                <button class="button tk-close-modal">بستن</button>
            </div>
        </div>
    </div> 
   <?php
    $link_counts = array_count_values(array_column($data['links'], 'target_url'));
    arsort($link_counts);
    $main_target = key($link_counts);
    $unexpected_links = array_filter($data['links'], function($link) use ($main_target) {
        return $link['target_url'] !== $main_target;
    });
    if (empty($unexpected_links)) continue;
    ?>
    <div class="tk-anchor-modal" id="tk-duplicate-modal-<?php echo esc_attr($key); ?>">
    <div class="tk-modal-content">
        <div class="tk-modal-header">
            <h3 style="color:red;">لینک‌های نادرست برای: "<?php echo esc_html($data['text']); ?>"</h3>
            <p style="font-size: 13px; color:#666;">مقصد اصلی تشخیص داده‌شده: 
<a href="<?php echo esc_url($main_target); ?>" target="_blank"><?php echo esc_html(urldecode($main_target)); ?></a>
</p>
        </div>
        <div class="tk-modal-body">
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ردیف</th>
                        <th>مبدا</th>
                        <th>مقصد نادرست</th>
                    </tr>
                </thead>
        <tbody id="tk-duplicate-body-<?php echo esc_attr($key); ?>" data-key="<?php echo esc_attr($key); ?>">
<?php foreach (array_slice($unexpected_links, 0, 5) as $index => $link): ?>
<?php $row_number = $offset + $index + 1; ?>
<tr>
    <td><?php echo $row_number; ?></td>

            <td>
                <a href="<?php echo esc_url($link['source_url']); ?>" target="_blank">
                    <?php echo esc_html($link['source_title']); ?>
                </a>
                <br>
                <small style="color:#666;">منبع: <?php echo $link['source_type'] === 'acf' ? 'ACF' : 'محتوا'; ?></small>
            </td>
            <td>
                <a href="<?php echo esc_url($link['target_url']); ?>" target="_blank">
                    <?php echo esc_html(urldecode($link['target_url'])); ?>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>



            </table>
        </div>
        <div class="tk-modal-footer">
            <?php if (count($unexpected_links) > 5): ?>
            <button class="button tk-load-more-duplicates" data-offset="5" data-key="<?php echo esc_attr($key); ?>">
                بارگذاری بیشتر 
            </button>
          <?php endif; ?>
            <button class="button tk-close-modal">بستن</button>
        </div>
    </div>
</div>


<?php endforeach; ?>

                
                
            
     
    <?php else: ?>
        <p>هیچ انکر تکستی پیدا نشد.</p>
    <?php endif; ?>
</div>
 
