<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load logger functions (client_ban_ip, client_is_ip_banned, etc.)
$_client_logger = __DIR__ . '/../../admin/logs/logger.php';
if ( file_exists( $_client_logger ) ) {
    require_once $_client_logger;
}
unset( $_client_logger );

// ── Admin Menu ────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'client_messages_admin_menu' );

function client_messages_admin_menu() {
    $new_count = client_messages_new_count();
    $bubble    = $new_count > 0
        ? ' <span class="awaiting-mod count-' . intval( $new_count ) . '"><span class="pending-count">' . intval( $new_count ) . '</span></span>'
        : '';

    add_menu_page(
        'Mensagens de Contato',
        'Mensagens' . $bubble,
        'manage_options',
        'client-messages',
        'client_messages_page',
        'dashicons-email-alt',
        74
    );
}

function client_messages_new_count() {
    global $wpdb;
    $table = $wpdb->prefix . 'contact_messages';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return 0;
    }
    return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'novo'" );
}

// ── Main Page ─────────────────────────────────────────────────────────────────

function client_messages_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sem permissão.' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'contact_messages';

    // Ensure table exists
    if ( function_exists( 'client_contato_init_table' ) ) {
        client_contato_init_table();
    }

    // ── Handle POST actions ───────────────────────────────────────────────────
    if ( isset( $_POST['client_msg_action'] ) && check_admin_referer( 'client_messages_action', 'client_messages_nonce' ) ) {
        $act = sanitize_text_field( wp_unslash( $_POST['client_msg_action'] ) );

        // ── Export CSV ────────────────────────────────────────────────────────
        if ( $act === 'export' ) {
            $where = client_messages_build_where( $wpdb, $table );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC", ARRAY_A );
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=contact-messages-' . date( 'Y-m-d' ) . '.csv' );
            $out = fopen( 'php://output', 'w' );
            fputcsv( $out, array( 'ID', 'Data', 'IP', 'Nome', 'E-mail', 'Mensagem', 'Status', 'Risco', 'User-Agent' ) );
            foreach ( $rows as $r ) {
                fputcsv( $out, array(
                    $r['id'], $r['created_at'], $r['ip'], $r['nome'],
                    $r['email'], $r['mensagem'], $r['status'], $r['risk_score'], $r['user_agent'],
                ) );
            }
            fclose( $out );
            exit;
        }

        // ── Mark as read ──────────────────────────────────────────────────────
        if ( $act === 'mark_read' && ! empty( $_POST['msg_id'] ) ) {
            $id = intval( $_POST['msg_id'] );
            $wpdb->update( $table, array( 'status' => 'lido' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
            echo '<div class="notice notice-success is-dismissible"><p>Mensagem marcada como lida.</p></div>';
        }

        // ── Mark as spam + ban IP ────────────────────────────────────────────
        if ( $act === 'mark_spam' && ! empty( $_POST['msg_id'] ) ) {
            $id  = intval( $_POST['msg_id'] );
            $msg = $wpdb->get_row( $wpdb->prepare( "SELECT ip FROM {$table} WHERE id = %d", $id ), ARRAY_A );
            $wpdb->update( $table, array( 'status' => 'spam' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
            $banned_ip = '';
            if ( $msg && ! empty( $msg['ip'] ) && function_exists( 'client_ban_ip' ) ) {
                client_ban_ip( $msg['ip'] );
                $banned_ip = $msg['ip'];
            }
            $msg_text = 'Mensagem marcada como spam.';
            if ( $banned_ip ) {
                $msg_text .= ' IP <strong>' . esc_html( $banned_ip ) . '</strong> banido.';
            }
            echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $msg_text ) . '</p></div>';
        }

        // ── Delete ────────────────────────────────────────────────────────────
        if ( $act === 'delete' && ! empty( $_POST['msg_id'] ) ) {
            $id = intval( $_POST['msg_id'] );
            $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
            echo '<div class="notice notice-success is-dismissible"><p>Mensagem excluída.</p></div>';
        }

        // ── Bulk delete (spam cleanup) ────────────────────────────────────────
        if ( $act === 'delete_spam' ) {
            $deleted = $wpdb->query( "DELETE FROM {$table} WHERE status = 'spam'" );
            echo '<div class="notice notice-success is-dismissible"><p>' . intval( $deleted ) . ' mensagens de spam excluídas.</p></div>';
        }

        // ── Cleanup by age ────────────────────────────────────────────────────
        if ( $act === 'cleanup' && isset( $_POST['days'] ) ) {
            $days    = intval( $_POST['days'] );
            if ( $days > 0 ) {
                $deleted = $wpdb->query( $wpdb->prepare(
                    "DELETE FROM {$table} WHERE created_at < (NOW() - INTERVAL %d DAY)",
                    $days
                ) );
                echo '<div class="notice notice-success is-dismissible"><p>' . intval( $deleted ) . ' mensagens anteriores a ' . esc_html( $days ) . ' dias removidas.</p></div>';
            }
        }
    }

    // ── Counts ────────────────────────────────────────────────────────────────
    $count_novo  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'novo'" );
    $count_lido  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'lido'" );
    $count_spam  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'spam'" );
    $count_total = $count_novo + $count_lido + $count_spam;

    // ── Reading filters ───────────────────────────────────────────────────────
    $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
    $email_filter  = isset( $_GET['email'] )  ? sanitize_text_field( wp_unslash( $_GET['email'] ) )  : '';
    $from          = isset( $_GET['from'] )   ? sanitize_text_field( wp_unslash( $_GET['from'] ) )   : '';
    $to            = isset( $_GET['to'] )     ? sanitize_text_field( wp_unslash( $_GET['to'] ) )     : '';

    $where    = client_messages_build_where( $wpdb, $table );
    $per_page = 20;
    $paged    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
    $offset   = ( $paged - 1 ) * $per_page;

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $rows  = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ),
        ARRAY_A
    );

    // ── Page output ───────────────────────────────────────────────────────────
    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Mensagens de Contato</h1>';
    if ( $count_novo > 0 ) {
        echo ' <span style="background:#E8175D;border-radius:16px;padding:3px 10px;color:#fff;font-size:12px;font-weight:600;vertical-align:middle">'
             . intval( $count_novo ) . ' nova(s)</span>';
    }
    echo '<hr class="wp-header-end">';

    // Status tabs
    $tabs = array(
        ''      => 'Todas (' . $count_total . ')',
        'novo'  => 'Novas (' . $count_novo . ')',
        'lido'  => 'Lidas (' . $count_lido . ')',
        'spam'  => 'Spam (' . $count_spam . ')',
    );
    echo '<ul class="subsubsub">';
    foreach ( $tabs as $val => $label ) {
        $url    = esc_url( add_query_arg( array( 'page' => 'client-messages', 'status' => $val ), admin_url( 'admin.php' ) ) );
        $active = $val === $status_filter ? ' class="current"' : '';
        echo '<li><a href="' . $url . '"' . $active . '>' . esc_html( $label ) . '</a>';
        if ( array_key_last( $tabs ) !== $val ) {
            echo ' | ';
        }
        echo '</li>';
    }
    echo '</ul><br class="clear">';

    // Filter form
    echo '<form method="get" style="margin-bottom:12px">';
    echo '<input type="hidden" name="page" value="client-messages" />';
    if ( $status_filter ) {
        echo '<input type="hidden" name="status" value="' . esc_attr( $status_filter ) . '" />';
    }
    echo '<label>E-mail: <input type="text" name="email" value="' . esc_attr( $email_filter ) . '" style="width:200px" /></label> ';
    echo '<label>De: <input type="date" name="from" value="' . esc_attr( $from ) . '" /></label> ';
    echo '<label>Até: <input type="date" name="to" value="' . esc_attr( $to ) . '" /></label> ';
    echo '<button class="button">Filtrar</button>';
    if ( $email_filter || $from || $to ) {
        $clear_url = esc_url( add_query_arg( array( 'page' => 'client-messages', 'status' => $status_filter ), admin_url( 'admin.php' ) ) );
        echo ' <a href="' . $clear_url . '" class="button button-secondary">Limpar</a>';
    }
    echo '</form>';

    // Bulk actions row
    echo '<div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap">';

    echo '<form method="post" style="display:inline">';
    wp_nonce_field( 'client_messages_action', 'client_messages_nonce' );
    echo '<input type="hidden" name="client_msg_action" value="export" />';
    if ( $status_filter ) {
        echo '<input type="hidden" name="status_filter" value="' . esc_attr( $status_filter ) . '" />';
    }
    echo '<button class="button">⬇ Exportar CSV</button>';
    echo '</form>';

    if ( $count_spam > 0 ) {
        echo '<form method="post" style="display:inline">';
        wp_nonce_field( 'client_messages_action', 'client_messages_nonce' );
        echo '<input type="hidden" name="client_msg_action" value="delete_spam" />';
        echo '<button class="button button-secondary" onclick="return confirm(\'Excluir todas as mensagens de spam?\')">🗑 Limpar spam (' . intval( $count_spam ) . ')</button>';
        echo '</form>';
    }

    echo '<form method="post" style="display:inline;margin-left:auto">';
    wp_nonce_field( 'client_messages_action', 'client_messages_nonce' );
    echo 'Remover mensagens com mais de <input type="number" name="days" value="180" style="width:70px" /> dias ';
    echo '<input type="hidden" name="client_msg_action" value="cleanup" />';
    echo '<button class="button button-secondary">Remover</button>';
    echo '</form>';

    echo '</div>';

    echo '<p style="color:#666;font-size:13px">Exibindo <strong>' . intval( $total ) . '</strong> mensagens.</p>';

    // ── Table ─────────────────────────────────────────────────────────────────
    echo '<table class="widefat fixed striped" cellspacing="0">';
    echo '<thead><tr>';
    echo '<th style="width:130px">Data</th>';
    echo '<th style="width:130px">Nome</th>';
    echo '<th style="width:180px">E-mail</th>';
    echo '<th>Mensagem</th>';
    echo '<th style="width:70px">Status</th>';
    echo '<th style="width:50px">Risco</th>';
    echo '<th style="width:160px">Ações</th>';
    echo '</tr></thead><tbody>';

    $status_cfg = array(
        'novo'  => array( 'label' => 'Novo',  'color' => '#2271b1' ),
        'lido'  => array( 'label' => 'Lido',  'color' => '#51bb25' ),
        'spam'  => array( 'label' => 'Spam',  'color' => '#c02b2b' ),
    );
    $risk_cfg = array(
        0 => array( 'label' => '—',     'color' => '#999' ),
        1 => array( 'label' => 'Baixo', 'color' => '#2b8c2b' ),
        2 => array( 'label' => 'Médio', 'color' => '#e6a700' ),
        3 => array( 'label' => 'Alto',  'color' => '#c02b2b' ),
    );

    if ( empty( $rows ) ) {
        echo '<tr><td colspan="7" style="text-align:center;padding:30px;color:#666">Nenhuma mensagem encontrada.</td></tr>';
    } else {
        foreach ( $rows as $r ) {
            $s_cfg    = isset( $status_cfg[ $r['status'] ] ) ? $status_cfg[ $r['status'] ] : array( 'label' => $r['status'], 'color' => '#999' );
            $risk_val = min( 3, max( 0, (int) $r['risk_score'] ) );
            $r_cfg    = $risk_cfg[ $risk_val ];
            $short    = mb_strlen( $r['mensagem'] ) > 100
                ? mb_substr( $r['mensagem'], 0, 100 ) . '…'
                : $r['mensagem'];
            $is_new   = $r['status'] === 'novo';
            $row_style = $is_new ? ' style="font-weight:600;background:#f0f6fc"' : '';

            echo '<tr' . $row_style . '>';
            echo '<td style="font-size:12px">' . esc_html( $r['created_at'] ) . '</td>';
            echo '<td>' . esc_html( $r['nome'] ) . '</td>';
            echo '<td style="word-break:break-all"><a href="mailto:' . esc_attr( $r['email'] ) . '">' . esc_html( $r['email'] ) . '</a></td>';

            echo '<td>';
            echo '<div class="client-msg-short">' . esc_html( $short ) . '</div>';
            if ( mb_strlen( $r['mensagem'] ) > 100 ) {
                echo '<div class="client-msg-full" style="display:none;white-space:pre-wrap">' . esc_html( $r['mensagem'] ) . '</div>';
                echo ' <a href="#" class="client-toggle-msg" data-id="' . intval( $r['id'] ) . '" style="font-size:11px;color:#2271b1">Ver mais</a>';
            }
            if ( ! empty( $r['user_agent'] ) ) {
                echo '<div style="font-size:10px;color:#aaa;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . esc_attr( $r['user_agent'] ) . '">' . esc_html( mb_substr( $r['user_agent'], 0, 80 ) ) . '</div>';
            }
            echo '</td>';

            echo '<td><span style="color:' . esc_attr( $s_cfg['color'] ) . ';font-weight:600">' . esc_html( $s_cfg['label'] ) . '</span></td>';
            echo '<td><span style="color:' . esc_attr( $r_cfg['color'] ) . '">' . esc_html( $r_cfg['label'] ) . '</span></td>';

            echo '<td>';

            if ( $r['status'] !== 'lido' && $r['status'] !== 'spam' ) {
                echo '<form method="post" style="display:inline;margin-right:3px">';
                wp_nonce_field( 'client_messages_action', 'client_messages_nonce' );
                echo '<input type="hidden" name="client_msg_action" value="mark_read" />';
                echo '<input type="hidden" name="msg_id" value="' . intval( $r['id'] ) . '" />';
                echo '<button class="button button-small" title="Marcar como lido">✓</button>';
                echo '</form>';
            }

            if ( $r['status'] !== 'spam' ) {
                echo '<form method="post" style="display:inline;margin-right:3px">';
                wp_nonce_field( 'client_messages_action', 'client_messages_nonce' );
                echo '<input type="hidden" name="client_msg_action" value="mark_spam" />';
                echo '<input type="hidden" name="msg_id" value="' . intval( $r['id'] ) . '" />';
                echo '<button class="button button-small button-secondary" title="Marcar como spam e banir IP">⛔</button>';
                echo '</form>';
            }

            echo '<form method="post" style="display:inline">';
            wp_nonce_field( 'client_messages_action', 'client_messages_nonce' );
            echo '<input type="hidden" name="client_msg_action" value="delete" />';
            echo '<input type="hidden" name="msg_id" value="' . intval( $r['id'] ) . '" />';
            echo '<button class="button button-small" title="Excluir" onclick="return confirm(\'Excluir permanentemente?\')">✕</button>';
            echo '</form>';

            echo '</td></tr>';
        }
    }

    echo '</tbody></table>';

    // ── Pagination ────────────────────────────────────────────────────────────
    $total_pages = (int) ceil( $total / $per_page );
    if ( $total_pages > 1 ) {
        echo '<div class="tablenav bottom"><div class="tablenav-pages">';
        $base_args = array( 'page' => 'client-messages' );
        if ( $status_filter ) {
            $base_args['status'] = $status_filter;
        }
        if ( $email_filter ) {
            $base_args['email'] = $email_filter;
        }
        if ( $from ) {
            $base_args['from'] = $from;
        }
        if ( $to ) {
            $base_args['to'] = $to;
        }
        for ( $p = 1; $p <= $total_pages; $p++ ) {
            if ( $p === $paged ) {
                echo '<span class="page-numbers current">' . $p . '</span> ';
            } else {
                $purl = esc_url( add_query_arg( array_merge( $base_args, array( 'paged' => $p ) ), admin_url( 'admin.php' ) ) );
                echo '<a class="page-numbers" href="' . $purl . '">' . $p . '</a> ';
            }
        }
        echo '</div></div>';
    }

    // ── Inline JS ─────────────────────────────────────────────────────────────
    ?>
    <script>
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('client-toggle-msg')) {
            e.preventDefault();
            var td    = e.target.closest('td');
            var full  = td.querySelector('.client-msg-full');
            var short = td.querySelector('.client-msg-short');
            var open  = full.style.display !== 'none';
            full.style.display  = open ? 'none'  : 'block';
            short.style.display = open ? 'block' : 'none';
            e.target.textContent = open ? 'Ver mais' : 'Ver menos';
        }
    });
    </script>
    <?php

    echo '</div>'; // .wrap
}

