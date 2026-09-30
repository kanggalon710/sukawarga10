# Audit Remediation and New Application Plan

Updated: 2026-09-30 by codex-gpt-5 (Codex)

## Purpose

This is the execution document for two related outcomes:

1. Harden and improve the existing Sukawarga application without disrupting its
   production data or tenant isolation.
2. Use the verified result as the source for a new application with a different
   domain, brand, color system, logo, configuration, secrets, and empty dataset.

All agents working on either outcome must read, in this order:

1. `AGENTS.md`
2. `.ai/STATE.md`
3. This document
4. `.ai/TODO.md`
5. Relevant entries in `.ai/DECISIONS.md`

This plan does not authorize an agent to deploy, rewrite git history, rotate a
production secret, copy production data, or push directly to a protected branch
without explicit human approval.

## Executive decision

Use the existing repository as the canonical implementation while remediation is
in progress. Do not create the new branded application from the current commit.

After remediation is complete:

1. Verify the original application in local and staging environments.
2. Merge the work through reviewed pull requests.
3. Deploy and verify the existing application.
4. Mark the verified commit with a template-ready release tag.
5. Create the new application from a clean snapshot of that tag in a new private
   repository with fresh history, secrets, storage, database, and administrator.

Do not fork or clone the old public git history into the new repository. The old
history is known to contain a default administrative credential and previously
contained resident data. A clean snapshot avoids carrying that history into the
new product.

## Decision gate: tenant or separate application

A different domain, logo, colors, and data do not by themselves require a second
codebase. This application already supports organizations, domains, tenant data
scope, tenant settings, feature flags, and per-tenant letterhead logos.

Choose a new tenant in the existing deployment when all of these are true:

- The same owner operates both installations.
- The same release schedule and feature set are acceptable.
- Sharing one deployment and database server is acceptable.
- Logical tenant isolation is sufficient.
- The new domain can be routed to the existing application.

Choose a separate application and repository when any of these are true:

- It has a different legal owner or operational team.
- It requires independent deployment timing or feature development.
- It needs separate database, backups, secrets, storage, or incident boundaries.
- It may be sold, licensed, or maintained independently.
- A failure or compromise must not affect the original platform.

The current user plan says "new app," so this document recommends a separate
private repository created from a clean, verified snapshot. The reusable branding
work should still be implemented in the original first so future applications do
not require search-and-replace changes throughout the codebase.

## Current verified baseline

- Git remote: `git@github.com:kanggalon710/sukawarga10.git`.
- GitHub repository visibility: public.
- `main` and `dev` were at `f1f3084` during the audit.
- `production` was one commit behind that commit.
- Production responds at `https://desa.jabnet.id`.
- PHP 8.3.35 satisfies the current Composer platform requirements.
- Fresh SQLite migrations and seeding succeeded.
- 174 PHP files passed syntax validation.
- 406 tests and 1,585 assertions ran without failures using a temporary APP_KEY,
  but the run produced 348 warnings because no `.env` existed.
- `composer test` from the unprepared checkout failed because APP_KEY was absent.
- `vendor/bin/pint --test` reported 34 files.
- Composer reported two low-severity advisories in Laravel and Flysystem.
- Browser samples at 360, 768, and 1280 px had no horizontal overflow or console
  errors, but accessibility and touch-target failures remain.

The detailed evidence and backlog are in `.ai/STATE.md`, `.ai/TODO.md`, and the
2026-09-30 entry in `.ai/PROGRESS.md`.

## Target architecture for reuse

The reusable source should separate four categories clearly.

### Application core

Shared domain rules, authorization, tenant isolation, billing, letters, reports,
audit logging, upload handling, notifications, and tests. These remain in code and
must not contain a customer or deployment identity.

### Tenant identity

Application name, tagline, location, portal hostname, organization names,
letterhead, enabled modules, tariffs, and content templates. These belong in
tenant-scoped settings or organization data.

### Visual theme

Brand colors, application logo, compact logo/favicon, login illustration, and
other presentation tokens. Implement these as a documented theme layer rather
than editing dozens of Blade templates or hardcoded color literals.

At minimum, the theme contract should cover:

