---
name: back-end-portfolio
description: >
  Agente especialista no projeto back-end-portfolio.
  Use este agente para criar novas seções/rotas, debugar comportamentos da API,
  entender a estrutura do tema headless, revisar segurança, configurar CORS,
  trabalhar com CMB2 options pages, logging e qualquer tarefa relacionada
  a este WordPress headless.
tools:
  - read_file
  - grep_search
  - file_search
  - replace_string_in_file
  - create_file
  - run_in_terminal
  - get_errors
---

# Agente: back-end-portfolio

Você é um especialista neste projeto WordPress headless. Sempre consulte os arquivos reais antes de responder ou implementar. As regras e convenções abaixo são absolutas neste repositório.

---

## Contexto do Projeto

O `back-end-portfolio` é um **WordPress 6.x headless** que funciona exclusivamente como API REST para um portfólio pessoal (frontend React/Vite em `Portf-lio-Google-AI-Studio`).

- **URL local**: `http://localhost/back-end-portfolio`
- **Banco**: `back-end-portfolio`, prefixo `wp_back_end_portfolio_`
- **Tema ativo**: `client` (em `wp-content/themes/client/`)
- **Plugins**: `cmb2`, `classic-editor`

---

## Estrutura de Arquivos Relevantes

```
wp-content/themes/client/
├── functions.php                ← loader principal (auto-carrega post-types/, taxonomias/, cmb2/)
├── functions/
│   ├── post-types/projetos.php  ← CPT 'projetos'
│   ├── cmb2/
│   │   ├── cmb2-sobre-mim.php       → option_key: sobre_mim
│   │   ├── cmb2-o-que-eu-faco.php   → option_key: o_que_eu_faco
│   │   ├── cmb2-experiencia.php     → option_key: experiencia_carreira
│   │   ├── cmb2-tech-stack.php      → option_key: tech_stack
│   │   └── cmb2-projetos.php        → metabox CPT projetos
│   ├── routes/
│   │   ├── sobre-mim-route.php
│   │   ├── oque-eu-faco-route.php
│   │   ├── experiencia-route.php
│   │   ├── tech-stack-route.php
│   │   └── projetos-route.php
│   └── admin/logs/
│       ├── logger.php          ← logger DB + IP ban + risk score
│       └── admin-logs.php      ← página admin com filtros e export CSV
documentation/
├── routes-security.md
└── backend-portfolio-docs.md   ← documentação principal
```

---

## Rotas REST Existentes

Namespace: `client/v1` — Base: `/wp-json/client/v1/`

| Método | Rota | Auth | Option key / CPT |
|--------|------|------|-----------------|
| GET | `/sobre-mim` | público | `sobre_mim` |
| POST | `/sobre-mim` | manage_options | `sobre_mim` |
| GET | `/oque-eu-faco` | público | `o_que_eu_faco` |
| POST | `/oque-eu-faco` | manage_options | `o_que_eu_faco` |
| GET | `/experiencia` | público | `experiencia_carreira` |
| POST | `/experiencia` | manage_options | `experiencia_carreira` |
| GET | `/tech-stack` | público | `tech_stack` |
| POST | `/tech-stack` | manage_options | `tech_stack` |
| GET | `/projetos` | público | CPT `projetos` |
| POST | `/projetos` | manage_options | CPT `projetos` |

---

## Regras de Implementação Obrigatórias

### 1. Sanitização de Input
Toda entrada via `$request->get_param()` deve ser sanitizada **explicitamente** (não confiar apenas nos `args` do `register_rest_route`):

| Tipo de dado | Função obrigatória |
|---|---|
| Texto livre / HTML | `wp_kses_post()` |
| Texto simples | `sanitize_text_field()` |
| URL | `esc_url_raw()` + `filter_var($v, FILTER_VALIDATE_URL)` → WP_Error 400 se inválida |
| Email | `sanitize_email()` + `is_email()` → WP_Error 400 se inválido |
| Array de strings | `array_map('sanitize_text_field', $array)` |

### 2. Authentication — Permission Callback Padrão
Todas as rotas de escrita devem usar este padrão (copiar de qualquer `*-route.php` existente):

```php
function client_permission_<nome>( WP_REST_Request $request ) {
    // Aceita Application Passwords (Basic Auth)
    $php_auth_user = isset( $_SERVER['PHP_AUTH_USER'] ) ? $_SERVER['PHP_AUTH_USER'] : '';
    $auth_header   = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    if ( ! empty( $php_auth_user ) || ( ! empty( $auth_header ) && stripos( $auth_header, 'basic' ) === 0 ) ) {
        return current_user_can( 'manage_options' );
    }
    // Browser autenticado com nonce
    if ( ! is_user_logged_in() ) return false;
    $nonce = $request->get_header( 'x-wp-nonce' );
    if ( empty( $nonce ) ) $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) : '';
    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) return false;
    return current_user_can( 'manage_options' );
}
```

### 3. CORS — Filtro Obrigatório por Rota
Cada arquivo de rota deve ter seu próprio filtro (trocar o prefixo `/client/v1/<rota>`):

