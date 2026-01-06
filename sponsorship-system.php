<?php
/**
 * Plugin Name: Sponsorship System
 * Description: Sponsorships + Transactions stored in custom DB tables, Sponsors remain CPT. (IPN is handled by your existing IPN Callback Page Template)
 * Version: 1.0.0
 * Author: Hashe
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

/**
 * =========================
 * CONFIG
 * =========================
 */
define('SP_ADMIN_EMAIL', 'donations@tenerifehorserescue.com');
define('SP_FROM_EMAIL',  'donotreply@tenerifehorserescue.com');
define('SP_FROM_NAME',   'Tenerife Horse Rescue');
define('SP_ROLE',        'sponser'); // your existing role slug

// Optional: include your modules
if (file_exists(plugin_dir_path(__FILE__) . 'messages-module/hashe-messages/hashe-messages.php')) {
    require_once plugin_dir_path(__FILE__) . 'messages-module/hashe-messages/hashe-messages.php';
    require_once plugin_dir_path(__FILE__) . 'messages-module/hashe-messages/preview-email.php';
}
if (file_exists(plugin_dir_path(__FILE__) . 'reporting-module/hashe-reporting/plugin.php')) {
    require_once plugin_dir_path(__FILE__) . 'reporting-module/hashe-reporting/plugin.php';
}

/**
 * =========================
 * Activation: create tables
 * =========================
 */
register_activation_hook(__FILE__, function () {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    $t_sponsorships = $wpdb->prefix . 'sponsorships';
    $t_tx           = $wpdb->prefix . 'sponsorship_transactions';

    // Sponsorships = link between sponsor and animal (one sponsor can have multiple animals)
    $sql1 = "CREATE TABLE {$t_sponsorships} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sponsor_id BIGINT UNSIGNED NOT NULL,
        animal_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        animal_name VARCHAR(255) NULL,

        sponsorship_type VARCHAR(20) NOT NULL DEFAULT 'one_time', /* one_time|monthly */
        status VARCHAR(20) NOT NULL DEFAULT 'pending',            /* pending|active|cancelled|failed|expired */

        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        currency CHAR(3) NOT NULL DEFAULT 'USD',
        gateway VARCHAR(50) NOT NULL DEFAULT 'paypal',

        item_number VARCHAR(64) NULL,
        subscr_id VARCHAR(64) NULL,

        start_date DATETIME NULL,
        last_payment_at DATETIME NULL,
        last_txn_id VARCHAR(64) NULL,

        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,

        PRIMARY KEY  (id),
        KEY sponsor_id (sponsor_id),
        KEY animal_id (animal_id),
        KEY item_number (item_number),
        KEY subscr_id (subscr_id),
        UNIQUE KEY uniq_link (sponsor_id, animal_id, item_number, subscr_id)
    ) {$charset_collate};";

    // Transactions = one row per payment
    $sql2 = "CREATE TABLE {$t_tx} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sponsor_id BIGINT UNSIGNED NOT NULL,
        sponsorship_id BIGINT UNSIGNED NULL,

        txn_id VARCHAR(64) NOT NULL,
        subscr_id VARCHAR(64) NULL,
        item_number VARCHAR(64) NULL,
        animal_id BIGINT UNSIGNED NOT NULL DEFAULT 0,

        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        currency CHAR(3) NOT NULL DEFAULT 'USD',

        payment_status VARCHAR(32) NULL, /* Completed|Pending|Failed|Refunded... */
        txn_type VARCHAR(32) NULL,       /* web_accept|subscr_payment|subscr_signup|subscr_cancel... */
        payment_date_raw VARCHAR(64) NULL,
        paid_at DATETIME NULL,

        raw_ipn LONGTEXT NULL,
        created_at DATETIME NOT NULL,

        PRIMARY KEY  (id),
        UNIQUE KEY txn_id (txn_id),
        KEY sponsor_id (sponsor_id),
        KEY sponsorship_id (sponsorship_id),
        KEY subscr_id (subscr_id),
        KEY item_number (item_number),
        KEY animal_id (animal_id)
    ) {$charset_collate};";

    dbDelta($sql1);
    dbDelta($sql2);
});

/**
 * =========================
 * Admin menu
 * =========================
 */
add_action('admin_menu', function () {
    global $submenu;

    $cap = 'manage_options';
    $menu_slug = 'sponsorships-page';
    

    add_menu_page('Sponsorships', 'Sponsorships', $cap, $menu_slug, 'sp_render_sponsorships_page', 'dashicons-heart', 3);

    // keep your external link style
    // $submenu[$menu_slug][] = array('<div id="newtab1">Sponsors</div>', 'manage_options', admin_url('/edit.php?post_type=sponsors', 'https'));
  
    // add_submenu_page($menu_slug, 'Sponsorships', 'Sponsorships', $cap, 'sponsorships-page', 'sp_render_sponsorships_page');
    add_submenu_page($menu_slug, 'Sponsors', 'Sponsors', $cap, 'sponsors-page', 'sp_render_sponsors_page');

    add_submenu_page($menu_slug, 'Transactions', 'Transactions', $cap, 'transactions-page', 'sp_render_transactions_page');

    // Message log (if your function exists)
    add_submenu_page(
        $menu_slug,
        __('Message log', 'my-textdomain'),
        __('Message log', 'my-textdomain'),
        'manage_options',
        'message-log',
        function () {
            if (function_exists('my_admin_page_contents')) {
                my_admin_page_contents();
            } else {
                echo '<div class="wrap"><h1>Message log</h1><p>Message log module not loaded.</p></div>';
            }
        }
    );

    // Reporting external link
    $submenu[$menu_slug][] = array('<div id="newtab3">Reporting</div>', 'manage_options', admin_url('/admin.php?page=sponsored', 'https'));
});

