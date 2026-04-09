<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST routes for the contact form.
 *
 * GET  /wp-json/client/v1/contato  → returns a one-time submission token (public)
 * POST /wp-json/client/v1/contato  → process form submission (public, rate-limited)
 *
 * Security layers:
 *  1. IP ban check       — banned IPs receive a silent 200 (no info leakage).
 *  2. Honeypot field     — bots that fill '_hp' are silently dropped + logged.
 *  3. One-time token     — token fetched via GET, consumed on POST (anti-replay).
 *  4. Rate limiting      — max 3 submissions per IP per hour (transient-based).
 *  5. Speed check        — submissions under 2 s from page load are silently dropped.
 *  6. Input sanitization — every field is explicitly sanitized/validated.
 *  7. Auto-ban           — IPs with risk_score >= 3 are automatically banned.
 */

// ── DB Table ──────────────────────────────────────────────────────────────────

if ( ! function_exists( 'client_contato_init_table' ) ) {
function client_contato_init_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'contact_messages';
    $charset = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
        return $table;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (
        id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at  datetime            NOT NULL,
        ip          varchar(45)         DEFAULT '',
        nome        varchar(255)        DEFAULT '',
        email       varchar(255)        DEFAULT '',
        mensagem    longtext,
        status      varchar(20)         DEFAULT 'novo',
        user_agent  varchar(500)        DEFAULT '',
        risk_score  tinyint(1)          DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_status     (status),
        KEY idx_created_at (created_at),
        KEY idx_ip         (ip)
    ) {$charset};";

    dbDelta( $sql );
    return $table;
}
}

// ── Rate Limiting (max 3 / hour / IP) ────────────────────────────────────────

if ( ! function_exists( 'client_contato_is_rate_limited' ) ) {
function client_contato_is_rate_limited( $ip ) {
    $key = 'client_contato_rate_' . md5( $ip );
    return (int) get_transient( $key ) >= 3;
}
}

if ( ! function_exists( 'client_contato_increment_rate' ) ) {
function client_contato_increment_rate( $ip ) {
    $key   = 'client_contato_rate_' . md5( $ip );
    $count = (int) get_transient( $key );
    if ( 0 === $count ) {
        set_transient( $key, 1, HOUR_IN_SECONDS );
    } else {
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    }
}
}

// ── One-Time Token ────────────────────────────────────────────────────────────

if ( ! function_exists( 'client_contato_generate_token' ) ) {
function client_contato_generate_token() {
    $token = wp_generate_password( 40, false );
    set_transient( 'client_contato_token_' . $token, 1, 30 * MINUTE_IN_SECONDS );
    return $token;
}
}

if ( ! function_exists( 'client_contato_validate_and_consume_token' ) ) {
function client_contato_validate_and_consume_token( $token ) {
    if ( empty( $token ) || strlen( $token ) > 60 ) {
        return false;
    }
    $key = 'client_contato_token_' . sanitize_text_field( $token );
    $ok  = (bool) get_transient( $key );
    if ( $ok ) {
        delete_transient( $key ); // single-use
    }
    return $ok;
}
}

// ── Logger helper (loads from shared logger) ──────────────────────────────────

if ( ! function_exists( 'client_contato_load_logger' ) ) {
function client_contato_load_logger() {
    $path = __DIR__ . '/../admin/logs/logger.php';
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}
}

// ── REST Routes ───────────────────────────────────────────────────────────────

add_action( 'rest_api_init', function () {

    // GET → issue a one-time token
    register_rest_route( 'client/v1', '/contato', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'client_contato_get_token_route',
        'permission_callback' => '__return_true',
    ) );

    // POST → process form submission
    register_rest_route( 'client/v1', '/contato', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'client_contato_send',
        'permission_callback' => '__return_true',
        'args'                => array(
            'nome'       => array( 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ),
            'email'      => array( 'required' => true,  'sanitize_callback' => 'sanitize_email' ),
            'mensagem'   => array( 'required' => true,  'sanitize_callback' => 'sanitize_textarea_field' ),
            '_token'     => array( 'required' => true ),
            '_hp'        => array( 'required' => false, 'default' => '' ),
            '_load_time' => array( 'required' => false, 'default' => 0 ),
        ),
    ) );
} );