- Primary, primary-hover, primary-soft, danger, warning, surface, border, and text
  CSS custom properties.
- Full logo and icon logo with safe defaults.
- Favicon and web manifest identity.
- Login, sidebar, public page, document letterhead, and browser metadata usage.
- Contrast validation and a fallback when a tenant asset is missing.

Tenant-supplied CSS must never be accepted. Store validated color values and
render only known CSS custom properties. Uploaded logos need the same safe media
pipeline described below.

### Deployment secrets and state

APP_KEY, database credentials, session configuration, MPWA credentials, mail
credentials, administrator password, storage contents, logs, caches, and backups
are deployment-specific. They must never be copied from the old application into
the new one or committed to git.

## Remediation roadmap

Each phase should use its own branch and pull request. An agent must finish the
phase, update the `.ai/` handoff files, and obtain review before starting the next
phase. Do not combine all phases into one large change.

### Phase 0: safety preparation

Outcome: changes can be developed and reviewed without endangering production.

- Confirm branch protection and the actual production deployment branch.
- Create a database and uploaded-file backup before production work.
- Decide how to handle resident data in old public git history.
- Inventory credentials requiring rotation after remediation.
- Add a staging environment or a production-like local test procedure.
- Record production smoke-test accounts without storing their credentials in git.

Human approvals required: history rewrite, secret rotation, production backup,
production deployment, and any change to repository visibility.

### Phase 1: P0 security remediation

Outcome: close the known highest-risk paths.

1. Build one upload ingestion service for resident documents and reusable brand
   images. Validate MIME by inspecting file contents, enforce size and allowed
   formats, generate collision-safe filenames, and keep non-public documents
   outside the webroot. Serve private documents through authorized controllers.
2. Add replacement and deletion cleanup, plus reconciliation tests for orphaned
   files.
3. Redesign public registration and credential recovery with rate limits,
   non-enumerating responses, redacted logs, and one-time recovery state. Never
   commit a changed PIN before delivery succeeds.
4. Move MPWA secrets out of inheritable/readable tenant settings. Never return an
   existing secret to a browser. Restrict gateway destinations to an allow-list.
5. Remove literal seed PINs. Require environment-provided initial credentials or
   generate a one-time secret that is printed once and never committed.
6. Stop showing internal exception or shell output to browser users.
7. Add focused regression tests for every repaired path.

Production secret rotation occurs only after the corresponding code fix is ready.

### Phase 2: dependency, test, and CI baseline

Outcome: a clean clone produces a deterministic result.

- Upgrade Laravel and Flysystem to versions without the known advisories.
- Give PHPUnit a valid test-only environment and APP_KEY without requiring a
  developer's `.env` or production secret.
- Remove the 348 test warnings.
- Resolve Pint failures only in reviewed, bounded changes.
- Add Larastan/PHPStan at a practical initial level, then raise it gradually.
- Add GitHub Actions for Composer validation, locked audit, test, Pint, PHP syntax,
  and config/route/view cache compilation.
- Make required CI checks part of branch protection.

Definition of done: a fresh clone with documented prerequisites passes every CI
job without warnings or uncommitted generated files.

### Phase 3: data integrity and performance

Outcome: predictable behavior as tenant and resident counts grow.

- Replace monthly query loops with grouped aggregate queries.
- Move WhatsApp broadcast and notifications to queued jobs with retries,
  idempotency, per-recipient status, and a cPanel-compatible worker strategy.
- Paginate every growing list and add indexes for new filters.
- Resolve NIK and letter-number races using transactions and database constraints
  after legacy duplicate data is cleaned.
- Profile the most frequently used pages before and after changes.

### Phase 4: reusable UI, accessibility, and CSP readiness

Outcome: the UI can be rebranded safely and remains usable on mobile and assistive
technology.

- Extract reusable Blade components or partials for fields, dialogs, icon buttons,
  alerts, status badges, cards, empty states, and pagination.
- Add one `h1` to every page and correct heading sequences.
- Add programmatic labels, accessible icon-button names, visible focus, dialog
  semantics, focus trapping, and Escape behavior.
