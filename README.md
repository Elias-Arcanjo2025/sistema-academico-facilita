# Facilita — Sistema Académico

Plataforma de gestão académica para instituições de ensino em Angola: notas trimestrais (MAC, PP, PT/PG), pautas, boletins, portal do aluno e transição de ano lectivo.

## Requisitos

- PHP 8.2+
- Composer 2
- MySQL 8 / MariaDB
- Node.js 18+ (assets opcionais)
- Laragon, Valet ou servidor equivalente

## Instalação rápida

```bash
composer install
cp .env.example .env
```

**1. Configure o `.env` primeiro** (base de dados, URL, e opcionalmente o perfil de ensino):

```env
ENSINO_PERFIS=ii_ciclo
# Outros exemplos:
# ENSINO_PERFIS=i_ciclo,ii_ciclo
# ENSINO_PERFIS=primario,i_ciclo,ii_ciclo
```

| Valor | Classes |
|-------|---------|
| `primario` | 1.ª–6.ª |
| `i_ciclo` | 7.ª–9.ª |
| `ii_ciclo` | 10.ª–13.ª |

Depois da instalação, no **primeiro login do administrador**, o Facilita abre o **assistente de configuração inicial** (perfis de ensino). Só depois pode criar o ano lectivo e o resto.

Em instalações já em uso, o assistente é ignorado automaticamente.

Também pode alterar os perfis mais tarde em **Configurações → Perfis de ensino**.

**2. Depois** execute os comandos:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan ensino:sincronizar-classes
php artisan storage:link
```

> A ordem importa: o `.env` (incluindo `ENSINO_PERFIS`) deve estar definido **antes** do `migrate --seed`. Se alterar o perfil mais tarde, volte a correr `php artisan ensino:sincronizar-classes` (e `config:clear` se tiver cache).

Contas criadas pelo seeder (`RolesAndPermissionsSeeder`):

| Email | Palavra-passe | Cargo |
|-------|---------------|-------|
| admin@facilita.ao | definida em `FACILITA_SEED_ADMIN_PASSWORD` (ou aleatória no log) | admin_full |
| gestor@facilita.ao | definida em `FACILITA_SEED_GESTOR_PASSWORD` (ou aleatória no log) | gestor_academico |

No primeiro login estas contas **obrigam a alterar a password**. Em ambiente local pode definir no `.env`:

```env
FACILITA_SEED_ADMIN_PASSWORD=sua-senha-local
FACILITA_SEED_GESTOR_PASSWORD=sua-senha-local
```

## Configuração essencial

1. **Ano lectivo** — Configurações → Ano lectivo actual e trimestre.
2. **Instituição** — Nome, logo e contactos (aparecem na página pública).
3. **Página pública** — Configurações → Página pública (textos, carousel, registo).
4. **Queue worker** — Para boletins em lote e jobs IA:
   ```bash
   php artisan queue:work --tries=3
   ```
5. **E-mail** — Configure SMTP no `.env` para notificações de aprovação de contas.
6. **Produção** — `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS activo.

## Variáveis de ambiente relevantes

| Variável | Descrição |
|----------|-----------|
| `ENSINO_PERFIS` | Perfis de ensino: `primario`, `i_ciclo`, `ii_ciclo` (vírgula) |
| `FACILITA_ROUTE_PREFIX` | Prefixo das rotas autenticadas (ex.: `app`) |
| `FACILITA_SIGNED_DOCUMENT_TTL` | Validade de links assinados de boletim (horas) |
| `ASSISTENTE_ENABLED` | Activar/desactivar chat IA |
| `GEMINI_API_KEY` / `OPENROUTER_API_KEY` | Serviços de IA pedagógica |
| `registo_publico_activo` | Config BD — permite registo público (também na UI admin) |

## Testes

```bash
php artisan test
```

## Segurança implementada