add_action('admin_menu', function () {
    // hide old menus if you want
    remove_menu_page('edit.php?post_type=sponsors');  // Sponsors
    // remove_menu_page('edit.php?post_type=messages');  // Messages History
    remove_menu_page('sponsored');                    // Reporting page (still available under hub)
}, 99);

/**
 * =========================
 * Admin pages (simple tables)
 * =========================
 */
function sp_get_request($key, $default = '') {
    return isset($_GET[$key]) ? wp_unslash($_GET[$key]) : $default;
}

function sp_paginate($total, $per_page, $current_page, $base_url) {
    $total_pages = (int) ceil($total / $per_page);
    if ($total_pages <= 1) return '';
    $links = paginate_links([
        'base'      => add_query_arg('paged', '%#%', $base_url),
        'format'    => '',
        'prev_text' => '«',
        'next_text' => '»',
        'total'     => $total_pages,
        'current'   => $current_page,
        'type'      => 'array',
    ]);
    if (empty($links)) return '';
    return '<div class="tablenav"><div class="tablenav-pages">' . implode(' ', $links) . '</div></div>';
}

function sp_render_sponsors_page() {
    if ( ! current_user_can('manage_options') ) wp_die('No permission');
    global $wpdb;

    $t = $wpdb->prefix . 'sponsorships';

    $s      = trim((string) sp_get_request('s', ''));     // sponsor search
    $s_a    = trim((string) sp_get_request('s_a', ''));   // animal search (FIXED)
    $status = (string) sp_get_request('status', '');
    $type   = (string) sp_get_request('type', '');
    $paged  = max(1, (int) sp_get_request('paged', 1));
    $per    = 20;
    $offset = ($paged - 1) * $per;

    $where  = "WHERE 1=1";
    $params = [];

    if ($status !== '') { $where .= " AND status=%s"; $params[] = $status; }
    if ($type !== '')   { $where .= " AND sponsorship_type=%s"; $params[] = $type; }

    // Sponsor search (by sponsor CPT title)
    if ($s !== '') {
        $like = '%' . $wpdb->esc_like($s) . '%';

        $sponsor_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 WHERE p.post_type = %s
                   AND p.post_status IN ('publish','private')
                   AND p.post_title LIKE %s",
                'sponsors',
                $like
            )
        );

        $sponsor_ids = array_values(array_unique(array_map('intval', (array) $sponsor_ids)));

        // If user searched but no sponsors matched -> return no rows safely
        if (empty($sponsor_ids)) {
            $where .= " AND 1=0";
        } else {
            $in = implode(',', array_fill(0, count($sponsor_ids), '%d'));
            $where .= " AND sponsor_id IN ($in)";
            $params = array_merge($params, $sponsor_ids);
        }
    }

    // Animal search (by animals CPT title -> filter sponsorships.animal_id)
    if ($s_a !== '') {
        $like = '%' . $wpdb->esc_like($s_a) . '%';

        $animal_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 WHERE p.post_type = %s
                   AND p.post_status IN ('publish','private')
                   AND p.post_title LIKE %s",
                'animals',
                $like
            )
        );

        $animal_ids = array_values(array_unique(array_map('intval', (array) $animal_ids)));

        if (empty($animal_ids)) {
            $where .= " AND 1=0";
        } else {
            $in = implode(',', array_fill(0, count($animal_ids), '%d'));
            $where .= " AND animal_id IN ($in)";
            $params = array_merge($params, $animal_ids);
        }
    }

    /**
     * COUNT sponsors (not sponsorship rows)
     */
    $sql_count = "SELECT COUNT(DISTINCT sponsor_id) FROM {$t} {$where}";
    $total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $params));

    /**
     * MAIN query: one row per sponsor + animals list
     */
    $sql = "
        SELECT
            sponsor_id,
            COUNT(*) AS sponsorship_rows,
            COUNT(DISTINCT animal_id) AS animals_count,
            GROUP_CONCAT(DISTINCT animal_name ORDER BY animal_name SEPARATOR ', ') AS animals,
            GROUP_CONCAT(DISTINCT sponsorship_type ORDER BY sponsorship_type SEPARATOR ', ') AS types,
            GROUP_CONCAT(DISTINCT status ORDER BY status SEPARATOR ', ') AS statuses,
            GROUP_CONCAT(DISTINCT currency ORDER BY currency SEPARATOR ', ') AS currencies,
            SUM(amount) AS total_amount,
            MAX(COALESCE(updated_at, created_at)) AS last_activity
        FROM {$t}
        {$where}
        GROUP BY sponsor_id
        ORDER BY last_activity DESC
        LIMIT %d OFFSET %d
    ";

    $params2 = array_merge($params, [$per, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params2), ARRAY_A);

    $base_url = remove_query_arg('paged');

    echo '<div class="wrap"><h1>Sponsors</h1>';

    echo '<form method="get" style="margin:12px 0;" class="form_actions">';
    echo '<input type="hidden" name="page" value="sponsors-page" />';

    echo '<input type="search" name="s" value="'.esc_attr($s).'" placeholder="Search Sponsor Name" style="min-width:240px;" /> ';
    echo '<input type="search" name="s_a" value="'.esc_attr($s_a).'" placeholder="Search Animal Name" style="min-width:240px;" /> ';

    echo '<select name="type"><option value="">All Types</option>';
    echo '<option value="one_time" '.selected($type,'one_time',false).'>One-time</option>';
    echo '<option value="monthly" '.selected($type,'monthly',false).'>Monthly</option>';
    echo '</select> ';

    echo '<select name="status"><option value="">All Status</option>';
    foreach (['pending','active','cancelled','failed','expired'] as $st) {
        echo '<option value="'.esc_attr($st).'" '.selected($status,$st,false).'>'.esc_html(ucfirst($st)).'</option>';
    }
    echo '</select> ';

    submit_button('Filter','primary','',false);
    echo ' <a class="button" href="'.esc_url(remove_query_arg(['s_a','s','type','status','paged'])).'">Reset</a>';
    echo '</form>';

    echo '<p><b>Total Sponsors:</b> '.esc_html($total).'</p>';

    echo '<table class="widefat striped"><thead><tr>
        <th>Sponsor ID</th>
        <th>Sponsor</th>
        <th>Sponsored Animals</th>
        <th># Animals</th>
        <th>Types</th>
        <th>Statuses</th>
        <th>Total Amount</th>
        <th>Currencies</th>
        <th>Last Activity</th>
        <th>Actions</th>
    </tr></thead><tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="10">No records found.</td></tr>';
    } else {
        foreach ($rows as $r) {
            $sponsor_id = (int) $r['sponsor_id'];

            $edit_url = admin_url('post.php?post=' . $sponsor_id . '&action=edit');
            $view_url = get_permalink($sponsor_id);
            $title    = get_the_title($sponsor_id);

            echo '<tr>';
            echo '<td>'.esc_html($sponsor_id).'</td>';
            echo '<td><a href="'.esc_url($edit_url).'">'.esc_html($title).'</a></td>';
            echo '<td>'.esc_html($r['animals']).'</td>';
            echo '<td>'.esc_html($r['animals_count']).'</td>';
            echo '<td>'.esc_html($r['types']).'</td>';
            echo '<td>'.esc_html($r['statuses']).'</td>';
            echo '<td>'.esc_html($r['total_amount']).'</td>';
            echo '<td>'.esc_html($r['currencies']).'</td>';
            echo '<td>'.esc_html($r['last_activity']).'</td>';
            echo '<td><a class="button button-small" href="'.esc_url($view_url).'" target="_blank">View Sponsor</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    echo sp_paginate($total, $per, $paged, $base_url);

    echo '</div>';
    ?>
    <style>
        form.form_actions {
            display:flex; gap:5px; background:#1d2327; padding:10px; border-radius:5px;
        }
        .tablenav { clear:both; margin:6px 0 4px; padding-top:5px; gap:10px; color:#fff; background:#1d2327; border-radius:5px; }
        .tablenav .tablenav-pages > * { background:#fff; margin:0 5px; display:inline-block; color:#000; padding:2px 10px; line-height:1.5; border-radius:3px; text-decoration:none; }
        .tablenav .tablenav-pages > span.page-numbers.current { background:#2271b1; color:#fff; }
        .notice { display:none !important; }
    </style>
    <?php
}


function sp_render_sponsorships_page() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;

    $t = $wpdb->prefix . 'sponsorships';

    $s      = trim((string) sp_get_request('s', ''));
    $s_a      = trim((string) sp_get_request('s', ''));
    $status = (string) sp_get_request('status', '');
    $type   = (string) sp_get_request('type', '');
    $paged  = max(1, (int) sp_get_request('paged', 1));
    $per    = 20;
    $offset = ($paged - 1) * $per;

    $where  = "WHERE 1=1";
    $params = [];

    if ($status !== '') { $where .= " AND status=%s"; $params[] = $status; }
    if ($type !== '')   { $where .= " AND sponsorship_type=%s"; $params[] = $type; }
    if ($s !== '') {
        
        $meta_like = '%' . $wpdb->esc_like($s) . '%';

        $sponsor_ids_meta = $wpdb->get_col(
          $wpdb->prepare(
            "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','private')
             AND post_title LIKE %s
            ",
            'sponsors',
            $meta_like
          )
        );
        $sponsor_ids = [];
        
        $sponsor_ids = array_values(array_unique(array_merge($sponsor_ids, $sponsor_ids_meta)));

        
        if ( ! empty($sponsor_ids) ) {
            $in_placeholders = implode(',', array_fill(0, count($sponsor_ids), '%d'));
        
            $where .= " AND sponsor_id IN ($in_placeholders)";
        
            // merge numeric ids into params
            $params = array_merge($params, array_map('intval', $sponsor_ids));
        }

        // $like = '%' . $wpdb->esc_like($s) . '%';
        // $where .= " AND (CAST(sponsor_id AS CHAR) LIKE %s)";
        // $params = array_merge($params, [$like]);
    }
     // animal name 
    if ($s_a !== '') {
        // $like = '%' . $wpdb->esc_like($s) . '%';
        // $where .= " AND (txn_id LIKE %s OR subscr_id LIKE %s OR item_number LIKE %s OR CAST(animal_id AS CHAR) LIKE %s OR CAST(sponsor_id AS CHAR) LIKE %s)";
        // $params = array_merge($params, [$like,$like,$like,$like,$like]);
        
        $meta_like = '%' . $wpdb->esc_like($s_a) . '%';

        $sponsor_ids_meta = $wpdb->get_col(
          $wpdb->prepare(
            "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','private')
             AND post_title LIKE %s
            ",
            'animals',
            $meta_like
          )
        );
        $animal_ids = [];
        
        $animal_ids = array_values(array_unique(array_merge($animal_ids, $sponsor_ids_meta)));

        
        if ( ! empty($animal_ids) ) {
            $in_placeholders = implode(',', array_fill(0, count($animal_ids), '%d'));
        
            $where .= " AND animal_id IN ($in_placeholders)";
        
            // merge numeric ids into params
            $params = array_merge($params, array_map('intval', $animal_ids));
        }
    }

    $sql_count = $wpdb->prepare("SELECT COUNT(*) FROM {$t} {$where}", $params);
    $total = (int) $wpdb->get_var($sql_count);

    $sql = "SELECT * FROM {$t} {$where} ORDER BY COALESCE( updated_at) DESC LIMIT %d OFFSET %d";
    $params2 = array_merge($params, [$per, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params2), ARRAY_A);

    $base_url = remove_query_arg('paged');

    echo '<div class="wrap"><h1>Sponsorships</h1>';

    echo '<form method="get" style="margin:12px 0;" class="form_actions">';
    echo '<input type="hidden" name="page" value="sponsorships-page" />';
    echo '<input type="search" name="s" value="'.esc_attr($s).'" placeholder="Sponsor " style="min-width:240px;" /> ';
    echo '<input type="search" name="s_a" value="'.esc_attr($s_a).'" placeholder="Sponsor " style="min-width:20px;" /> ';

    echo '<select name="type"><option value="">All Types</option>';
    echo '<option value="one_time" '.selected($type,'one_time',false).'>One-time</option>';
    echo '<option value="monthly" '.selected($type,'monthly',false).'>Monthly</option>';
    echo '</select> ';

    echo '<select name="status"><option value="">All Status</option>';
    foreach (['pending','active','cancelled','failed','expired'] as $st) {
        echo '<option value="'.esc_attr($st).'" '.selected($status,$st,false).'>'.esc_html(ucfirst($st)).'</option>';
    }
    echo '</select> ';

    submit_button('Filter','primary','',false);
    echo ' <a class="button" href="'.esc_url(remove_query_arg(['s_a','s','type','status','paged'])).'">Reset</a>';
    echo '</form>';

    echo '<p><b>Total:</b> '.esc_html($total).'</p>';

    echo '<table class="widefat striped"><thead><tr>
        <th>ID</th>
        <th>Sponsor</th>
        <th>Animal</th>
        <th>Payment Type</th>
        <th>Status</th>
        <th>Amount</th>
        <th>Currency</th>

        <th>Actions</th>
    </tr></thead><tbody>';

        // <th>Subscr ID</th>
        // <th>Last Payment</th>
    if (empty($rows)) {
        echo '<tr><td colspan="13">No records found.</td></tr>';
    } else {
        foreach ($rows as $r) {
            // esc_html($r['animal_id'])
            // $edit = admin_url('post.php?post='.(int)$r['sponsor_id'].'&action=edit');
            $post_permalink = get_permalink( (int)$r['sponsor_id'] );
             $post_title = get_the_title( (int)$r['sponsor_id'] );

            echo '<tr>';
            echo '<td>'.esc_html($r['id']).'</td>';
            echo '<td><a href="'.esc_url($edit).'">'.esc_html($post_title).'</a></td>';
            echo '<td>'.esc_html($r['animal_name']).'</td>';
            echo '<td>'.esc_html($r['sponsorship_type']).'</td>';
            echo '<td>'.esc_html($r['status']).'</td>';
            echo '<td>'.esc_html($r['amount']).'</td>';
            echo '<td>'.esc_html($r['currency']).'</td>';
            // echo '<td>'.esc_html($r['subscr_id']).'</td>';
            echo '<td>'.esc_html($r['updated_at']).'</td>';
            // <a class="button button-small" href="'.esc_url(admin_url('admin.php?page=sponsorships-page&detail='.$r['id'])).'">View Details</a>
            echo '<td><a class="button button-small" href="'.esc_url($post_permalink).'">view Sponsor</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo sp_paginate($total,$per,$paged,$base_url);
    echo '</div>';
    
    ?>
<style>
    form.form_actions {
        display: flex;
        gap: 5px;
        background: #1d2327;
        padding: 10px 10px;
        border-radius: 5px;
    }
    .tablenav {
    clear: both;
    height: 30px;
    margin: 6px 0 4px;
    padding-top: 5px;
    vertical-align: middle;
    gap: 10px;
    color: #fff;
    background: #1d2327;
    border-radius: 5px;
}
.tablenav .tablenav-pages > * {
    background: #fff;
    margin: 0px 5px;
    display: inline-block;
    color: #000;
    padding: 2px 10px;
    line-height: 1.5;
    border-radius: 3px;
    text-decoration: none;
}
.tablenav .tablenav-pages > span.page-numbers.current {
    background: #2271b1;
    color: #fff;
}
.notice {
    display: none !important;
}
</style>
<?php    
}

function sp_render_transactions_page() {
    if (!current_user_can('manage_options')) wp_die('No permission');
    global $wpdb;

    $t = $wpdb->prefix . 'sponsorship_transactions';

    $s        = trim((string) sp_get_request('s', ''));
     $s_a        = trim((string) sp_get_request('s_a', ''));
     
    $txn_type = (string) sp_get_request('txn_type', '');
    $pstatus  = (string) sp_get_request('payment_status', '');
    $paged    = max(1, (int) sp_get_request('paged', 1));
    $per      = 20;
    $offset   = ($paged - 1) * $per;

    $where  = "WHERE 1=1";
    $params = [];

    if ($txn_type !== '') { $where .= " AND txn_type=%s"; $params[] = $txn_type; }
    if ($pstatus !== '')  { $where .= " AND payment_status=%s"; $params[] = $pstatus; }
    if ($s !== '') {
        // $like = '%' . $wpdb->esc_like($s) . '%';
        // $where .= " AND (txn_id LIKE %s OR subscr_id LIKE %s OR item_number LIKE %s OR CAST(animal_id AS CHAR) LIKE %s OR CAST(sponsor_id AS CHAR) LIKE %s)";
        // $params = array_merge($params, [$like,$like,$like,$like,$like]);
        
        $meta_like = '%' . $wpdb->esc_like($s) . '%';

        $sponsor_ids_meta = $wpdb->get_col(
          $wpdb->prepare(
            "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','private')
             AND post_title LIKE %s
            ",
            'sponsors',
            $meta_like
          )
        );
        $sponsor_ids = [];
        
        $sponsor_ids = array_values(array_unique(array_merge($sponsor_ids, $sponsor_ids_meta)));

        
        if ( ! empty($sponsor_ids) ) {
            $in_placeholders = implode(',', array_fill(0, count($sponsor_ids), '%d'));
        
            $where .= " AND sponsor_id IN ($in_placeholders)";
        
            // merge numeric ids into params
            $params = array_merge($params, array_map('intval', $sponsor_ids));
        }
    }
    
    // animal name 
    if ($s_a !== '') {
        // $like = '%' . $wpdb->esc_like($s) . '%';
        // $where .= " AND (txn_id LIKE %s OR subscr_id LIKE %s OR item_number LIKE %s OR CAST(animal_id AS CHAR) LIKE %s OR CAST(sponsor_id AS CHAR) LIKE %s)";
        // $params = array_merge($params, [$like,$like,$like,$like,$like]);
        
        $meta_like = '%' . $wpdb->esc_like($s_a) . '%';

        $animal_ids_meta = $wpdb->get_col(
          $wpdb->prepare(
            "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = %s
              AND p.post_status IN ('publish','private')
             AND post_title LIKE %s
            ",
            'animals',
            $meta_like
          )
        );
        $animal_ids = [];
        
        $animal_ids = array_values(array_unique(array_merge($animal_ids, $animal_ids_meta)));

        
        if ( ! empty($animal_ids) ) {
            $in_placeholders = implode(',', array_fill(0, count($animal_ids), '%d'));
        
            $where .= " AND animal_id IN ($in_placeholders)";
        
            // merge numeric ids into params
            $params = array_merge($params, array_map('intval', $animal_ids));
        }
    }

    $sql_count = $wpdb->prepare("SELECT COUNT(*) FROM {$t} {$where}", $params);
    $total = (int) $wpdb->get_var($sql_count);

    $sql = "SELECT * FROM {$t} {$where} ORDER BY COALESCE(paid_at, created_at) DESC LIMIT %d OFFSET %d";
    $params2 = array_merge($params, [$per, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params2), ARRAY_A);
    // echo $wpdb->prepare($sql, $params2);

    $base_url = remove_query_arg('paged');

    echo '<div class="wrap"><h1>Transactions</h1>';

    echo '<form method="get" style="margin:12px 0;" class="form_actions">';
    echo '<input type="hidden" name="page" value="transactions-page" />';
    echo '<input type="search" name="s" value="'.esc_attr($s).'" placeholder="Sponsor " style="min-width:240px;" /> ';
    echo '<input type="search" name="s_a" value="'.esc_attr($s_a).'" placeholder="Animal Name " style="min-width:240px;" /> ';

    echo '<select name="txn_type"><option value="">All txn_type</option>';
    foreach (['web_accept','subscr_payment','subscr_signup','subscr_cancel','subscr_failed','subscr_eot'] as $v) {
        echo '<option value="'.esc_attr($v).'" '.selected($txn_type,$v,false).'>'.esc_html($v).'</option>';
    }
    echo '</select> ';

    echo '<select name="payment_status"><option value="">All payment_status</option>';
    foreach (['Completed','Pending','Failed','Refunded','Reversed'] as $v) {
        echo '<option value="'.esc_attr($v).'" '.selected($pstatus,$v,false).'>'.esc_html($v).'</option>';
    }
    echo '</select> ';

    submit_button('Filter','primary','',false);
    echo ' <a class="button" href="'.esc_url(remove_query_arg(['s_a','s','txn_type','payment_status','paged'])).'">Reset</a>';
    echo '</form>';

    echo '<p><b>Total:</b> '.esc_html($total).'</p>';

    echo '<table class="widefat striped"><thead><tr>
        <th>ID</th>
        <th>Sponsor</th>
        <th>Animal Name</th>
       <th>Transaction Type</th>
        <th>Status</th>
        <th>Amount</th>
        <th>Currency</th>

        <th>Actions</th>
    </tr></thead><tbody>';
     // <th>Txn ID</th>
        // <th>Txn Type</th>
        // <th>Item #</th>
        // <th>Subscr ID</th>
        // <th>Animal ID</th>
        // <th>Paid At</th>
        
    if (empty($rows)) {
        echo '<tr><td colspan="13">No records found.</td></tr>';
    } else {
        foreach ($rows as $r) {
            $post_permalink = get_permalink( (int)$r['sponsor_id'] );
             $post_title = get_the_title( (int)$r['sponsor_id'] );
             $animal_title = get_the_title( (int)$r['animal_id'] );
            $edit = admin_url('post.php?post='.(int)$r['sponsor_id'].'&action=edit');
            echo '<tr>';
            echo '<td>'.esc_html($r['id']).'</td>';
            echo '<td>'.esc_html($post_title).'</td>';
            echo '<td>'.esc_html($animal_title).'</td>';
            // echo '<td>'.esc_html($r['txn_id']).'</td>';
            echo '<td>'.esc_html($r['txn_type']).'</td>';
            echo '<td>'.esc_html($r['payment_status']).'</td>';
            echo '<td>'.esc_html($r['amount']).'</td>';
            echo '<td>'.esc_html($r['currency']).'</td>';
            // echo '<td>'.esc_html($r['item_number']).'</td>';
            // echo '<td>'.esc_html($r['subscr_id']).'</td>';
            // echo '<td>'.esc_html($r['animal_id']).'</td>';
            // echo '<td>'.esc_html($r['paid_at']).'</td>';
            echo '<td><a class="button button-small" href="'.esc_url($post_permalink).'">view details</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo sp_paginate($total,$per,$paged,$base_url);
    echo '</div>';

        ?>
<style>
    form.form_actions {
        display: flex;
        gap: 5px;
        background: #1d2327;
        padding: 10px 10px;
        border-radius: 5px;
    }
    .tablenav {
    clear: both;
    height: 30px;
    margin: 6px 0 4px;
    padding-top: 5px;
    vertical-align: middle;
    gap: 10px;
    color: #fff;
    background: #1d2327;
    border-radius: 5px;
}
.tablenav .tablenav-pages > * {
    background: #fff;
    margin: 0px 5px;
    display: inline-block;
    color: #000;
    padding: 2px 10px;
    line-height: 1.5;
    border-radius: 3px;
    text-decoration: none;
}
.tablenav .tablenav-pages > span.page-numbers.current {
    background: #2271b1;
    color: #fff;
}
.notice {
    display: none !important;
}
</style>
<?php    
}

/**
 * =========================
 * IPN CORE (CALL THIS FROM YOUR IPN CALLBACK PAGE TEMPLATE)
 * =========================
 *
 * In your IPN Page Template, after verification, do:
 *   $result = sp_process_paypal_ipn(file_get_contents('php://input'), $_POST);
 *   echo wp_json_encode($result); exit;
 *
 * This plugin DOES NOT register an IPN endpoint; it only provides processing + DB storage.
 */
function sp_process_paypal_ipn($raw_body, array $ipn) {
    global $wpdb;

    $txn_type       = sanitize_text_field($ipn['txn_type'] ?? '');
    $payment_status = sanitize_text_field($ipn['payment_status'] ?? '');
    $txn_id         = sanitize_text_field($ipn['txn_id'] ?? '');
    $item_number    = sanitize_text_field($ipn['item_number'] ?? '');
    $subscr_id      = sanitize_text_field($ipn['subscr_id'] ?? '');
    $mc_currency    = sanitize_text_field($ipn['mc_currency'] ?? '');
    $mc_gross       = isset($ipn['mc_gross']) ? (float)$ipn['mc_gross'] : 0.0;
    $payment_date   = sanitize_text_field($ipn['payment_date'] ?? '');

    $custom_raw = $ipn['custom'] ?? '';
    $custom = sp_parse_custom($custom_raw);

    $your_email = strtolower(sanitize_email($custom['your_email'] ?? ''));
    $your_name  = sanitize_text_field($custom['your_name'] ?? '');
    $animal_id  = isset($custom['specify_animal']) ? (int)$custom['specify_animal'] : 0;
    $plan       = sanitize_text_field($custom['donation_plan'] ?? '');
    $amount_custom = isset($custom['amount']) ? (float)$custom['amount'] : 0.0;

    if (!$your_email) {
        return ['ok'=>false,'error'=>'missing_email_in_custom'];
    }

    // sponsor post (CPT)
    $sponsor_id = sp_get_or_create_sponsor($your_email, $your_name);

    // determine type (monthly vs one_time)
    $sponsorship_type = (strpos($txn_type,'subscr_') === 0) ? 'monthly' : 'one_time';
    if ($sponsorship_type === 'one_time') {
        $pl = strtolower($plan);
        if (strpos($pl,'month') !== false || strpos($pl,'monthly') !== false) $sponsorship_type = 'monthly';
    }

    // map status
    $is_paid = true; //($payment_status === 'Completed');
    $status = 'pending';
    if ($txn_type === 'subscr_cancel') $status = 'cancelled';
    elseif ($txn_type === 'subscr_failed') $status = 'failed';
    elseif ($txn_type === 'subscr_eot') $status = 'expired';
    elseif ($is_paid && in_array($txn_type, ['web_accept','subscr_payment'], true)) $status = 'active';
    elseif ($txn_type === 'subscr_signup') $status = 'pending';

    $t_s  = $wpdb->prefix . 'sponsorships';
    $t_tx = $wpdb->prefix . 'sponsorship_transactions';
    $now  = current_time('mysql');

    // Uniqueness fields
    $s_item = $item_number ?: ($custom['item_number'] ?? '');
    $s_sub  = $subscr_id ?: '';

    // amount
    $amount_final = $mc_gross ?: ($amount_custom ?: 0.0);

    // upsert sponsorship
    $existing_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$t_s}
         WHERE sponsor_id=%d AND animal_id=%d AND item_number <=> %s AND subscr_id <=> %s
         LIMIT 1",
        $sponsor_id, $animal_id, $s_item, $s_sub
    ));

    if ($existing_id) {
        $wpdb->update($t_s, [
            'sponsorship_type' => $sponsorship_type,
            'status'           => $status,
            'amount'           => $amount_final,
            'currency'         => $mc_currency ?: 'USD',
            'gateway'          => 'paypal',
            'item_number'      => $s_item ?: null,
            'subscr_id'        => $s_sub ?: null,
            'last_payment_at'  => ($is_paid ? $now : null),
            'last_txn_id'      => ($txn_id ? $txn_id : null),
            'updated_at'       => $now,
        ], ['id' => $existing_id]);

        $sponsorship_id = $existing_id;
            ipn_log("ERROR: sp_process_paypal_ipn() in sposonsr existing");

    } else {
       
        
        
                    $insert_data = [
                'sponsor_id'       => (int) $sponsor_id,
                'animal_id'        => (int) $animal_id,
                'animal_name'      => get_the_title($animal_id), // do not touch animals CPT here
                'sponsorship_type' => $sponsorship_type,
                'status'           => $status,
                'amount'           => $amount_final,
                'currency'         => $mc_currency ?: 'USD',
                'gateway'          => 'paypal',
                'item_number'      => $s_item ?: null,
                'subscr_id'        => $s_sub ?: null,
                'start_date'       => $now,
                'last_payment_at'  => ($is_paid ? $now : null),
                'last_txn_id'      => ($txn_id ? $txn_id : null),
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
            
            $inserted = $wpdb->insert($t_s, $insert_data);
            
            if ($inserted === false) {
                ipn_log("ERROR: Sponsorship insert failed. last_error=" . $wpdb->last_error);
                ipn_log("ERROR: last_query=" . $wpdb->last_query);
                // Optional: stop here if you want
                // return;
            } else {
                $sponsorship_id = (int) $wpdb->insert_id;
            
                ipn_log("OK: Sponsorship inserted. insert_id=" . $sponsorship_id);
            
                // If insert_id is still 0, your table likely has no AUTO_INCREMENT PK
                if ($sponsorship_id === 0) {
                    ipn_log("WARN: insert succeeded but insert_id=0. Check AUTO_INCREMENT primary key on table: {$t_s}");
                }
            }


        
    }

    // insert transaction (dedupe by UNIQUE txn_id)
    $added_tx = false;
    if ($txn_id) {
        $paid_at = $is_paid ? $now : null;

        // INSERT IGNORE to respect UNIQUE txn_id
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$t_tx}
            (sponsor_id, sponsorship_id, txn_id, subscr_id, item_number, animal_id, amount, currency, payment_status, txn_type, payment_date_raw, paid_at, raw_ipn, created_at)
            VALUES (%d, %d, %s, %s, %s, %d, %f, %s, %s, %s, %s, %s, %s, %s)",
            $sponsor_id,
            $sponsorship_id,
            $txn_id,
            $subscr_id ?: null,
            $item_number ?: null,
            $animal_id,
            $amount_final,
            $mc_currency ?: 'USD',
            $payment_status ?: null,
            $txn_type ?: null,
            $payment_date ?: null,
            $paid_at,
            wp_json_encode($ipn),
            $now
        ));

        $added_tx = ($inserted > 0);
    }

    // user creation + credentials email (only on paid payment)
    if ($is_paid && in_array($txn_type, ['web_accept','subscr_payment'], true)) {
        sp_ensure_user($sponsor_id, $your_email, $your_name);
    }

    // admin emails (send only once per txn when paid + new txn)
    if ($txn_type === 'web_accept' && $is_paid && $added_tx) {
        sp_send_admin_email('donation-add', $custom, $ipn, $sponsor_id, $sponsorship_id);
    }
    if ($txn_type === 'subscr_payment' && $is_paid && $added_tx) {
        sp_send_admin_email('subscription-add', $custom, $ipn, $sponsor_id, $sponsorship_id);
    }
    if ($txn_type === 'subscr_cancel') {
        // dedupe cancel email by subscr_id
        if ($subscr_id) {
            $last_cancel = get_post_meta($sponsor_id, '_last_cancel_notice_subscr_id', true);
            if ($last_cancel !== $subscr_id) {
                sp_send_admin_email('subscription-cancel', $custom, $ipn, $sponsor_id, $sponsorship_id);
                update_post_meta($sponsor_id, '_last_cancel_notice_subscr_id', $subscr_id);
            }
        } else {
            sp_send_admin_email('subscription-cancel', $custom, $ipn, $sponsor_id, $sponsorship_id);
        }
    }

    return [
        'ok' => true,
        'sponsor_id' => $sponsor_id,
        'sponsorship_id' => $sponsorship_id,
        'added_tx' => $added_tx,
        'txn_type' => $txn_type,
        'payment_status' => $payment_status,
        'status' => $status
    ];
}

/**
 * =========================
 * Helpers
 * =========================
 */
function sp_parse_custom($custom) {
    $data = [];
    if (!$custom) return $data;

    $parts = explode(',', $custom);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $data[trim($kv[0])] = trim($kv[1]);
        }
    }
    return $data;
}