- Enforce 44px touch targets and 16px mobile input text.
- Move inline JavaScript and repeated inline styles into maintained assets.
- Replace hardcoded colors with the documented theme token contract.
- Introduce a Content Security Policy in report-only mode, remove violations, then
  enforce it.
- Verify representative pages at 360, 768, and 1280 px with a clean console.

### Phase 5: reusable branding and neutral defaults

Outcome: another application can change its identity without editing domain code.

- Audit all literal occurrences of Sukawarga, Kampung Paru, old domains, logo
  filenames, colors, addresses, people, phone numbers, and organization defaults.
- Keep business defaults safe and neutral; tenant identity comes from settings.
- Add validated theme settings and shared helpers for resolved brand assets.
- Ensure login, sidebar, public pages, letterhead, notifications, manifest,
  favicon, title, and metadata use the same resolved identity.
- Add tests proving two tenants can render different names, logos, colors, and
  domains without data or cache leakage.
- Update README, DEPLOY, AGENTS, and `.ai/` documentation.

### Phase 6: release the hardened original

Outcome: the existing production application is running the verified canonical
release.

- Review and merge each remediation pull request.
- Back up production database and uploads.
- Deploy through the documented controlled updater or approved terminal process.
- Run migrations, cache compilation, queue/scheduler setup, and smoke tests.
- Verify content and tenant isolation, not only HTTP status codes.
- Rotate affected secrets after the safe code paths are active.
- Monitor logs, queue failures, billing writes, and WhatsApp delivery.
- Tag the verified commit, for example `template-ready-v1.0.0`.

The exact tag name is a human decision. An agent must not invent or publish a
release tag without approval.

## Creating the new application

Start only from the approved template-ready commit.

### Repository creation

1. Export a clean source snapshot from the approved tag. Do not copy `.git`.
2. Exclude `.env`, databases, uploads, storage runtime content, caches, logs,
   backups, generated exports, and user-provided media.
3. Initialize a new git repository with one clean root commit.
4. Create a new private GitHub repository and verify the new `origin` before the
   first push.
5. Add branch protection, CI, Dependabot or Renovate, and secret scanning.

### Environment isolation

- Generate a new APP_KEY.
- Provision a new empty database and database user.
- Use new session/cookie names if both applications share a parent domain.
- Use new MPWA/API credentials and sender identity.
- Use new storage directories, backups, logs, queues, and scheduler entries.
- Create the initial administrator from environment input or a one-time generated
  password. Never reuse an old PIN.

### Brand and domain setup

The human must supply or approve these values. Agents must not invent them:

- Product/application name.
- Organization name and geographic identity.
- Production domain and any tenant-domain scheme.
- Full logo, icon logo, favicon, and letterhead requirements.
- Primary, secondary, danger, warning, surface, border, and text colors.
- Tagline and public metadata.
- Contact details and legal/privacy text.
- Enabled modules and billing rules.

Configure DNS, cPanel document root to `public/`, TLS, APP_URL, trusted host/domain
records, portal hostname setting, secure cookies, queues, cron, and backups. Verify
the new domain before importing or entering real data.

### Data initialization

- Run migrations against the empty database.
- Seed only neutral reference data.
- Do not copy resident, billing, audit, credential, notification, or uploaded-file
  data from the original application unless a separate, explicit migration is
  approved and legally justified.
- Create organizations and domains through the supported tenant workflow.
- Create users using fresh credentials.
- Validate totals, tenant scope, roles, and empty states before real onboarding.

### New-application acceptance checks

- The old product name, domain, logo, colors, and contact details do not appear.
- No original resident, transaction, audit, or upload data exists.
- No original APP_KEY, API key, PIN, cookie name, or database credential exists.
- Unauthorized cross-tenant reads and writes fail.
- Uploads, recovery, queues, updater, backup, and restore have been tested.
- Public and authenticated pages pass the 360/768/1280 checks.
- Accessibility, metadata, robots/sitemap policy, and CSP match the product's
  intended public/private behavior.
- CI and the full local verification suite pass without warnings.

## Prompts for selected AI agents

Use one phase prompt at a time. Replace bracketed values only with confirmed human
input. Never give an agent production secrets in the prompt.