// ── WHERE builder ─────────────────────────────────────────────────────────────

function client_messages_build_where( $wpdb, $table ) {
    $clauses = array();

    if ( ! empty( $_GET['status'] ) ) {
        $clauses[] = $wpdb->prepare( 'status = %s', sanitize_text_field( wp_unslash( $_GET['status'] ) ) );
    }
    if ( ! empty( $_POST['status_filter'] ) ) {
        // Used during CSV export to pass current filter
        $clauses[] = $wpdb->prepare( 'status = %s', sanitize_text_field( wp_unslash( $_POST['status_filter'] ) ) );
    }
    if ( ! empty( $_GET['email'] ) ) {
        $like      = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['email'] ) ) ) . '%';
        $clauses[] = $wpdb->prepare( 'email LIKE %s', $like );
    }
    if ( ! empty( $_GET['from'] ) ) {
        $from      = date( 'Y-m-d 00:00:00', (int) strtotime( sanitize_text_field( wp_unslash( $_GET['from'] ) ) ) );
        $clauses[] = $wpdb->prepare( 'created_at >= %s', $from );
    }
    if ( ! empty( $_GET['to'] ) ) {
        $to        = date( 'Y-m-d 23:59:59', (int) strtotime( sanitize_text_field( wp_unslash( $_GET['to'] ) ) ) );
        $clauses[] = $wpdb->prepare( 'created_at <= %s', $to );
    }

    if ( empty( $clauses ) ) {
        return '';
    }
    return 'WHERE ' . implode( ' AND ', $clauses );
}
