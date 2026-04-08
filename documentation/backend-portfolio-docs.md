# back-end-portfolio — Documentação Completa

> WordPress headless atuando como API REST para o portfólio pessoal.

---

## Sumário

1. [Visão Geral](#visão-geral)
2. [Estrutura de Diretórios](#estrutura-de-diretórios)
3. [Configuração](#configuração)
4. [Plugins Instalados](#plugins-instalados)
5. [Tema `client` — Arquitetura Interna](#tema-client--arquitetura-interna)
6. [Post Types Customizados](#post-types-customizados)
7. [CMB2 — Options Pages e Metaboxes](#cmb2--options-pages-e-metaboxes)
8. [API REST — Rotas `client/v1`](#api-rest--rotas-clientv1)
9. [Autenticação e Permissões](#autenticação-e-permissões)
10. [CORS](#cors)
11. [Sistema de Logging e Auditoria](#sistema-de-logging-e-auditoria)
12. [Segurança Aplicada](#segurança-aplicada)
13. [Fluxo de Dados](#fluxo-de-dados)
14. [Como Adicionar Novas Seções](#como-adicionar-novas-seções)

---

## Visão Geral

O `back-end-portfolio` é uma instalação WordPress configurada como **CMS headless**. Ele expõe dados do portfólio (sobre mim, projetos, experiências, tech stack, etc.) via **WordPress REST API** com namespace próprio (`client/v1`). O front-end React (`Portf-lio-Google-AI-Studio`) consome essas rotas.

```
Frontend (React/Vite)
        │
        │  HTTP GET → /wp-json/client/v1/*
        │
        ▼
WordPress REST API
        │
        ▼
 Theme "client" (functions/)
        │
   ┌────┴──────┐
   │  CMB2     │  → armazena dados em wp_options / CPT
   │  Routes   │  → expõe e atualiza dados
   │  Logger   │  → registra mutações em DB
   └───────────┘
```

---

## Estrutura de Diretórios

```
back-end-portfolio/
├── wp-config.php                    ← configuração principal (DB, salts, CORS origins)
├── documentation/
│   ├── routes-security.md           ← notas de segurança das rotas
│   └── backend-portfolio-docs.md    ← este arquivo
├── security-notes/
│   └── csrf-poc.html                ← PoC de teste CSRF
└── wp-content/
    ├── plugins/
    │   ├── classic-editor/          ← editor clássico WP
    │   └── cmb2/                    ← Custom Meta Boxes 2
    └── themes/
        └── client/                  ← TEMA PRINCIPAL (toda a lógica custom)
            ├── functions.php        ← loader principal
            ├── functions/
            │   ├── post-types/
            │   │   └── projetos.php           ← CPT 'projetos'
            │   ├── taxonomias/                ← taxonomias (vazio por padrão)
            │   ├── cmb2/
            │   │   ├── cmb2-sobre-mim.php     ← options page + carrega rota
            │   │   ├── cmb2-o-que-eu-faco.php ← options page + carrega rota
            │   │   ├── cmb2-experiencia.php   ← options page
            │   │   ├── cmb2-tech-stack.php    ← options page
            │   │   └── cmb2-projetos.php      ← metabox do CPT projetos
            │   ├── routes/
            │   │   ├── sobre-mim-route.php
            │   │   ├── oque-eu-faco-route.php
            │   │   ├── experiencia-route.php
            │   │   ├── tech-stack-route.php
            │   │   └── projetos-route.php
            │   ├── admin/
            │   │   └── logs/
            │   │       ├── logger.php          ← logger completo (IP ban, risk score)
            │   │       └── admin-logs.php      ← página admin com filtros + export CSV
            │   ├── logger.php                  ← legacy (delega para admin/logs/logger.php)
            │   └── admin-logs.php              ← legacy (versão simples)
            └── documentation/
```

---

## Configuração

### `wp-config.php`

| Parâmetro | Valor |
|-----------|-------|
| `DB_NAME` | `back-end-portfolio` |
| `DB_USER` | `root` |
| `DB_PASSWORD` | `""` (sem senha, ambiente local) |
| `DB_HOST` | `localhost` |
| `table_prefix` | `wp_back_end_portfolio_` |

### Constante `CLIENT_ALLOWED_ORIGINS`

Define as origens CORS permitidas para as rotas `client/v1`. Deve ser configurada em `wp-config.php`:

```php
define( 'CLIENT_ALLOWED_ORIGINS', [
    'http://localhost:3000',   // dev frontend
    'https://meudominio.com',  // produção
] );
```

Se não definida, o padrão é `[ get_site_url() ]`.

---

## Plugins Instalados

| Plugin | Função |
|--------|--------|
| `classic-editor` | Mantém o editor clássico do WP no lugar do Gutenberg |
| `cmb2` | Cria metaboxes, options pages e campos customizados no admin |

---

## Tema `client` — Arquitetura Interna

### `functions.php` — Loader Principal

Responsável por:
- Desabilitar XML-RPC (`add_filter('xmlrpc_enabled', '__return_false')`)
- Remover header `X-Pingback`
- Desabilitar comentários em todos os post types
- Adicionar `X-Frame-Options: SAMEORIGIN`
- Ocultar admin bar no front-end
- **Auto-carregar** todos os arquivos `.php` de:
  - `functions/post-types/`
  - `functions/taxonomias/`
  - `functions/cmb2/`

Os arquivos de **routes** são carregados pelos próprios arquivos CMB2 (ex.: `cmb2-sobre-mim.php` inclui `routes/sobre-mim-route.php`), exceto as rotas `experiencia`, `tech-stack` e `projetos`, que são auto-carregadas via glob.

---

## Post Types Customizados

### `projetos` (`functions/post-types/projetos.php`)

| Parâmetro | Valor |
|-----------|-------|
| Slug | `projetos` |
| Público | `false` (não há template de front-end WP) |
| Show UI | `true` (aparece no admin) |
| REST API | `true` (acessível via API autenticada) |
| Suportes | title, editor, thumbnail, custom-fields, revisions |
| Ícone | `dashicons-portfolio` |

---

## CMB2 — Options Pages e Metaboxes

Todos os dados não-CPT são armazenados em `wp_options` via CMB2.

### Options Pages

| Arquivo | `option_key` | Campos | Posição menu |
|---------|-------------|--------|--------------|
| `cmb2-sobre-mim.php` | `sobre_mim` | descricao (textarea), github_link, linkedin_link, twitter_link, email | 26 |
| `cmb2-o-que-eu-faco.php` | `o_que_eu_faco` | descricao (textarea), tags_tecnologias (repeatable text) | 25 |
| `cmb2-experiencia.php` | `experiencia_carreira` | experiencias (grupo repetível: cargo, empresa, ano, descricao_cargo) | 24 |
| `cmb2-tech-stack.php` | `tech_stack` | tecnologias (repeatable text) | 27 |

### Metabox de Projetos

Arquivo: `cmb2-projetos.php`  
Tipo: metabox para o CPT `projetos`

| Campo | ID | Tipo |
|-------|----|------|
| Subtítulo | `subtitulo_projeto` | text |
| Legenda | `legenda_projeto` | text |
| Descrição | `descricao_projeto` | textarea |
| Imagem | `imagem_projeto` | file (upload) |

---

## API REST — Rotas `client/v1`

Base URL: `http://localhost/back-end-portfolio/wp-json/client/v1`

### Tabela de Rotas

| Método | Endpoint | Auth | Descrição |
|--------|----------|------|-----------|
| GET | `/sobre-mim` | Público | Retorna dados pessoais (bio, links, email) |
| POST | `/sobre-mim` | `manage_options` | Atualiza dados pessoais |
| GET | `/oque-eu-faco` | Público | Retorna descrição profissional e tech tags |
| POST | `/oque-eu-faco` | `manage_options` | Atualiza descrição e tags |
| GET | `/experiencia` | Público | Retorna lista de experiências de carreira |
| POST | `/experiencia` | `manage_options` | Substitui toda a lista de experiências |
| GET | `/tech-stack` | Público | Retorna array de tecnologias |
| POST | `/tech-stack` | `manage_options` | Atualiza lista de tecnologias |
| GET | `/projetos` | Público | Retorna projetos publicados (campos limitados) |
| POST | `/projetos` | `manage_options` | Cria ou atualiza um projeto (upsert por `id`) |

### Detalhes por Rota

#### GET `/sobre-mim`
Resposta:
```json
{
  "descricao": "...",
  "github_link": "https://github.com/user",
  "linkedin_link": "https://linkedin.com/in/user",
  "twitter_link": "https://twitter.com/user",
  "email": "user@example.com"
}
```

#### POST `/sobre-mim`
Campos aceitos no body (todos opcionais):
```json
{
  "descricao": "HTML seguro permitido",
  "github_link": "https://...",
  "linkedin_link": "https://...",
  "twitter_link": "https://...",
  "email": "user@example.com"
}
```

#### GET `/experiencia`
Resposta (array):
```json
[
  {
    "cargo": "Desenvolvedor Full Stack",
    "empresa": "Empresa X",
    "ano": "2022 - Atual",
    "descricao_cargo": "Responsável por..."
  }
]
```

#### POST `/experiencia`
Body: `{ "experiencias": [ { "cargo": "...", "empresa": "...", "ano": "...", "descricao_cargo": "..." } ] }`  
Substitui toda a lista armazenada.

#### GET `/tech-stack`
Resposta: `["PHP", "JavaScript", "React", "WordPress"]`

#### POST `/tech-stack`
Body: `{ "tecnologias": ["PHP", "React", "Node.js"] }` ou string separada por vírgulas.

#### GET `/projetos`
Resposta (campos limitados — seguro para exposição pública):
```json
[
  {
    "id": 42,
    "title": "Portfólio",
    "excerpt": "Primeiras 40 palavras...",
    "thumbnail": "https://.../image.jpg"
  }
]
```

#### POST `/projetos`
- **Criar**: body sem `id` → `{ "title": "...", "content": "..." }`
- **Atualizar**: body com `id` → `{ "id": 42, "title": "...", "content": "..." }`
- Retorna: `{ "success": true, "id": 42 }`

---

## Autenticação e Permissões

Todas as rotas de escrita (POST/PUT) utilizam um `permission_callback` próprio que aceita **dois métodos**:

### 1. Application Passwords (recomendado para scripts/automações)
- Enviar `Authorization: Basic base64(username:app-password)` no header
- Ou via `PHP_AUTH_USER` + `PHP_AUTH_PW`
- O usuário precisa ter a capability `manage_options`

```bash
# Exemplo com curl
curl -X POST \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"tecnologias":["PHP","React"]}' \
  http://localhost/back-end-portfolio/wp-json/client/v1/tech-stack
```

### 2. Cookie + X-WP-Nonce (para chamadas do browser autenticado)
- O usuário precisa estar logado no WordPress
- Gerar nonce: `wp_create_nonce('wp_rest')`
- Enviar no header: `X-WP-Nonce: <nonce>`

```javascript
// Exemplo fetch com nonce (contexto de admin WP)
fetch('/wp-json/client/v1/sobre-mim', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce,
  },
  body: JSON.stringify({ descricao: 'Nova bio...' }),
  credentials: 'include',
});
```

---

## CORS

Cada arquivo de rota registra um filtro `rest_post_dispatch` que verifica `HTTP_ORIGIN` contra `CLIENT_ALLOWED_ORIGINS`.

**Comportamento:**
- Se a origem estiver na lista → adiciona `Access-Control-Allow-Origin: <origin>` + `Access-Control-Allow-Credentials: true`
- Se não estiver → nenhum header CORS é adicionado (browser bloqueará)

**Configuração em `wp-config.php`:**
```php
define( 'CLIENT_ALLOWED_ORIGINS', [
    'http://localhost:3000',
    'https://meuportfolio.com',
] );
```

---

## Sistema de Logging e Auditoria

### Tabela `wp_back_end_portfolio_client_logs`

Criada automaticamente na primeira operação de escrita via `dbDelta()`.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | bigint | PK auto-increment |
| `created_at` | datetime | Timestamp da ação |
| `user_id` | bigint | ID do usuário WP |
| `user_login` | varchar(191) | Login do usuário |
| `ip` | varchar(45) | IP do requisitante |
| `route` | varchar(191) | Rota acessada |
| `action` | varchar(191) | Ação realizada |
| `payload` | longtext | Dados salvos (JSON) |

### Funções Disponíveis (`admin/logs/logger.php`)

```php
// Registrar evento
client_log_event( 'action_name', $payload_array, '/client/v1/route' );

// Banir/desbanir IP
client_ban_ip( $ip );
client_unban_ip( $ip );
client_is_ip_banned( $ip );   // bool

// Score de risco por IP (0=nenhum, 1=baixo, 2=médio, 3=alto)
client_get_ip_risk_score( $ip, $hours = 24 );
```

### Painel Admin — Client Logs

Acesso: `WP Admin → Client Logs` (menu lateral, posição 75)

Funcionalidades:
- Listagem paginada (25/página) com filtros por rota, usuário, data
- Exportação para CSV
- Limpeza por antiguidade (ex.: remover logs > 30 dias)
- Banimento/remoção de ban de IPs

---

## Segurança Aplicada

| Medida | Onde | Função |
|--------|------|--------|
| XML-RPC desabilitado | `functions.php` | Evita vetores de ataque via xmlrpc |
| X-Pingback removido | `functions.php` | Evita exposição de endpoint |
| X-Frame-Options: SAMEORIGIN | `functions.php` | Previne clickjacking |
| Comentários desabilitados | `functions.php` | Reduz superfície de ataque |
| CORS restrito | Cada `*-route.php` | Apenas origens autorizadas recebem headers CORS |
| CSRF (nonce) | `permission_callback` | Browser requests precisam de `X-WP-Nonce` válido |
| Sanitização de input | Callbacks de update | `wp_kses_post`, `sanitize_text_field`, `esc_url_raw`, `sanitize_email` |
| Validação de URL | Callbacks de update | `FILTER_VALIDATE_URL` + retorna WP_Error 400 |
| Validação de email | Callbacks de update | `is_email()` + retorna WP_Error 400 |
| Auditoria | Após cada `update_option` | `client_log_event()` registra em DB |
| IP Ban | `admin/logs/logger.php` | Lista de IPs banidos em `wp_options` |
| Risk Score | `admin/logs/logger.php` | Score baseado em volume de eventos por IP |

---

## Fluxo de Dados

### Leitura (Frontend → API)

```
1. React faz GET /wp-json/client/v1/<rota>
2. WordPress autentica o namespace 'client/v1'
3. permission_callback: __return_true (público)
4. callback GET: busca dados do wp_options ou CPT
5. Dados são sanitizados na saída
6. Filtro CORS verifica e adiciona header se origem permitida
7. JSON retornado ao frontend
```

### Escrita (Admin/Script → API)

```
1. Cliente POST /wp-json/client/v1/<rota>
2. permission_callback verifica:
   a. Basic Auth → Application Password → current_user_can('manage_options')
   b. Cookie + X-WP-Nonce → wp_verify_nonce + current_user_can('manage_options')
3. Se não autorizado → 401/403
4. callback POST:
   a. Lê parâmetros via $request->get_param()
   b. Sanitiza/valida cada campo
   c. Retorna WP_Error 400 se validação falhar
   d. update_option() ou wp_insert_post() / wp_update_post()
   e. client_log_event() registra auditoria
5. Filtro CORS adiciona header se origem permitida
6. Resposta JSON { success: true, data: ... }
```

---

## Como Adicionar Novas Seções

### Passo a passo para criar uma nova seção de dados

1. **Criar a Options Page no CMB2** (`functions/cmb2/cmb2-<nome>.php`):
```php
add_action( 'cmb2_admin_init', 'cmb2_<nome>_options_page' );
function cmb2_<nome>_options_page() {
    $opts = new_cmb2_box( [
        'id'           => '<nome>_options',
        'title'        => 'Título da Seção',
        'object_types' => [ 'options-page' ],
        'option_key'   => '<nome_chave>',
        'menu_title'   => 'Nome no Menu',
        'position'     => 28,
    ] );
    $opts->add_field( [ 'id' => 'campo', 'name' => '...', 'type' => 'text' ] );
}
// Carregar a rota
if ( file_exists( __DIR__ . '/../routes/<nome>-route.php' ) ) {
    require_once __DIR__ . '/../routes/<nome>-route.php';
}
```

2. **Criar a rota REST** (`functions/routes/<nome>-route.php`):
```php
add_action( 'rest_api_init', function () {
    register_rest_route( 'client/v1', '/<nome>', [
        [ 'methods' => WP_REST_Server::READABLE,  'callback' => 'client_get_<nome>',    'permission_callback' => '__return_true' ],
        [ 'methods' => WP_REST_Server::EDITABLE,  'callback' => 'client_update_<nome>', 'permission_callback' => 'client_permission_<nome>' ],
    ] );
} );

function client_get_<nome>( WP_REST_Request $req ) {
    return rest_ensure_response( get_option( '<nome_chave>', [] ) );
}

function client_update_<nome>( WP_REST_Request $req ) {
    $val = sanitize_text_field( $req->get_param( 'campo' ) );
    update_option( '<nome_chave>', [ 'campo' => $val ] );
    client_log_event( '<nome>_update', [ 'campo' => $val ], '/client/v1/<nome>' );
    return rest_ensure_response( [ 'success' => true ] );
}

function client_permission_<nome>( WP_REST_Request $req ) {
    // copiar o padrão de qualquer outro *-route.php existente
}
```

3. **Adicionar CORS** ao arquivo de rota (copiar o bloco `add_filter('rest_post_dispatch', ...)` de outro arquivo trocando o prefixo da rota).

4. **Documentar** a nova rota neste arquivo e em `documentation/routes-security.md`.