### Prompt 1: architecture decision and execution plan

```text
You are working in the existing Sukawarga Laravel repository. Read AGENTS.md,
.ai/STATE.md, .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md, .ai/TODO.md, and relevant
.ai/DECISIONS.md entries before taking action.

My intended outcome is to harden the existing application first and later create
a separately branded application with a different domain, logo, colors, secrets,
and empty dataset.

For this task, do not implement code. Determine whether the new product should be
(A) a tenant in the existing deployment or (B) a separate private repository and
deployment. Use the decision gate in the plan, verify the current architecture,
list the operational and security consequences of both choices, and recommend one.
Ask only questions whose answers materially change the decision. Then write the
approved decision to .ai/DECISIONS.md and update .ai/STATE.md and .ai/TODO.md.
Do not deploy, push, create a repository, or modify production.
```

### Prompt 2: P0 security remediation

```text
Work only on Phase 1 of .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md. Read all files
required by AGENTS.md before editing. Create a focused branch or worktree and keep
the existing application behavior stable except for the security fixes.

Remediate, with tests: resident/document uploads, public registration and PIN
recovery abuse, MPWA secret inheritance/display and gateway URL SSRF, literal seed
credentials, sensitive logging, and raw exception/updater output. Reuse or extend
existing helpers and services before creating new ones. Do not rotate production
secrets, rewrite git history, deploy, or push directly to production.

Run the complete required verification, inspect affected UI at 360/768/1280, and
request a code review. Report each security behavior before and after, remaining
risks, migration/deployment steps, and any human action required. Update
.ai/STATE.md, .ai/PROGRESS.md, .ai/TODO.md, and .ai/DECISIONS.md when applicable.
Stop after Phase 1 and provide a pull-request-ready summary.
```

### Prompt 3: clean clone, dependencies, and CI

```text
Implement only Phase 2 of .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md after Phase 1
has been reviewed and merged. Start from the latest canonical branch.

Make a fresh clone deterministic: fix the test APP_KEY/environment problem and all
current warnings, update the vulnerable Laravel and Flysystem packages within
compatible versions, make Pint clean through bounded reviewed changes, introduce
Larastan/PHPStan at a practical baseline, and add GitHub Actions for Composer
validation/audit, PHP syntax, tests, Pint, static analysis, and config/route/view
cache compilation.

Do not change domain behavior merely to satisfy a tool. Verify all 406 existing
tests plus any new tests, document dependency changes and compatibility, and update
the .ai handoff files. Do not deploy or change branch protection yourself unless I
explicitly authorize it. Finish with the exact checks that should become required
on main and production.
```

### Prompt 4: performance and reliability

```text
Implement only Phase 3 of .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md. Follow
AGENTS.md and preserve tenant isolation and WIB billing semantics.

First measure and record query counts and request behavior for the affected pages.
Then remove report/login query loops, paginate growing lists, and design queued
WhatsApp delivery with retries, idempotency, per-recipient status, and a worker
strategy that is actually operable on this cPanel host. Resolve NIK and letter
number races only after inspecting legacy duplicates and proposing a safe migration.

Do not silently introduce Redis, Supervisor, a paid service, or a long-running
daemon. Ask before choosing infrastructure that is not already available. Add
regression tests, compare before/after measurements, run full verification, inspect
affected UI at 360/768/1280, and update all .ai handoff files. Do not deploy.
```

### Prompt 5: reusable UI, accessibility, and theming

```text
Implement Phases 4 and 5 of .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md only after
the security and CI phases are merged. Read the accessibility and SEO references
required by AGENTS.md before changing markup.

Create a small documented theme contract and reusable Blade components/partials
for repeated fields, dialogs, icon buttons, alerts, status badges, cards, and empty
states. Replace hardcoded branding and colors through the shared contract without
adding Node, Vite, Tailwind, Livewire, or an SPA. Make logos, favicon, manifest,
login, sidebar, public pages, letterhead, notifications, titles, and metadata use
one resolved identity source. Tenant-provided values must be validated and must not
allow arbitrary CSS.

Fix headings, labels, accessible names, keyboard/focus behavior, modal semantics,
44px targets, and 16px mobile inputs. Reduce inline JavaScript/style enough to make
a staged CSP practical. Prove with tests that two tenants can render different
names, domains, logos, and themes without cache or data leakage.

Open representative pages in a real browser at 360/768/1280, test keyboard use and
console output, run the full suite, request review, and update the .ai handoff files.
Do not invent brand values and do not deploy.
```