- Boletim por URL assinada respeita bloqueio do portal (`consulta_notas_portal`).
- Armazenamento público (`/arquivos/*`) restrito a pastas permitidas.
- Registo público pode ser desactivado; aprovação exige escolha de cargo.
- `UserPolicy` centraliza permissões de utilizadores.
- Bloqueio por inactividade com validação server-side (middleware `CheckSessaoBloqueada`).

## Produção — checklist

1. `APP_DEBUG=false`, `APP_ENV=production`, HTTPS activo.
2. `php artisan migrate --force` e `php artisan config:cache`.
3. Worker de filas: `php artisan queue:work --tries=3` (Supervisor recomendado).
4. Cron diário: `php artisan schedule:run` (agendar no sistema).
5. Backups automáticos via UI em Configurações ou `php artisan backup:run` se configurado.
6. SMTP no `.env` para notificações de contas e alertas de jobs falhados.
7. `ASSISTENTE_ENABLED=true` e `GEMINI_API_KEY` para o chat IA.

## Assistente Facilita e base de dados

O assistente consulta a BD real via **tools server-side** (`AssistantToolRegistry`): horário, provas, notas, rankings, etc. O LLM recebe os resultados em JSON — nunca executa SQL.

## Portal do Aluno — instalar PWA

1. Inicie sessão no portal (`/app/portal/aluno` ou o prefixo configurado).
2. **Android (Chrome/Edge):** aparece o banner «Instalar» ou use o menu ⋮ → «Instalar aplicação» / «Adicionar ao ecrã principal».
3. **iPhone (Safari):** botão Partilhar → «Adicionar ao Ecrã de Início».
4. **Desktop (Chrome/Edge):** ícone de instalação na barra de endereço (⊕).

Requisitos: HTTPS (ou `localhost` em desenvolvimento) e manifesto em `/app/manifest-portal.webmanifest`.

## Estrutura principal

- `app/Services/PautaService.php` — Cálculos e regras ministeriais
- `app/Http/Controllers/Portal/AlunoPortalController.php` — Portal do aluno
- `resources/views/welcome.blade.php` — Página pública
- `database/seeders/RolesAndPermissionsSeeder.php` — Cargos e permissões

## Controlo Facilita (multi-instalação)

**Não é multi-tenant.** Cada escola tem a sua instalação e BD. O hub é **outro site** (outro domínio/servidor) só para ti controlares licenças e cobranças.

| `CONTROL_MODE` | Função |
|----------------|--------|
| `off` | Desactivado (defeito) |
| `school` | Instalação da escola — envia heartbeat/alunos e respeita licença |
| `hub` | Site do painel central (sem menu académico) |

**Deploy recomendado**
1. Site hub (ex.: `controlo.facilita.ao`) → `CONTROL_MODE=hub` + BD própria  
2. Cada escola (ex.: `escola-x.ao`) → `CONTROL_MODE=school` + `CONTROL_HUB_URL` + ID + secret  

No hub, login redirecciona para o Controlo; rotas académicas ficam bloqueadas.  
Sync: `php artisan control:sync` (a cada 30 min).  
API: `POST /api/control/v1/heartbeat`, `POST /api/control/v1/alunos/sync`, `GET /api/control/v1/license`.

**Cobrança:** hub → escola → **Criar cobrança**. A escola vê banner. Sem pagamento → atraso → **Bloquear**. **Marcar como pago** reactiva.

## Inovação (diferenciação)

| Módulo | Onde |
|--------|------|
| **Pauta Certificada** (DE 424/25) | Análise → `/app/pauta-certificada` — selo, simulador de conselho, export JSON inspecção |
| **Radar Pedagógico** | `/app/radar` — turmas em risco, MAC/PP em atraso, benchmark hub |
| **Portal Encarregado** | `/app/portal/encarregado` — notas, boletim QR, assinatura digital |
| **WhatsApp** | Webhook `/webhooks/whatsapp` · `WHATSAPP_*=` no `.env` · provider `log` em dev |

Gestão de encarregados: Administração → Encarregados.

## Licença

Uso interno / institucional — consulte o responsável do projecto.
