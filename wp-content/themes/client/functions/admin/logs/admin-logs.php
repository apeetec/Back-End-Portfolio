<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

    // Handle actions: export, cleanup, ban, unban
    if ( isset( $_POST['client_action'] ) && check_admin_referer( 'client_logs_action', 'client_logs_nonce' ) ) {
        $act = sanitize_text_field( $_POST['client_action'] );
        if ( $act === 'export' ) {
            // build same query as listing and output CSV
            $where = client_logs_build_where( $wpdb, $table );
            $rows = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC", ARRAY_A );
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=client_logs.csv' );
            $out = fopen( 'php://output', 'w' );
            fputcsv( $out, array( 'created_at', 'user_id', 'user_login', 'ip', 'route', 'action', 'payload' ) );
            foreach ( $rows as $r ) {
                fputcsv( $out, array_values( $r ) );
            }
            exit;
        }

        if ( $act === 'cleanup' && isset( $_POST['days'] ) ) {
            $days = intval( $_POST['days'] );
            if ( $days > 0 ) {
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < (NOW() - INTERVAL %d DAY)", $days ) );
                echo '<div class="updated notice"><p>Registros anteriores a ' . esc_html( $days ) . ' dias removidos.</p></div>';
            }
        }

        if ( $act === 'ban' && ! empty( $_POST['ip'] ) ) {
            $ip = sanitize_text_field( $_POST['ip'] );
            client_ban_ip( $ip );
            echo '<div class="updated notice"><p>IP ' . esc_html( $ip ) . ' banido.</p></div>';
        }

        if ( $act === 'unban' && ! empty( $_POST['ip'] ) ) {
            $ip = sanitize_text_field( $_POST['ip'] );
            client_unban_ip( $ip );
            echo '<div class="updated notice"><p>IP ' . esc_html( $ip ) . ' removido da lista de ban.</p></div>';
        }
    }

    // Filters
    $route_filter = isset( $_GET['route'] ) ? sanitize_text_field( $_GET['route'] ) : '';
    $user_filter = isset( $_GET['user'] ) ? sanitize_text_field( $_GET['user'] ) : '';
    $from = isset( $_GET['from'] ) ? sanitize_text_field( $_GET['from'] ) : '';
    $to = isset( $_GET['to'] ) ? sanitize_text_field( $_GET['to'] ) : '';

    // Build where clause and params
    $where = client_logs_build_where( $wpdb, $table );

    $per_page = 25;
    $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $offset = ( $paged - 1 ) * $per_page;

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

    // Distinct routes for filter dropdown
    $routes = $wpdb->get_col( "SELECT DISTINCT route FROM {$table} ORDER BY route" );

    echo '<div class="wrap"><h1>Client Logs</h1>';

    // Filters form
    echo '<form method="get" class="client-logs-filters">';
    echo '<input type="hidden" name="page" value="client-logs" />';
    echo '<label>Rota: <select name="route"><option value="">(todas)</option>';
    foreach ( $routes as $r ) {
        $sel = $r === $route_filter ? ' selected' : '';
        echo '<option value="' . esc_attr( $r ) . '"' . $sel . '>' . esc_html( $r ) . '</option>';
    }
    echo '</select></label> ';
    echo '<label>Usuário: <input type="text" name="user" value="' . esc_attr( $user_filter ) . '" /></label> ';
    echo '<label>De: <input type="date" name="from" value="' . esc_attr( $from ) . '" /></label> ';
    echo '<label>Até: <input type="date" name="to" value="' . esc_attr( $to ) . '" /></label> ';
    echo '<button class="button">Filtrar</button>';
    echo '</form>';

    // Actions: export, cleanup
    echo '<form method="post" style="margin-top:10px">';
    wp_nonce_field( 'client_logs_action', 'client_logs_nonce' );
    echo '<input type="hidden" name="client_action" value="export" />';
    echo '<button class="button">Exportar (CSV)</button>';
    echo '</form>';

    echo '<form method="post" style="margin-top:10px">';
    wp_nonce_field( 'client_logs_action', 'client_logs_nonce' );
    echo 'Remover logs mais antigos que <input type="number" name="days" value="90" style="width:80px" /> dias ';
    echo '<input type="hidden" name="client_action" value="cleanup" />';
    echo '<button class="button">Remover</button>';
    echo '</form>';

    echo '<p>Total registros: ' . esc_html( $total ) . '</p>';

    echo '<table class="widefat fixed" cellspacing="0"><thead><tr>';
    echo '<th>Data</th><th>User</th><th>IP</th><th>Risco</th><th>Route</th><th>Action</th><th>Payload</th><th>Ações</th></tr></thead><tbody>';

    if ( empty( $rows ) ) {
        echo '<tr><td colspan="8">Nenhum registro</td></tr>';
    } else {
        foreach ( $rows as $r ) {
            $payload = isset( $r['payload'] ) ? $r['payload'] : '';
            $short = strlen( $payload ) > 200 ? substr( $payload, 0, 200 ) . '...' : $payload;
            $risk = client_get_ip_risk_score( $r['ip'], 24 );
            $risk_label = array( 'Nenhum', 'Baixo', 'Médio', 'Alto' );
            $risk_color = array( '#777', '#2b8c2b', '#e6a700', '#c02b2b' );

            echo '<tr>';
            echo '<td>' . esc_html( $r['created_at'] ) . '</td>';
            echo '<td>' . esc_html( $r['user_login'] ) . ' (' . intval( $r['user_id'] ) . ')</td>';
            echo '<td>' . esc_html( $r['ip'] ) . '</td>';
            echo '<td><span style="color:' . esc_attr( $risk_color[ $risk ] ) . '">' . esc_html( $risk_label[ $risk ] ) . '</span></td>';
            echo '<td>' . esc_html( $r['route'] ) . '</td>';
            echo '<td>' . esc_html( $r['action'] ) . '</td>';
            echo '<td><div class="client-payload-short">' . esc_html( $short ) . '</div>';
            echo '<div class="client-payload-full" style="display:none"><pre>' . esc_html( $payload ) . '</pre></div>';
            echo '<a href="#" class="client-toggle-payload">Ver</a></td>';
            // Actions: ban/unban
            $banned = client_is_ip_banned( $r['ip'] );
            echo '<td>';
            echo '<form method="post" style="display:inline">';
            wp_nonce_field( 'client_logs_action', 'client_logs_nonce' );
            echo '<input type="hidden" name="client_action" value="' . ( $banned ? 'unban' : 'ban' ) . '" />';
            echo '<input type="hidden" name="ip" value="' . esc_attr( $r['ip'] ) . '" />';
            echo '<button class="button">' . ( $banned ? 'Desbanir' : 'Banir IP' ) . '</button>';
            echo '</form>';
            echo '</td>';
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
                echo '<a class="pagination-links" href="' . esc_url( add_query_arg( array( 'paged' => $p ) ) ) . '">' . $p . '</a> ';
            }
        }
        echo '</div></div>';
    }

    // Simple JS for toggling payload
    echo '<script>document.addEventListener("click",function(e){if(e.target&&e.target.classList&&e.target.classList.contains("client-toggle-payload")){e.preventDefault();var parent=e.target.closest("td");var full=parent.querySelector(".client-payload-full");if(full.style.display==="none"){full.style.display="block";e.target.textContent="Ocultar";}else{full.style.display="none";e.target.textContent="Ver";}}});</script>';

    echo '</div>';
}

function client_logs_build_where( $wpdb, $table ) {
    $clauses = array();
    if ( ! empty( $_GET['route'] ) ) {
        $clauses[] = $wpdb->prepare( "route = %s", sanitize_text_field( $_GET['route'] ) );
    }
    if ( ! empty( $_GET['user'] ) ) {
        $user = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['user'] ) ) . '%';
        $clauses[] = $wpdb->prepare( "user_login LIKE %s", $user );
    }
    if ( ! empty( $_GET['from'] ) ) {
        $from = date( 'Y-m-d 00:00:00', strtotime( sanitize_text_field( $_GET['from'] ) ) );
        $clauses[] = $wpdb->prepare( "created_at >= %s", $from );
    }
    if ( ! empty( $_GET['to'] ) ) {
        $to = date( 'Y-m-d 23:59:59', strtotime( sanitize_text_field( $_GET['to'] ) ) );
        $clauses[] = $wpdb->prepare( "created_at <= %s", $to );
    }
    if ( empty( $clauses ) ) {
        return '';
    }
    return 'WHERE ' . implode( ' AND ', $clauses );
}