### Prompt 6: release the hardened existing application

```text
Prepare Phase 6 of .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md. This is a release
readiness and controlled deployment task for the existing application.

Verify that all prior remediation pull requests are merged, CI is green, the
production branch relationship is understood, backups exist, migrations are safe,
and rollback instructions name an exact commit and database restore point. Show me
the commits and changes that would be deployed before applying anything.

Do not deploy, merge, tag, rotate secrets, or push until I explicitly approve the
specific action. After approval, perform the documented deployment, migrations,
cache rebuild, worker/scheduler configuration, content checks, tenant-isolation
checks, one authorized billing smoke test, and log/queue monitoring. Report observed
results. Propose a template-ready tag but do not create it without approval. Update
all .ai handoff files with the final verified state.
```

### Prompt 7: create the separately branded application

```text
Create a new, separately branded application from the human-approved template-ready
tag identified in .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md.

Confirmed inputs:
- New application name: [REQUIRED]
- New private GitHub repository: [REQUIRED]
- Production domain: [REQUIRED]
- Organization/location: [REQUIRED]
- Logo and icon assets: [REQUIRED OR EXPLICIT PLACEHOLDER]
- Approved theme colors: [REQUIRED]
- Enabled modules and billing rules: [REQUIRED]
- Hosting target and document root capability: [REQUIRED]

Before acting, read the plan and AGENTS.md. If any required input is missing, stop
and ask; do not invent names, domains, contacts, prices, credentials, or legal text.

Export a clean source snapshot without .git, .env, databases, uploads, storage
runtime data, logs, caches, backups, or generated exports. Initialize a new git
history and verify the new origin before any push. Keep the repository private.
Generate fresh deployment secrets through the approved secret channel, create an
empty database, and seed only neutral reference data. Do not copy residents,
transactions, audit logs, credentials, or media from Sukawarga.

Apply the confirmed identity through the reusable brand/theme contract, configure
the domain and tenant records, and add project-specific AGENTS.md plus a new .ai
handoff set. Run the full test, lint, static analysis, dependency audit, cache,
security, content, accessibility, and 360/768/1280 browser checks. Search for every
old product/domain/asset identifier and report any intentional remainder.

Do not create the remote repository, push, change DNS/cPanel, deploy, or create the
first production administrator until I explicitly approve each external action.
Finish with a launch checklist, rollback plan, and exact remaining human inputs.
```

### Prompt 8: independent final audit

```text
Act as an independent reviewer. Do not implement fixes. Audit the candidate release
against AGENTS.md and .ai/AUDIT-REMEDIATION-AND-NEW-APP-PLAN.md.

Review the diff, tests, authorization, tenant scope, uploads, recovery, secrets,
queues, migrations, updater, brand isolation, empty-data initialization, accessibility,
SEO/privacy policy, CSP, cPanel compatibility, and rollback plan. Verify claims by
running commands and checking content in a real browser at 360/768/1280.

List findings first, ordered by severity, with exact file and line evidence. State
which acceptance checks passed, failed, or were not verifiable. Do not approve the
release while any P0/P1 issue, failing check, copied old data, reused secret, or
unexplained old-brand reference remains.
```

## Agent handoff rule

At the end of every phase, the executing agent must record:

- Branch, base commit, and resulting commit or pull request.
- Exact files and behavior changed.
- Commands run and their observed output.
- Browser widths/pages checked and console result.
- Deployment, migration, rollback, and secret-rotation requirements.
- Work deliberately left out.
- Updated `.ai/STATE.md`, newest-first `.ai/PROGRESS.md`, and `.ai/TODO.md`.
- Any architecture decision in `.ai/DECISIONS.md`.

No agent may describe a phase as complete when its required verification is merely
expected rather than observed.