// ── GET callback — token endpoint ─────────────────────────────────────────────

function client_contato_get_token_route( WP_REST_Request $request ) {
    return rest_ensure_response( array( 'token' => client_contato_generate_token() ) );
}

// ── POST callback — form submission ───────────────────────────────────────────

function client_contato_send( WP_REST_Request $request ) {
    global $wpdb;

    $ip = isset( $_SERVER['REMOTE_ADDR'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
        : '';
    $ua = isset( $_SERVER['HTTP_USER_AGENT'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
        : '';

    // Load shared logger functions
    client_contato_load_logger();

    // ── 1. IP Ban check ───────────────────────────────────────────────────────
    if ( function_exists( 'client_is_ip_banned' ) && client_is_ip_banned( $ip ) ) {
        // Silent success — no information leakage to automated tools
        return rest_ensure_response( array( 'success' => true ) );
    }

    // ── 2. Honeypot check (_hp must be empty) ─────────────────────────────────
    $hp = (string) $request->get_param( '_hp' );
    if ( ! empty( $hp ) ) {
        if ( function_exists( 'client_log_event' ) ) {
            client_log_event(
                'contato_honeypot',
                array( 'ip' => $ip, 'ua' => substr( $ua, 0, 200 ) ),
                '/client/v1/contato'
            );
        }
        // Silent success — bot filled the trap
        return rest_ensure_response( array( 'success' => true ) );
    }

    // ── 3. Token validation (anti-replay) ─────────────────────────────────────
    $token = (string) $request->get_param( '_token' );
    if ( ! client_contato_validate_and_consume_token( $token ) ) {
        return new WP_Error(
            'invalid_token',
            'Token inválido ou expirado. Recarregue a página e tente novamente.',
            array( 'status' => 403 )
        );
    }

    // ── 4. Rate limiting ─────────────────────────────────────────────────────
    if ( client_contato_is_rate_limited( $ip ) ) {
        if ( function_exists( 'client_log_event' ) ) {
            client_log_event(
                'contato_rate_limited',
                array( 'ip' => $ip ),
                '/client/v1/contato'
            );
        }
        return new WP_Error(
            'rate_limited',
            'Muitas tentativas. Aguarde antes de enviar novamente.',
            array( 'status' => 429 )
        );
    }

    // ── 5. Speed check (< 2 s → likely bot) ──────────────────────────────────
    $load_time = (int) $request->get_param( '_load_time' );
    if ( $load_time > 0 ) {
        $elapsed_ms = (int) ( microtime( true ) * 1000 ) - $load_time;
        if ( $elapsed_ms < 2000 ) {
            if ( function_exists( 'client_log_event' ) ) {
                client_log_event(
                    'contato_bot_speed',
                    array( 'ip' => $ip, 'elapsed_ms' => $elapsed_ms ),
                    '/client/v1/contato'
                );
            }
            // Silent success to avoid tipping off bots
            return rest_ensure_response( array( 'success' => true ) );
        }
    }

    // ── 6. Explicit field sanitization & validation ───────────────────────────
    $nome = sanitize_text_field( (string) $request->get_param( 'nome' ) );
    if ( empty( $nome ) ) {
        return new WP_Error( 'missing_nome', 'Nome é obrigatório.', array( 'status' => 400 ) );
    }
    if ( mb_strlen( $nome ) > 200 ) {
        return new WP_Error( 'invalid_nome', 'Nome muito longo (máx 200 caracteres).', array( 'status' => 400 ) );
    }

    $email_raw = (string) $request->get_param( 'email' );
    $email     = sanitize_email( $email_raw );
    if ( empty( $email ) || ! is_email( $email ) ) {
        return new WP_Error( 'invalid_email', 'E-mail inválido.', array( 'status' => 400 ) );
    }

    $mensagem = sanitize_textarea_field( (string) $request->get_param( 'mensagem' ) );
    if ( empty( $mensagem ) ) {
        return new WP_Error( 'missing_mensagem', 'Mensagem é obrigatória.', array( 'status' => 400 ) );
    }
    if ( mb_strlen( $mensagem ) > 5000 ) {
        return new WP_Error(
            'invalid_mensagem',
            'Mensagem muito longa (máx 5000 caracteres).',
            array( 'status' => 400 )
        );
    }

    // ── 7. Risk score from existing logger ───────────────────────────────────
    $risk = function_exists( 'client_get_ip_risk_score' )
        ? (int) client_get_ip_risk_score( $ip, 24 )
        : 0;

    // ── 8. Persist to DB ─────────────────────────────────────────────────────
    $table = client_contato_init_table();
    $ok = $wpdb->insert(
        $table,
        array(
            'created_at' => current_time( 'mysql' ),
            'ip'         => $ip,
            'nome'       => $nome,
            'email'      => $email,
            'mensagem'   => $mensagem,
            'status'     => 'novo',
            'user_agent' => mb_substr( $ua, 0, 500 ),
            'risk_score' => $risk,
        ),
        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
    );

    if ( false === $ok ) {
        error_log( '[client] contato_send DB insert failed: ' . $wpdb->last_error );
        return new WP_Error( 'db_error', 'Erro interno. Tente novamente.', array( 'status' => 500 ) );
    }

    $msg_id = (int) $wpdb->insert_id;

    // ── 9. Increment rate counter ────────────────────────────────────────────
    client_contato_increment_rate( $ip );

    // ── 10. Email notification to admin ──────────────────────────────────────
    $admin_email = get_option( 'admin_email' );
    if ( ! empty( $admin_email ) ) {
        $subject = sprintf( '[Portfólio] Nova mensagem de %s', $nome );
        $body    = sprintf(
            "Nova mensagem de contato recebida:\n\nNome: %s\nE-mail: %s\nIP: %s\nRisco: %d\n\nMensagem:\n%s\n\n---\nVer no admin: %s",
            $nome,
            $email,
            $ip,
            $risk,
            $mensagem,
            admin_url( 'admin.php?page=client-messages' )
        );
        wp_mail( $admin_email, $subject, $body );
    }

    // ── 11. Audit log ────────────────────────────────────────────────────────
    if ( function_exists( 'client_log_event' ) ) {
        client_log_event(
            'contato_recebido',
            array( 'msg_id' => $msg_id, 'nome' => $nome, 'email' => $email, 'ip' => $ip, 'risk' => $risk ),
            '/client/v1/contato'
        );
    }

    // ── 12. Auto-ban high-risk IPs ───────────────────────────────────────────
    if ( $risk >= 3 && function_exists( 'client_ban_ip' ) ) {
        client_ban_ip( $ip );
        if ( function_exists( 'client_log_event' ) ) {
            client_log_event(
                'contato_auto_ban',
                array( 'ip' => $ip, 'risk' => $risk ),
                '/client/v1/contato'
            );
        }
    }

    return rest_ensure_response( array( 'success' => true ) );
}

// ── CORS ──────────────────────────────────────────────────────────────────────

add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    if ( strpos( $request->get_route(), '/client/v1/contato' ) !== 0 ) {
        return $result;
    }
    $allowed = defined( 'CLIENT_ALLOWED_ORIGINS' ) && is_array( CLIENT_ALLOWED_ORIGINS )
        ? CLIENT_ALLOWED_ORIGINS
        : array( get_site_url() );
    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';
    if ( in_array( $origin, $allowed, true ) ) {
        $response = rest_ensure_response( $result );
        $response->header( 'Access-Control-Allow-Origin', $origin );
        $response->header( 'Access-Control-Allow-Credentials', 'true' );
        return $response;
    }
    return $result;
}, 10, 3 );

// ── Admin Page Loader ────────────────────────────────────────────────────────

if ( is_admin() && file_exists( __DIR__ . '/../admin/messages/admin-messages.php' ) ) {
    require_once __DIR__ . '/../admin/messages/admin-messages.php';
}