function sp_find_sponsor_by_email($email) {
    $email = strtolower(sanitize_email($email));
    if (!$email) return 0;

    // primary: title is email
    $post = get_page_by_title($email, OBJECT, 'sponsors');
    if ($post && !empty($post->ID)) return (int) $post->ID;

    // fallback: ACF meta 'email'
    $ids = get_posts([
        'post_type'              => 'sponsors',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'meta_key'               => 'email',
        'meta_value'             => $email,
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);
    return !empty($ids) ? (int)$ids[0] : 0;
}

function sp_get_or_create_sponsor($email, $name = '') {
    $email = strtolower(sanitize_email($email));
    $sid = sp_find_sponsor_by_email($email);

    if ($sid) {
        if ($name !== '' && function_exists('update_field')) update_field('name', sanitize_text_field($name), $sid);
        if (function_exists('update_field')) update_field('email', $email, $sid);
        return $sid;
    }

    $sid = wp_insert_post([
        'post_title'  => $email,
        'post_status' => 'publish',
        'post_type'   => 'sponsors',
    ]);

    if ($sid) {
        if ($name !== '' && function_exists('update_field')) update_field('name', sanitize_text_field($name), $sid);
        if (function_exists('update_field')) update_field('email', $email, $sid);
        
    }
    return (int)$sid;
}

function sp_ensure_user($sponsor_post_id, $email, $name = '') {
    $email = strtolower(sanitize_email($email));
    if (!$email || !$sponsor_post_id) return 0;

    $p = get_post($sponsor_post_id);
    if ($p && (int)$p->post_author > 0) return (int)$p->post_author;

    $user = get_user_by('email', $email);
    if ($user) {
        wp_update_post(['ID'=>$sponsor_post_id, 'post_author'=>$user->ID]);
        return (int)$user->ID;
    }

    $username_base = sanitize_user($email, true);
    if ($username_base === '') $username_base = 'sponsor';

    $username = $username_base;
    $i = 1;
    while (username_exists($username)) {
        $username = $username_base . $i++;
    }

    $password = wp_generate_password(12, false);

    $userdata = [
        'user_login'   => $username,
        'user_pass'    => $password,
        'user_email'   => $email,
        'display_name' => $name ?: $email,
        'nickname'     => $name ?: $email,
        'role'         => SP_ROLE,
    ];

    $user_id = wp_insert_user($userdata);
    if (is_wp_error($user_id)) return 0;

    wp_update_post(['ID'=>$sponsor_post_id, 'post_author'=>$user_id]);

    // credentials email
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $login_url = site_url('/sponsor-login');
    $subject = sprintf(__('[%s] Your username and password'), $blogname);

    $message = '<p>Welcome ' . esc_html($name ?: $email) . ',</p>'
        . '<p>Thank you for your sponsorship! Here are your login details:</p>'
        . '<p><b>Username:</b> ' . esc_html($username) . '<br>'
        . '<b>Password:</b> ' . esc_html($password) . '</p>'
        . '<p>Login here: <a href="' . esc_url($login_url) . '">' . esc_html($login_url) . '</a></p>';

    $headers = [
        'From: ' . SP_FROM_NAME . ' <' . SP_FROM_EMAIL . '>',
        'Reply-To: ' . SP_FROM_EMAIL,
        'Content-Type: text/html; charset=UTF-8',
    ];

    wp_mail($email, $subject, $message, $headers);
 ipn_log("WARN: emai sent and user  created");
    return (int)$user_id;
}

function sp_send_admin_email($type, array $custom, array $ipn, $sponsor_id, $sponsorship_id) {
    $to = SP_ADMIN_EMAIL;

    $name      = $custom['your_name'] ?? '';
    $email     = $custom['your_email'] ?? '';
    $amount    = $custom['amount'] ?? ($ipn['mc_gross'] ?? '');
    $currency  = $ipn['mc_currency'] ?? '';
    $animal_id = $custom['specify_animal'] ?? '';
    $plan      = $custom['donation_plan'] ?? '';
    $txn_type  = $ipn['txn_type'] ?? '';
    $status    = $ipn['payment_status'] ?? '';
    $txn_id    = $ipn['txn_id'] ?? '';
    $subscr_id = $ipn['subscr_id'] ?? '';

    $subject = 'New Sponsorship';
    if ($type === 'donation-add')        $subject = 'New Sponsorship (One-time)';
    if ($type === 'subscription-add')    $subject = 'Monthly Sponsorship Payment Received';
    if ($type === 'subscription-cancel') $subject = 'Monthly Sponsorship Cancelled: ' . $name;

    $message = '<h3>Sponsorship Notification</h3>'
        . '<p><b>Sponsor Post:</b> #'.esc_html($sponsor_id).'</p>'
        . '<p><b>Sponsorship Row:</b> #'.esc_html($sponsorship_id).'</p>'
        . '<p><b>Name:</b> ' . esc_html($name) . '</p>'
        . '<p><b>Email:</b> ' . esc_html($email) . '</p>'
        . '<p><b>Plan:</b> ' . esc_html($plan) . '</p>'
        . '<p><b>Animal ID:</b> ' . esc_html($animal_id) . '</p>'
        . '<p><b>Amount:</b> ' . esc_html(trim($currency . ' ' . $amount)) . '</p>'
        . '<hr>'
        . '<p><b>Txn Type:</b> ' . esc_html($txn_type) . '</p>'
        . '<p><b>Payment Status:</b> ' . esc_html($status) . '</p>'
        . '<p><b>Txn ID:</b> ' . esc_html($txn_id) . '</p>'
        . '<p><b>Subscr ID:</b> ' . esc_html($subscr_id) . '</p>';

    $headers = [
        'From: ' . SP_FROM_NAME . ' <' . SP_FROM_EMAIL . '>',
        'Reply-To: ' . SP_FROM_EMAIL,
        'Content-Type: text/html; charset=UTF-8',
    ];

    wp_mail($to, $subject, $message, $headers);
    ipn_log("WARN: emai sent ".$type);
    
}