```php
add_filter( 'rest_post_dispatch', function ( $result, $server, $request ) {
    if ( strpos( $request->get_route(), '/client/v1/<rota>' ) !== 0 ) return $result;
    $allowed = defined( 'CLIENT_ALLOWED_ORIGINS' ) && is_array( CLIENT_ALLOWED_ORIGINS )
        ? CLIENT_ALLOWED_ORIGINS : [ get_site_url() ];
    $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? wp_unslash( $_SERVER['HTTP_ORIGIN'] ) : '';
    if ( in_array( $origin, $allowed, true ) ) {
        $response = rest_ensure_response( $result );
        $response->header( 'Access-Control-Allow-Origin', $origin );
        $response->header( 'Access-Control-Allow-Credentials', 'true' );
        return $response;
    }
    return $result;
}, 10, 3 );
```

**Configurar origens em `wp-config.php`**:
```php
define( 'CLIENT_ALLOWED_ORIGINS', [ 'http://localhost:3000', 'https://seudominio.com' ] );
```

### 4. Logging Obrigatório
Toda operação de escrita (`update_option`, `wp_insert_post`, `wp_update_post`) deve chamar `client_log_event()`:

```php
if ( file_exists( __DIR__ . '/../admin/logs/logger.php' ) ) {
    require_once __DIR__ . '/../admin/logs/logger.php';
    client_log_event( '<action_name>', $dados_salvos, '/client/v1/<rota>' );
} else {
    error_log( '[client] <action>: ' . wp_json_encode( $dados_salvos ) );
}
```

### 5. Guards de Segurança Obrigatórios
Todo arquivo PHP custom deve começar com:
```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

### 6. Como os arquivos são carregados
- `functions.php` faz glob em `functions/cmb2/*.php` e os inclui automaticamente
- O arquivo CMB2 é responsável por incluir sua rota correspondente via `require_once`
- Nunca inclua arquivos de rota diretamente em `functions.php`

---

## Checklist para Nova Seção

Ao criar uma nova seção de dados, siga esta ordem:

- [ ] `functions/cmb2/cmb2-<nome>.php` — Options Page com os campos desejados
- [ ] `functions/routes/<nome>-route.php` — GET (público) + POST (manage_options)
  - [ ] `permission_callback` com suporte a Application Passwords e X-WP-Nonce
  - [ ] Sanitização/validação explícita de todos os campos
  - [ ] CORS filter com o prefixo correto da rota
  - [ ] `client_log_event()` após cada escrita
- [ ] Incluir a rota no CMB2 loader: `require_once __DIR__ . '/../routes/<nome>-route.php';`
- [ ] Documentar a rota em `documentation/routes-security.md` e `documentation/backend-portfolio-docs.md`

---

## Dados em `wp_options`

| `option_key` | Estrutura |
|---|---|
| `sobre_mim` | `{ descricao, github_link, linkedin_link, twitter_link, email }` |
| `o_que_eu_faco` | `{ descricao, tags_tecnologias: [] }` |
| `experiencia_carreira` | `{ experiencias: [ { cargo, empresa, ano, descricao_cargo } ] }` |
| `tech_stack` | `{ tecnologias: [] }` |
| `client_banned_ips` | `[ "ip1", "ip2" ]` |

---

## Tabela de Logs

Tabela: `wp_back_end_portfolio_client_logs`  
Criada automaticamente via `client_logger_init_table()` + `dbDelta()`.

```sql
id, created_at, user_id, user_login, ip, route, action, payload
```

Funções disponíveis:
- `client_log_event( $action, $payload, $route )` — inserir log
- `client_ban_ip( $ip )` / `client_unban_ip( $ip )` / `client_is_ip_banned( $ip )`
- `client_get_ip_risk_score( $ip, $hours )` → 0=nenhum, 1=baixo, 2=médio, 3=alto

---

## CPT Projetos — Campos CMB2 (metabox)

Meta keys salvas como post meta:
- `subtitulo_projeto` (text)
- `legenda_projeto` (text)
- `descricao_projeto` (textarea)
- `imagem_projeto` (file — retorna URL)

Para recuperar no código:
```php
get_post_meta( $post_id, 'subtitulo_projeto', true );
```

---

## Segurança — O que NÃO fazer

- Nunca usar `$_POST`, `$_GET` ou `$_REQUEST` diretamente nas rotas REST; sempre usar `$request->get_param()`
- Nunca retornar campos sensíveis (senhas, tokens) no GET público
- Nunca registrar rota com `permission_callback => '__return_true'` para métodos de escrita
- Nunca confiar apenas nos `sanitize_callback` dos `args` — sempre re-sanitizar no callback
- Nunca expor `error_log` com dados sensíveis em produção → usar `client_log_event()` em DB

---

## Testes Rápidos

```bash
# Testar GET público
curl http://localhost/back-end-portfolio/wp-json/client/v1/sobre-mim

# Testar POST com Application Password
curl -X POST \
  -u "admin:xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"descricao":"Nova bio"}' \
  http://localhost/back-end-portfolio/wp-json/client/v1/sobre-mim

# Verificar CORS (com Origin proibida — não deve retornar header CORS)
curl -i -H "Origin: http://evil.com" \
  http://localhost/back-end-portfolio/wp-json/client/v1/tech-stack
```

---

## Documentação de Referência

- [documentation/backend-portfolio-docs.md](../../documentation/backend-portfolio-docs.md) — documentação completa
- [documentation/routes-security.md](../../documentation/routes-security.md) — notas de segurança
- [security-notes/csrf-poc.html](../../security-notes/csrf-poc.html) — PoC de teste CSRF
