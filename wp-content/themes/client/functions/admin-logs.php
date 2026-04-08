<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require logger utilities
if ( file_exists( __DIR__ . '/logger.php' ) ) {
    require_once __DIR__ . '/logger.php';
}

add_action( 'admin_menu', 'client_logs_admin_menu' );
function client_logs_admin_menu() {
    add_menu_page(
        'Client Logs',
        'Client Logs',
        'manage_options',
        'client-logs',
        'client_logs_page',
        'dashicons-list-view',
        75
    );
}

function client_logs_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sem permissão.' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'client_logs';
    client_logger_init_table();

    $per_page = 25;
    $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $offset = ( $paged - 1 ) * $per_page;

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

    $base_url = admin_url( 'admin.php?page=client-logs' );

    echo '<div class="wrap"><h1>Client Logs</h1>';
    echo '<p>Total registros: ' . esc_html( $total ) . '</p>';

    echo '<table class="widefat fixed" cellspacing="0"><thead><tr>';
    echo '<th>Data</th><th>User</th><th>IP</th><th>Route</th><th>Action</th><th>Payload</th></tr></thead><tbody>';

    if ( empty( $rows ) ) {
        echo '<tr><td colspan="6">Nenhum registro</td></tr>';
    } else {
        foreach ( $rows as $r ) {
            $payload = isset( $r['payload'] ) ? $r['payload'] : '';
            $short = strlen( $payload ) > 200 ? substr( $payload, 0, 200 ) . '...' : $payload;
            echo '<tr>';
            echo '<td>' . esc_html( $r['created_at'] ) . '</td>';
            echo '<td>' . esc_html( $r['user_login'] ) . ' (' . intval( $r['user_id'] ) . ')</td>';
            echo '<td>' . esc_html( $r['ip'] ) . '</td>';
            echo '<td>' . esc_html( $r['route'] ) . '</td>';
            echo '<td>' . esc_html( $r['action'] ) . '</td>';
            echo '<td><div class="client-payload-short">' . esc_html( $short ) . '</div>';
            echo '<div class="client-payload-full" style="display:none"><pre>' . esc_html( $payload ) . '</pre></div>';
            echo '<a href="#" class="client-toggle-payload">Ver</a></td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';

    // Pagination
    $total_pages = (int) ceil( $total / $per_page );
    if ( $total_pages > 1 ) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ( $p = 1; $p <= $total_pages; $p++ ) {
            if ( $p === $paged ) {
                echo '<span class="pagination-links current">' . $p . '</span> ';
            } else {
                echo '<a class="pagination-links" href="' . esc_url( add_query_arg( 'paged', $p, $base_url ) ) . '">' . $p . '</a> ';
            }
        }
        echo '</div></div>';
    }

    // Simple JS for toggling payload
    echo '<script>document.addEventListener("click",function(e){if(e.target&&e.target.classList&&e.target.classList.contains("client-toggle-payload")){e.preventDefault();var parent=e.target.closest("td");var full=parent.querySelector(".client-payload-full");if(full.style.display==="none"){full.style.display="block";e.target.textContent="Ocultar";}else{full.style.display="none";e.target.textContent="Ver";}}});</script>';

    echo '</div>';
}
