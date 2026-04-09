# Rutas REST — Segurança e Implementações

Este documento descreve as alterações e medidas de segurança aplicadas às rotas REST custom:

Arquivos alterados
- `functions/routes/sobre-mim-route.php`
- `functions/routes/oque-eu-faco-route.php`

Principais implementações

1) CORS restrito
- Onde: ambos os arquivos de rota.
- O que faz: adiciona um filtro `rest_post_dispatch` que insere o header `Access-Control-Allow-Origin` apenas quando a origem da requisição (`HTTP_ORIGIN`) estiver na lista `CLIENT_ALLOWED_ORIGINS`.
- Como configurar: no `wp-config.php`, definir `CLIENT_ALLOWED_ORIGINS` como array de origens permitidas. Ex:

```php
define('CLIENT_ALLOWED_ORIGINS', ['http://localhost:3000']);
```

- Motivo: evita que outras origens possam consumir as respostas via browser, reduzindo risco de uso indevido do frontend hospedado em domínios desconhecidos.

2) Sanitização e Validação explícita
- Onde: `client_update_sobre_mim()` (em `sobre-mim-route.php`) e `client_update_o_que_eu_faco()` (em `oque-eu-faco-route.php`).
- O que faz: os campos são lidos via `$request->get_param()` e sanitizados/validados manualmente:
  - `descricao` -> `wp_kses_post()`
  - URLs -> `esc_url_raw()` + `filter_var(..., FILTER_VALIDATE_URL)`
  - `email` -> `sanitize_email()` + `is_email()` (validação)
  - `tags_tecnologias` -> `sanitize_text_field()` por item
- Motivo: não confiar apenas nos `args` do `register_rest_route` e garantir consistência/validação.

3) Proteção CSRF / Auth (já aplicada previamente)
- As rotas editáveis usam um `permission_callback` que aceita:
  - Application Passwords (Basic auth) para scripts/servers;
  - Ou requisições de navegador autenticadas que incluam `X-WP-Nonce` válido (`wp_verify_nonce($nonce, 'wp_rest')`) e usuário com `manage_options`.
- Motivo: prevenir CSRF e garantir mutações apenas por atores autorizados.

4) Logging / Auditoria
- Onde: após o `update_option()` em ambos os handlers.
- O que faz: registra via `error_log()` uma linha com `user_login`, `user_id`, `REMOTE_ADDR`, timestamp ISO e os dados salvos (`wp_json_encode($options)`).
- Motivo: permitir investigação de alterações críticas.

Comentários no código
- As funções possuem comentários explicativos próximos às alterações (sanitização, CORS, logging).

Como testar
1. Teste de CORS
- Defina `CLIENT_ALLOWED_ORIGINS` para sua origem do frontend (ex.: `http://localhost:3000`).
- Faça um GET/POST via browser do domínio permitido — observe header `Access-Control-Allow-Origin` na resposta.
- Abra o PoC CSRF a partir de outra origem — a resposta não deve conter o header e (no caso de POST) o permission callback deve negar.

2. Teste CSRF
- PoC: `security-notes/csrf-poc.html` (form auto-submetido). Com admin logado, sem nonce, deverá receber 401/403.
- Teste legítimo com nonce: gere `wp_create_nonce('wp_rest')` e envie no header `X-WP-Nonce` — deverá funcionar.

3. Teste de sanitização
- Envie payloads com HTML malicioso nas descrições; verifique que o HTML é filtrado (permitidos apenas tags seguras) ao obter via GET.
- Envie URLs inválidas; a API deve responder com erro 400 para o campo correspondente.

Observações finais
- Para ambientes de produção, considere substituir `error_log()` por um sistema de logs centralizado ou plugin de auditoria.
- Se preferir não expor o `email` publicamente, remova-o da resposta pública nas rotas GET e sirva-o apenas via endpoints autenticados ou use obfuscação no front.


Arquivo(s) modificados:
- `functions/routes/sobre-mim-route.php` — sanitização, cors, logging
- `functions/routes/oque-eu-faco-route.php` — sanitização, cors, logging
- `functions/routes/experiencia-route.php` — rota GET/UPDATE para `experiencia_carreira` (sanitização, CORS, logging, permission_callback)
- `functions/routes/tech-stack-route.php` — rota GET/UPDATE para `tech_stack` (sanitização, CORS, logging, permission_callback)
- `functions/routes/projetos-route.php` — rota GET pública para lista de projetos e rota EDITABLE para criar/atualizar projetos (sanitização, CORS, logging, permission_callback)
- `functions/routes/contato-route.php` — rota GET (token) + POST (formulário de contato) com sistema completo de segurança anti-spam
- `functions/admin/messages/admin-messages.php` — página admin para consulta, gerenciamento e exportação de mensagens recebidas

Novas rotas
- `GET /wp-json/client/v1/experiencia` — retorna `experiencias` (público, campos sanitizados)
- `POST /wp-json/client/v1/experiencia` — atualiza `experiencias` (requer manage_options + nonce ou Application Password)
- `GET /wp-json/client/v1/tech-stack` — retorna array de tecnologias (público)
- `POST /wp-json/client/v1/tech-stack` — atualiza tecnologias (requer manage_options + nonce or Application Password)
- `GET /wp-json/client/v1/projetos` — retorna lista pública de projetos (campos limitados e sanitizados)
- `POST /wp-json/client/v1/projetos` — cria/atualiza projeto (requer manage_options + nonce or Application Password)
- `GET /wp-json/client/v1/contato` — retorna token único de submissão (público, validade 30 min)
- `POST /wp-json/client/v1/contato` — envia mensagem de contato (público, rate-limited, anti-spam)

Rota `/contato` — Camadas de segurança
1. **IP Ban** — IPs banidos recebem resposta 200 silenciosa (sem vazamento de info)
2. **Honeypot** (`_hp`) — campo invisível; bots que preenchem são descartados silenciosamente e logados
3. **Token único** (`_token`) — obtido via GET, consumido no POST; previne replay attacks (expira em 30 min)
4. **Rate limiting** — máximo 3 submissões por IP por hora (transient do WordPress)
5. **Speed check** (`_load_time`) — submissões em menos de 2 s após carregamento são descartadas (bots são rápidos)
6. **Sanitização explícita** — todos os campos são re-sanitizados independente dos `args` do `register_rest_route`
7. **Auto-ban** — IPs com risk_score ≥ 3 são automaticamente banidos após uma submissão

Tabela de mensagens
- `wp_back_end_portfolio_contact_messages` — armazena todas as mensagens recebidas
- Campos: `id`, `created_at`, `ip`, `nome`, `email`, `mensagem`, `status` (novo/lido/spam), `user_agent`, `risk_score`

Página Admin (WP Admin → Mensagens)
- Listagem paginada (20/página) com tabs por status
- Filtros por e-mail e data
- Ações por linha: marcar como lido, marcar como spam (+ bane IP), excluir
- Bulk: limpar spam, exportar CSV, remover por antiguidade
- Badge de contagem de novas mensagens no menu lateral


