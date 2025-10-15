<?php
if ( ! defined('ABSPATH') ) exit;

function brs_bfo_render_log_page() {
    if ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) {
        wp_die( esc_html__('You do not have permission to view this page.', 'brs-block-fake-orders') );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'brs_fo_log';

    // Actions: clear, export
    $action = isset($_POST['brs_bfo_action']) ? sanitize_text_field($_POST['brs_bfo_action']) : '';
    if ( $action && check_admin_referer('brs_bfo_log_action','brs_bfo_nonce') ) {
        if ( $action === 'clear' ) {
            $wpdb->query( "TRUNCATE TABLE {$table}" );
            echo '<div class="updated notice"><p>Log cleared.</p></div>';
        } elseif ( $action === 'export_csv' ) {
            brs_bfo_export_csv();
            exit;
        }
    }

    // Filters / search
    $level   = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
    $route   = isset($_GET['route']) ? sanitize_text_field($_GET['route']) : '';
    $s       = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $perpage = isset($_GET['perpage']) ? max( 10, (int) $_GET['perpage'] ) : 50;
    $paged   = max( 1, (int) ($_GET['paged'] ?? 1) );
    $offset  = ($paged - 1) * $perpage;

    $where = "WHERE 1=1";
    $params = [];

    if ( $level ) {
        $where  .= " AND level = %s";
        $params[] = $level;
    }
    if ( $route ) {
        $where  .= " AND route LIKE %s";
        $params[] = '%' . $wpdb->esc_like($route) . '%';
    }
    if ( $s ) {
        $where  .= " AND (msg LIKE %s OR context LIKE %s OR ua LIKE %s)";
        $q = '%' . $wpdb->esc_like($s) . '%';
        array_push($params, $q, $q, $q);
    }

    // Count
    $total = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", $params) );

    // Query
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
            array_merge( $params, [ $perpage, $offset ] )
        ),
        ARRAY_A
    );

    // UI
    echo '<div class="wrap">';
    echo '<h1>BRS Block Fake Orders &mdash; Log</h1>';

    // Filters/search form
    echo '<form method="get" style="margin:10px 0;">';
    echo '<input type="hidden" name="page" value="brs-fake-orders-log" />';
    echo '<input type="text" name="s" value="' . esc_attr($s) . '" placeholder="Search message, UA, context..." style="min-width:260px" />';
    echo ' <select name="level">
            <option value="">All Levels</option>
            <option value="info" '.selected($level,'info',false).'>info</option>
            <option value="warning" '.selected($level,'warning',false).'>warning</option>
            <option value="error" '.selected($level,'error',false).'>error</option>
          </select>';
    echo ' <input type="text" name="route" value="' . esc_attr($route) . '" placeholder="Route contains..." />';
    echo ' <select name="perpage">
            <option '.selected($perpage,25,false).'>25</option>
            <option '.selected($perpage,50,false).'>50</option>
            <option '.selected($perpage,100,false).'>100</option>
          </select>';
    submit_button('Filter', 'secondary', '', false);
    echo '</form>';

    // Actions
    echo '<form method="post" style="margin:10px 0;">';
    wp_nonce_field('brs_bfo_log_action','brs_bfo_nonce');
    echo '<button class="button button-secondary" name="brs_bfo_action" value="export_csv">Export CSV</button> ';
    echo '<button class="button button-link-delete" name="brs_bfo_action" value="clear" onclick="return confirm(\'Clear all log entries?\');">Clear Log</button>';
    echo '</form>';

    // Table
    if ( empty($rows) ) {
        echo '<p><em>No entries.</em></p>';
    } else {
        echo '<table class="widefat striped">';
        echo '<thead><tr>
                <th width="120">Time (UTC)</th>
                <th width="90">Level</th>
                <th>Message</th>
                <th>Route</th>
                <th width="140">IP</th>
                <th>User-Agent</th>
              </tr></thead><tbody>';
        foreach ( $rows as $r ) {
            $ctx = '';
            if ( ! empty($r['context']) ) {
                $decoded = json_decode( $r['context'], true );
                if ( is_array($decoded) ) {
                    $ctx = '<details><summary>Context</summary><pre style="white-space:pre-wrap;">' . esc_html( wp_json_encode($decoded, JSON_PRETTY_PRINT) ) . '</pre></details>';
                }
            }
            echo '<tr>';
            echo '<td>' . esc_html( $r['created_at'] ) . '</td>';
            echo '<td><code>' . esc_html( $r['level'] ) . '</code></td>';
            echo '<td>' . esc_html( $r['msg'] ) . $ctx . '</td>';
            echo '<td><code>' . esc_html( (string) $r['route'] ) . '</code></td>';
            echo '<td><code>' . esc_html( (string) $r['ip'] ) . '</code></td>';
            echo '<td style="max-width:360px;word-break:break-all;">' . esc_html( (string) $r['ua'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Pagination
        $total_pages = max(1, (int) ceil($total / $perpage));
        if ( $total_pages > 1 ) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            $base_url = remove_query_arg(['paged']);
            for ( $p = 1; $p <= $total_pages; $p++ ) {
                $url = esc_url( add_query_arg( 'paged', $p, $base_url ) );
                $class = $p === $paged ? ' class="page-numbers current"' : ' class="page-numbers"';
                echo "<a{$class} href=\"{$url}\">{$p}</a> ";
            }
            echo '</div></div>';
        }
    }

    echo '</div>';
}

/**
 * CSV Export helper
 */
function brs_bfo_export_csv() {
    if ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) {
        wp_die( esc_html__('No permission.', 'brs-block-fake-orders') );
    }
    global $wpdb;
    $table = $wpdb->prefix . 'brs_fo_log';
    $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=brs-fake-orders-log.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','created_at','level','msg','context','route','ip','ua']);
    foreach ( $rows as $r ) {
        fputcsv($out, [
            $r['id'], $r['created_at'], $r['level'], $r['msg'],
            $r['context'], $r['route'], $r['ip'], $r['ua']
        ]);
    }
    fclose($out);
}
