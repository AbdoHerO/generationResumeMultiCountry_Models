# ResumeAtlas — complete documentation

ResumeAtlas is a self-hosted web application for writing professional CVs (résumés) that follow
the conventions of five countries and downloading them as real A4 PDFs. Users can also import an
existing PDF CV: an AI model (Qwen on Groq) structures it and prepares French, English and
Chinese versions of a reusable **profile**, which then fills any new CV in one click.

Live instance: <https://resume.abdocloud.com> · Source: <https://github.com/AbdoHerO/generationResumeMultiCountry_Models>

---

## Contents

1. [Overview](#1-overview)
2. [Feature tour (for users)](#2-feature-tour-for-users)
3. [Administration guide](#3-administration-guide)
4. [The AI import in detail](#4-the-ai-import-in-detail)
5. [Templates and country conventions](#5-templates-and-country-conventions)
6. [Architecture](#6-architecture)
7. [Data model](#7-data-model)
8. [HTTP routes and JSON API](#8-http-routes-and-json-api)
9. [Configuration reference (`.env`)](#9-configuration-reference-env)
10. [Installation and local development](#10-installation-and-local-development)
11. [Deployment](#11-deployment)
12. [Security](#12-security)
13. [Testing](#13-testing)
14. [Operations and troubleshooting](#14-operations-and-troubleshooting)
15. [Extending the application](#15-extending-the-application)
16. [Known limitations](#16-known-limitations)
17. [Licences](#17-licences)

---

## 1. Overview

| | |
|---|---|
| **Countries** | France, Morocco, United States, Canada, China |
| **CV languages** | French, English, Arabic (right-to-left), Simplified Chinese |
| **Templates** | 40: 8 per country (5 classic + 3 designer templates) |
| **Profile languages** | French, English, Simplified Chinese |
| **Output** | Strict A4 PDF (multi-page), generated on the server with mPDF |
| **Stack** | PHP 8.2+, MySQL 8 (MariaDB 10.4+ for development), vanilla HTML/CSS/JS, Composer. No framework and no front-end build step |
| **AI** | Groq's OpenAI-compatible chat API, model `qwen/qwen3.8-27b` by default |

The application has three areas:

- **Public pages:** landing page, template gallery with full-size previews, privacy page, sign-in and registration.
- **User space:**
  - CV library (the dashboard), creation wizard and CV editor;
  - global profile and PDF import;
  - account settings.
- **Administration:** statistics, user management, templates, countries and languages, settings (including the AI service) and an activity log.

---

## 2. Feature tour (for users)

### 2.1 Account

- **Register** with a name, e-mail and password (at least 8 characters, with a letter and a digit).
- **Sign in.** After 5 failures in 15 minutes from the same e-mail or address, sign-in is paused.
- **Forgotten password:** a reset link valid for 60 minutes is e-mailed when mail is configured. Otherwise the page explains that the administrator handles resets.
- **Account page:** change your name or password, or delete your account. Deletion removes all your CVs, photos and your profile.
- **Password changes** sign out your other sessions.

### 2.2 CV library (dashboard)

All your CVs as cards with a live thumbnail. You can:

- search by name;
- filter by country, template and status (draft or completed);
- sort by last change, oldest change, name or creation date;
- rename, duplicate, delete or download each CV.

A CV is *completed* once it has a name, a contact detail, at least one experience or education entry, and a summary or three skills.

### 2.3 Creating a CV (wizard)

1. **Country and language.** The country sets the conventions: section order, date format, phone format, photo guidance and tips. Only the languages that make sense for the country are offered: France FR/EN, Morocco FR/AR/EN, United States EN, Canada EN/FR, China ZH/EN.
2. **Fill with my profile.** When your profile has a version in the chosen language (French, English or Chinese), the option *"Fill this CV with my profile"* is ticked by default. The new CV then starts with all your profile content, arranged in the order of the chosen template.
3. **Template.** Pick one of the country's 8 templates. Each card shows a live preview.

### 2.4 The CV editor

The form is on the left and a live A4 preview on the right.

- **Content tab:** personal details, then every section. Each section can be:
  - **expanded or collapsed**;
  - **renamed** (leave the heading empty to use the standard one in the CV language);
  - **hidden** from the CV without losing its content;
  - **reordered** by drag and drop on the grip, or with the ↑ / ↓ buttons (keyboard accessible);
  - **deleted** (Ctrl+Z restores it).

  Entries (jobs, degrees, projects…) can be reordered, duplicated and deleted too. Bullet lists support Enter to add a line and Backspace on an empty line to remove it.
- **Section types:** summary, experience, education, skills (category and optional level), languages (level), certifications, projects, internships, volunteer work, awards, publications, interests, hobbies, references (or "available on request"), additional information, and any number of custom sections.
- **Photo:** upload a JPEG, PNG or WebP of up to 2 MB, cropped to a square. It is stored privately and shown only to you. The country's photo guidance is displayed; US and Canadian CVs usually leave the photo out.
- **Design tab:** accent colour (palette or a custom colour; too-light colours are refused), font, text size, line spacing, margins, space between sections, date format, length target (one or two pages), and the header or sidebar variant of the template.
- **Template tab:** switch country conventions, CV language or template at any time. Your content is kept; the design resets to the new template's defaults, and Ctrl+Z undoes the switch.
- **More (⋮) menu:**
  - **Fill from my profile** replaces the content with your profile version in the CV's language. Design and template are kept, and Ctrl+Z undoes it.
  - Rename, duplicate, open the PDF in a new tab, delete.
- **Saving:**
  - Every change is saved automatically after a short pause; **Ctrl+S** saves at once.
  - If the CV was changed in another tab, you choose which version to keep.
  - If the network or your session fails, your changes are kept in the browser and offered back on your next visit.
- **Undo / redo:** Ctrl+Z, and Ctrl+Y or Ctrl+Shift+Z.
- **Preview:**
  - zoom in and out, or fit to width;
  - dashed page-break guides;
  - a page counter: an estimate first, then the exact count from the PDF engine a few seconds after you stop typing. It turns orange when the CV is longer than your length target.
- **Phones:** an Edit / Preview switch replaces the side-by-side layout.
- **Download PDF:** saves first, then downloads a file named like `Camille_Laurent_CV_France.pdf`.

### 2.5 My profile

*My profile* (in the sidebar) is a master copy of your CV content in **three language versions**: Français, English and 简体中文. It uses the same editor as CVs, without preview or design. Switch versions with the language buttons at the top. A version marked *empty* has no content yet.

You can fill it by hand, or import a PDF CV (next section). Any new CV in French, English or Chinese can start from it. A CV made from the profile is an independent copy: editing the CV never changes the profile, and the reverse is true too.

### 2.6 Importing a CV from PDF

1. Open **My profile → Import a PDF** (or `/profile/import`).
2. Drop or choose a PDF of up to 5 MB, then click **Read my CV**.
   - The PDF must contain text, as exported from Word, Google Docs or LinkedIn. Scanned images are not supported.
3. The progress panel shows four steps:
   - **Reading and structuring:** text extraction, then the AI organises your sections.
   - **Français, English, 简体中文:** the language your CV is written in is marked *Original language of your CV*; the other versions are translated.
   - If the AI service asks to wait (see [§4.4](#44-rate-limits-and-groqs-free-tier)), the page counts down and continues on its own. You can also leave and continue later from **Recent imports → Continue**.
4. The **review** page opens automatically. It shows the three versions in the profile editor.
   - Check and correct anything. Edits are saved as you type, but they stay in the import.
   - Click **Save to my profile** when you are satisfied. If you already have a profile, you confirm that it will be replaced.
5. Your PDF is deleted as soon as its text is read; it is never stored. The extracted text is kept only until the import is ready. Imports are deleted after 7 days.

---

## 3. Administration guide

The administrator account is created automatically on the first start:

- **E-mail:** `ADMIN_EMAIL` (default `admin@abdoucloud.com`).
- **Initial password:** `ADMIN_INITIAL_PASSWORD`, or `123456789` when it is empty.

The password is stored only as a bcrypt hash. **At the first sign-in the administrator must choose a new password before anything else.**

> The repository is public, so the default initial password is public too. Set a strong
> `ADMIN_INITIAL_PASSWORD` before the first start, or sign in and change it immediately.

Administration pages (sidebar **Administration**):

| Page | What you can do |
|---|---|
| **Overview** | Counts of users, CVs, templates and countries; new accounts and CVs over 14 days; CVs by country; most used templates; recent administrative activity |
| **Users** | Search and filter. **New user** creates an account with a temporary password, shown once with a Copy button, which the person must change at the first sign-in. Each user's page lets you: edit name and e-mail; **reset the password** (gives a new temporary password and signs the user out everywhere); deactivate or reactivate; grant or remove administrator rights; **delete the account** with all its CVs (type the e-mail to confirm). You cannot reset, demote, deactivate or delete yourself, and the last active administrator always remains |
| **Templates** | Large thumbnails. Click one, or **Preview**, for the full-size A4 preview in each language and in phone width. You can also: hide or show templates for new CVs (existing CVs keep theirs); edit name, description, category, supported languages, recommended flag and sort order |
| **Countries & languages** | Enable or disable countries; choose the available CV languages and the default language per country |
| **Settings** | Application name, support e-mail, registration on or off, maximum CVs per user. **CV import with AI (Groq)**: see below |
| **Activity log** | Administrative and security events (sign-ins of administrators, account changes, settings, imports applied). Passwords, tokens and CV contents are never recorded |

### 3.1 Managing the Groq API token

Open **Administration → Settings → CV import with AI (Groq)**. You can:

- **Allow users to import a PDF CV:** turns the feature on or off for everyone.
- **Groq API token:** paste a token from <https://console.groq.com> → *API Keys*.
  - The token is encrypted (libsodium secretbox, key derived from `APP_KEY`) and stored in the `settings` table.
  - It is never displayed again: the page shows a mask such as `gsk_…a1b2`.
  - Leave the field empty to keep the current token, or tick **Remove the stored token**.
- **Model:** default `qwen/qwen3.8-27b`. Any chat model offered by Groq can be named.
- **Reasoning:** *Off* (faster, recommended) or *On*. This sets Qwen's `reasoning_effort`, and the reasoning text is always hidden.
- **Test the connection:** sends a tiny request and shows the result and the response time.

**A new token takes effect immediately, without restarting anything.** The AI client reloads its settings from the database before every call, so the next request uses the new token. This holds even when PHP-FPM workers stay alive.

If no token is stored in the database, the `GROQ_API_KEY` of the server's `.env` is used as a fallback. Every change is written to the activity log as "replaced", "removed" or "unchanged"; the token itself never is.

---

## 4. The AI import in detail

### 4.1 Pipeline

```
PDF ──(smalot/pdfparser, on the server)──► text ──► numbered lines ("L12: …") ──► parts
     ──► for each part: Qwen structures it into minified CV JSON (its own language);
         long texts come back as line references ("L12-L14"), rebuilt by the server
     ──► parts merged into one CV ("source"), sanitised by CvSchema
     ──► the source language becomes its profile version (fr / en / zh)
     ──► the other versions: human-readable strings translated in batches, put back in place
     ──► review in the profile editor ──► "Save to my profile"
```

### 4.2 What is sent to the model, and why it works in parts

**The PDF is never sent to Groq**, neither as a file nor as an image: only its text, extracted on
the server. On the free tier, this model allows **1,000 output tokens per minute**, and Groq refuses
any single request whose predicted answer exceeds that. What it counts is the smaller of
`max_completion_tokens` and its own prediction of the answer's size, which it bases on similar
earlier requests. Output tokens are therefore what limits an import's speed, so the importer keeps
answers short, and each one under a configurable cap (`GROQ_MAX_TOKENS`, default 950):

- **Extraction, once per CV:**
  - The lines of the text are numbered (`L12: …`).
  - For long texts (summary, descriptions, bullets, custom items) the model answers with **line references** (`"L12"`, `"L12-L14"`) instead of copying the words; the server rebuilds them from the PDF's own lines. A range in a bullet list is split at the PDF's bullet marks.
  - Short values (names, titles, organisations, dates, skills) are written out, and copied text is accepted too.
  - The JSON is asked for **minified** (no indentation): whitespace was a large share of the tokens.
  - Lines are grouped into parts whose answer fits in one call. A part ends just before a section heading ("PROJETS", "Work Experience", "工作经历"), otherwise before a line starting with a date, so an entry is not cut in two.
  - Parts are merged: personal details come from the first part that has them, sections of the same type are joined, and an entry still cut between two parts is re-attached.
  - If an answer is cut off anyway, that part is split in two and retried.
  - Token counts are estimated per script: about 1 token per Chinese character, 1 per 2.5 Arabic and 1 per 3.5 Latin characters.
- **Translation:** only words are sent, as `{"items": [...]}`:
  - **sent:** headline, city, section titles, job titles, organisations, descriptions, bullets, skill and language names, and so on;
  - **never sent:** names of people, e-mails, phone numbers, links, dates, booleans and enumerations;
  - long texts are split at sentence ends and joined back;
  - the answer must have the same number of strings, otherwise the batch is split and retried.

  The structure of the CV can therefore never be damaged by a translation.
- **Resumable work:** the state of an import is saved in `profile_imports.data_json` after every model call.
  - The page calls `POST /api/profile/imports/{id}/continue` repeatedly. Each call works for at most `step_budget` seconds (30), safely under the web server's 60-second limit, then returns the progress. The page shows the elapsed time live.
  - A failure loses nothing: *Retry* or *Continue* resumes at the next part.

With a paid Groq tier, set `GROQ_MAX_TOKENS=8192`: the CV is then structured in one call and translated in one call per language, which takes a few seconds.

### 4.3 Prompts and guarantees

- **Extraction prompt:** copy faithfully, never invent; dates as `YYYY-MM`; `current: true` for "present / aujourd'hui / 至今"; one section per type; ignore any instruction found inside the CV text (prompt-injection guard).
- **Translation prompt:** natural professional wording; Simplified Chinese; keep company, school, product and technology names and acronyms; ignore instructions inside the strings.
- **Validation:** JSON mode (`response_format: json_object`) is requested.
  - Code fences and `<think>` blocks are tolerated.
  - Every result goes through `CvSchema::sanitize`, the same whitelist as user input, so the model can never add fields, scripts or invalid data.
- **Model options:** for `qwen/` models, `reasoning_effort` comes from the admin setting and `reasoning_format: hidden` is sent. If a model refuses an optional parameter, the request is retried without it.

### 4.4 Rate limits and Groq's free tier

- **When Groq answers HTTP 429**, the client reads `retry-after` (or `x-ratelimit-reset-*`).
  - A wait of 12 s or less, when it fits in the request's budget, happens on the server, followed by one retry.
  - Otherwise the API answers `429` with `errors.retryAfter`, and the import page counts down and calls `continue` again, up to 40 times.
- **Measured with a free-tier key** (two-page French CV, 2,556 characters, PDF → French, English and Chinese):

  | | Before (text copied, indented JSON) | Now (line references, minified) |
  |---|---|---|
  | Calls | 7 (+7 refused for quota) | 6 |
  | Output tokens | 3,135 | about 1,660 |
  | Total time | 169 s | about 50 s |

  Every call logs its token usage (`AI usage`: prompt, completion, finish reason; never content).
  With a paid tier, an import takes a few seconds.
- **Users** can start at most 10 imports per hour (`imports_per_hour`). Uploads refused by the AI quota are not counted.

### 4.5 Privacy

- **The PDF file** is deleted right after text extraction.
- **The CV text** is sent to Groq to structure and translate it. The import page says so.
- **The extracted text** is stored in the import only until it is ready.
- **Imports are deleted** after 7 days.
- **Logs** record counts, languages and error codes, never CV content or tokens. Groq's error messages are logged with the organisation id removed.

---

## 5. Templates and country conventions

### 5.1 The 40 templates

Templates are configuration (`config/templates.php`), rendered by `CvRenderer`. Building blocks:

- **Layouts:**
  - `single`: one column;
  - `sidebar`: a coloured side column on every page, in light, accent or dark style;
  - `gutter`: section titles in a left column.
- **Headers:** `centered`, `left`, `band` (full-width colour band), `split` (contact details on the right), `photo-right`, `tinted` (a tinted card with an accent edge).
- **Headings:**
  - `rule`, `caps-rule`, `bar`, `band`, `accent`, `double`, `caps-plain`;
  - `bilingual`: two languages side by side;
  - `solid`: white text on an accent band;
  - `marker`: an accent square before the title.
- **Entries:** `stacked`, `dates-left`, `compact`, `timeline` (dates in a margin and a vertical accent line).
- **Summary style:** `plain`, `italic` or `boxed` (tinted box).

| Country | Templates (★ recommended; *new designer templates* in italics) |
|---|---|
| France | France Classic ★, France Modern ★, France Executive, France Creative, France Compact, *France Élégance* (serif, centred portrait, timeline), *France Horizon* (deep teal sidebar, solid headings), *France Atelier* (tinted header card, boxed profile, dates in margin) |
| Morocco | Morocco Essential ★, Morocco Modern, Morocco Academic, Morocco Bilingual ★ (FR + AR headings), Morocco Career, *Morocco Prestige* (navy band, serif, timeline), *Morocco Atlas* (emerald sidebar), *Morocco Sahara* (amber tinted header, solid headings) |
| United States | US ATS Classic ★, US Modern, US Executive, US Technical ★, US Entry-Level, *US Impact* (blue header card, boxed summary), *US Nova* (indigo sidebar), *US Chronicle* (serif timeline) |
| Canada | Canada Professional ★, Canada Modern ★, Canada Technical, Canada Academic, Canada Career Change, *Canada Maple* (maple-red band, timeline), *Canada Aurora* (deep teal sidebar), *Canada Summit* (tinted header, title column) |
| China | China Classic ★, China Modern, China Academic, China Bilingual ★ (ZH + EN), China Technical, *China Elite* (tinted header with photo, solid bands), *China Jade* (jade sidebar), *China Ink* (ink-black timeline) |

The first template of each country is the simple, clean, ATS-friendly one. US and Canadian templates have no photo, following local hiring practice. Every template renders in every language it supports; the test suite checks that each one produces a one-page A4 PDF from the sample CV.

### 5.2 Country conventions (`config/countries.php`)

For each country the file sets:
- native name, CV languages and default language;
- calling code and phone example;
- date style and page target;
- length hint and photo guidance: `common`, `optional` or `discouraged`;
- the optional personal details customary there: date of birth, nationality, work authorisation, driving licence, hometown;
- the default section order, and tips shown in the editor.

Administrators can disable a country or restrict its languages; overrides are stored in `settings.countries`.

### 5.3 Labels (`config/cv/{en,fr,ar,zh}.php`)

Section headings, field labels, month names, skill and language levels, employment types and
date formats per language. For example, Chinese dates use `2024年3月` or `2024.03`.

---

## 6. Architecture

### 6.1 Request lifecycle

```
public/index.php → app/bootstrap.php (autoload, helpers, .env) → App\Core\App::run()
  → Migrator::ensureCurrent()   AUTO_MIGRATE: pending SQL + idempotent seeders under GET_LOCK,
                                skipped when the signature (migrations, seeders, templates) is unchanged
  → Session::start()            strict mode, HttpOnly, SameSite=Lax, Secure on HTTPS, idle timeout
  → Router::match()             routes/web.php
  → middleware                  guest | auth | password (forced change) | admin; CSRF on every non-GET
  → controller → Response       HTML view, JSON envelope {ok, message, data|errors, code}, redirect, file
  → SecurityHeaders             CSP, nosniff, frame options, referrer policy, HSTS on HTTPS
```

`.env` is read next to the code, or from `<app>/shared/.env` when the code runs from a
`releases/<id>` directory (CloudOps, Deployer and Capistrano layouts).

### 6.2 Code layout

```
app/Core            Router, Request, Response, Session, View, Database (PDO), Migrator, Config, Env,
                    ErrorHandler, SecurityHeaders, Logger (JSON lines), Storage, Validator
app/Controllers     pages; Api/ (CvApiController, ProfileApiController); Admin/ (overview, users, templates)
app/Middleware      Auth, Guest, Admin, Csrf, PasswordChange
app/Models          User, Cv, Template, Setting, AuditLog, LoginAttempt, PasswordReset, Profile, ProfileImport
app/Services        Auth, Countries, Labels, Mailer, PhotoService, Secrets
app/Services/Cv     CvSchema (content model + sanitize), CvRenderer (HTML), PdfService (mPDF), Design,
                    CvService (create/save/duplicate/editor state), SampleContent
app/Services/Ai     GroqClient (chat API, settings reload, rate limits), AiException
app/Services/Profile ProfileService (profile versions, fill CVs), ResumeImporter (PDF → profile)
app/Views           PHP templates, always escaped with e()
config              app, database, countries, cv/*.php labels, templates, design, icons
database            migrations (*.sql) and seeders
public              index.php, .htaccess, assets (css, js, fonts, img, icons)
routes/web.php      every route and its middleware
storage             logs, caches (mPDF fonts, sidebar strips), private photos — never served
tests               Unit, Pdf, Http suites; Support/groq-mock.php; browser/ (optional Chrome check)
bin/console         migrate, seed, seed:demo, key:generate, admin:reset-password, status
```

### 6.3 The CV document

One JSON document per CV (and per profile language), shared by every template:

```json
{ "personal": { "fullName": "", "headline": "", "email": "", "phoneCode": "+33", "phone": "",
                "city": "", "country": "", "address": "", "linkedin": "", "portfolio": "", "website": "",
                "dateOfBirth": "", "nationality": "", "workAuthorization": "", "drivingLicense": "",
                "hometown": "", "links": [{ "label": "", "url": "" }] },
  "sections": [ { "id": "s_…", "type": "experience", "title": "", "hidden": false,
                  "items": [ { "id": "i_…", "title": "", "organization": "", "location": "",
                               "employmentType": "", "startDate": "2021-03", "endDate": "",
                               "current": true, "description": "", "bullets": [""] } ] } ] }
```

`CvSchema::sanitize()` is the only way content reaches the database:
- section types and fields are whitelisted (no mass assignment);
- strings are cleaned and truncated;
- links must be `http(s)`; dates are `YYYY` or `YYYY-MM`;
- duplicate ids are replaced;
- limits apply: 30 sections, 50 items, 20 bullets, 400 KB.

### 6.4 Rendering and PDF

`CvRenderer` builds the same markup for two outputs:

- **Preview:** a full HTML page with `@font-face` rules (the same TTF files as the PDF), shown in a sandboxed `srcdoc` iframe without scripts. The editor double-buffers two iframes to avoid flicker.
- **PDF:** `PdfService` gives the body to mPDF with A4 pages and the design's margins.
  - Sidebar colours are drawn as a page background image, so they continue on every page.
  - mPDF picks XB Riyaz for Arabic and Sun-ExtA for Chinese automatically.
  - Right-to-left documents swap the margins and keep e-mails, phones and URLs left-to-right.

mPDF supports only part of CSS, so the CV markup uses tables for side-by-side content and
for tinted boxes, and never flexbox or grid.

### 6.5 Editor (`public/assets/js/editor.js`)

One state object holds title, country, language, template, content and design; in profile mode it holds the three language versions instead. Every edit goes through `change()`, which:

- records an undo snapshot (keystrokes in one field within 1.2 s form a single step);
- schedules a preview, about 300 ms later;
- schedules an autosave with the `version` number, 1.2 s later;
- schedules an exact page count, 4 s later.

The same editor serves three modes:

| Mode | Page | Saves to |
|---|---|---|
| `cv` | `/cvs/{id}/edit` | `POST /api/cvs/{id}/save` |
| `profile` | `/profile` | `POST /api/profile/save` |
| `import` | `/profile/imports/{id}` | `POST /api/profile/imports/{id}/save` |

---

## 7. Data model

| Table | Purpose |
|---|---|
| `users` | name, unique lower-case e-mail, bcrypt `password_hash`, role `user`/`admin`, status `active`/`inactive`, `must_change_password`, `last_login_at` |
| `templates` | 40 rows from `config/templates.php`. Administrator-managed metadata (name, description, category, languages, recommended, active, order) plus code-owned `configuration_json`, refreshed on every seed |
| `cvs` | owner, title, country, language, template, `content_json`, `design_json`, status, `version` (optimistic locking), private `photo_path` |
| `profiles` | one row per user: `data_json` = `{"fr": content, "en": content, "zh": content}`, `version` |
| `profile_imports` | a PDF import: status `processing`/`ready`/`applied`/`failed`, source language, file name, `data_json` (work state, then the three versions), `version`, error. Deleted after 7 days |
| `password_resets` | SHA-256 of single-use tokens, 60-minute expiry |
| `login_attempts` | throttling of sign-in and registration |
| `audit_logs` | administrative and security events with actor, target, details (never secrets) and IP |
| `settings` | JSON values: `app_name`, `registration_open`, `support_email`, `max_cvs_per_user`, `countries`, `ai_api_key` (encrypted), `ai_model`, `ai_reasoning_effort`, `ai_import_enabled` |
| `schema_migrations` | applied migration files |

Deleting a user cascades to their CVs, profile, imports and password resets; their photos are
removed from `storage/`. Migrations are plain SQL in `database/migrations` and run once each.

---

## 8. HTTP routes and JSON API

All JSON answers use the envelope `{"ok": true, "message": "", "data": {...}}` or
`{"ok": false, "message": "…", "code": "…", "errors": {...}}`.

Every POST needs the CSRF token, either as the `X-CSRF-Token` header or the `_token` field.

Every route with `{id}` loads the object with the signed-in user's id: another user's object answers **404**.

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/`, `/privacy`, `/templates` | public | Landing, privacy, gallery |
| GET | `/templates/{slug}/preview?lang=` | public (hidden templates: admins) | Sample CV in a template |
| GET | `/health` | public | `{"status":"ok","database":true}` (503 when the database is down) |
| GET/POST | `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` | guests | Authentication |
| POST | `/logout` | signed in | Sign out |
| GET/POST | `/account/change-password` | signed in | Forced password change |
| GET | `/dashboard` | user | CV library |
| GET/POST | `/cvs/new`, `/cvs` | user | Wizard; create (`country`, `language`, `template`, `title`, `from_profile=1`) |
| GET | `/cvs/{id}/edit`, `/cvs/{id}/download[?inline=1]`, `/cvs/{id}/render`, `/cvs/{id}/photo` | owner | Editor, PDF (header `X-Page-Count`), thumbnail HTML, private photo |
| GET/POST | `/account`, `/account/profile`, `/account/password`, `/account/delete` | user | Account |
| GET | `/api/cvs/{id}` | owner | CV state |
| POST | `/api/cvs/{id}/save` | owner | Save; `409 version_conflict` when stale |
| POST | `/api/cvs/{id}/preview`, `/api/cvs/{id}/pages` | owner | Preview HTML; exact PDF page count |
| POST | `/api/cvs/{id}/from-profile` | owner | Profile content for this CV (`language`, `country`, `templateId`); not saved, the editor applies it |
| POST | `/api/cvs/{id}/rename`, `/duplicate`, `/delete`, `/photo`, `/photo/delete` | owner | CV actions |
| GET | `/api/session` | user | Session check and fresh CSRF token |
| GET | `/profile`, `/profile/import`, `/profile/imports/{id}` | user | Profile editor, import page, import review |
| GET | `/api/profile` | user | `{version, filled: {fr,en,zh}, data}` |
| POST | `/api/profile/save` | user | `{version, data: {fr,en,zh}}`; `409` when stale |
| POST | `/api/profile/import` | user | Multipart `pdf`: extracts text, creates the import; returns `{id, progress}`. `503` when the AI is disabled or unconfigured, `429` after 10 imports per hour, `422` for an invalid file |
| POST | `/api/profile/imports/{id}/continue` | owner | Advances the work for up to 48 s; returns `{progress, ready, reviewUrl}`. `429` with `errors.retryAfter` when the AI quota is reached; `502` for other AI failures |
| GET | `/api/profile/imports/{id}` | owner | `{version, status}` |
| POST | `/api/profile/imports/{id}/save` | owner | Review edits (`409` when stale) |
| POST | `/api/profile/imports/{id}/apply` | owner | Replace the profile with the reviewed versions (once) |
| POST | `/api/profile/imports/{id}/delete` | owner | Discard an import |
| GET | `/admin`, `/admin/users`, `/admin/users/new`, `/admin/users/{id}`, `/admin/templates`, `/admin/countries`, `/admin/settings`, `/admin/audit` | admin | Administration pages |
| POST | `/admin/users`, `/admin/users/{id}`, `/{id}/password`, `/{id}/delete`, `/{id}/status`, `/{id}/role` | admin | User management |
| POST | `/admin/templates/{id}`, `/admin/templates/{id}/toggle`, `/admin/countries`, `/admin/settings` | admin | Catalogue and settings |
| POST | `/admin/settings/ai`, `/admin/settings/ai/test` | admin | AI token, model, reasoning, switch; connection test |

`progress` has the shape `{"steps": {"extract": {"done", "total"}, "fr": {...}, "en": {...}, "zh": {...}}, "ready": bool, "sourceLanguage": "fr"}`.

---

## 9. Configuration reference (`.env`)

Copy `.env.example` to `.env`. Real environment variables take precedence over the file. Never commit a real `.env`.

| Variable | Default | Meaning |
|---|---|---|
| `APP_NAME` | ResumeAtlas | Default application name (the admin setting overrides it) |
| `APP_ENV` | local | `production`, `local` or `testing` (`seed:demo` is refused in production) |
| `APP_DEBUG` | true | Show error details. **Must be `false` in production** |
| `APP_URL` | http://localhost:8000 | Public address, used in password-reset links |
| `APP_KEY` | — | `base64:` secret from `php bin/console key:generate`. Signs sessions and encrypts the Groq token. Changing it invalidates sessions and the stored token, which must then be entered again |
| `APP_TIMEZONE` | UTC | Display timezone; the database works in UTC |
| `DB_HOST`, `DB_PORT`, `DB_SOCKET`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | 127.0.0.1, 3306, —, cvbuilder, root, — | MySQL connection (`DB_SOCKET` wins over host and port) |
| `AUTO_MIGRATE` | true | Apply migrations and seeders on the first request after a deployment |
| `ADMIN_EMAIL` | admin@abdoucloud.com | Seeded administrator |
| `ADMIN_INITIAL_PASSWORD` | — (→ `123456789`) | One-time password, which must be changed at the first sign-in |
| `SESSION_SECURE` | auto | `auto` sets the Secure cookie flag on HTTPS |
| `SESSION_LIFETIME_MINUTES` | 120 | Idle session timeout |
| `MAIL_MAILER` | log | `smtp`, `log` (written to `storage/logs`) or `disabled` |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | | SMTP settings |
| `TRUSTED_PROXIES` | 127.0.0.1,::1 | Addresses whose `X-Forwarded-*` headers are trusted. Leave it empty when the web server already restores the client IP (CloudOps does) |
| `GROQ_API_URL` | https://api.groq.com/openai/v1/chat/completions | Chat completions endpoint (any OpenAI-compatible URL) |
| `GROQ_API_KEY` | — | Fallback token, used only when no token is stored by an administrator |
| `GROQ_MODEL` | qwen/qwen3.8-27b | Default model (the admin setting overrides it) |
| `GROQ_TIMEOUT` | 50 | Seconds per API call |
| `GROQ_MAX_TOKENS` | 950 | Output tokens per call: 950 fits Groq's free tier; use 8192 on a paid tier |

> If a Groq token (`gsk_…`) is placed in `GROQ_API_URL` by mistake, it is used as the fallback
> key with the default URL. It is better to put it in `GROQ_API_KEY`, or in the administration.

Code-level limits are in `config/app.php`:
- CVs per user: 50 (also an admin setting);
- photo size: 2 MB; content size: 400 KB;
- sign-in: 5 failures per 15 minutes; registrations: 10 per hour per IP;
- AI import: PDF up to 5 MB, 20,000 characters of text, 10 imports per hour, 48-second steps.

---

## 10. Installation and local development

Requirements:
- PHP 8.2 or newer with the `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `json`, `curl` and `sodium` extensions;
- MySQL 8 (MariaDB 10.4+ works for development);
- Composer 2.

```bash
git clone https://github.com/AbdoHerO/generationResumeMultiCountry_Models.git
cd generationResumeMultiCountry_Models
composer install
cp .env.example .env
php bin/console key:generate          # paste the printed APP_KEY line into .env
# create the database: CREATE DATABASE cvbuilder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php bin/console migrate               # or let AUTO_MIGRATE do it on the first request
php bin/console seed:demo             # optional: demo@example.test / demo-password-1, 5 sample CVs
php -S 127.0.0.1:8000 -t public public/index.php
```

- **Apache / XAMPP:** point the virtual host at `public/`. `public/.htaccess` routes every request to `index.php`. If the document root must be the project folder, the root `.htaccess` forwards to `public/`.
- **Nginx:** `root …/public; location / { try_files $uri /index.php?$query_string; }`, with PHP-FPM for `index.php` only.
- **Storage:** `storage/` must be writable by PHP and never served.

Console commands (`php bin/console …`):

| Command | Purpose |
|---|---|
| `migrate` | Apply migrations, then run the seeders (templates, settings, administrator) |
| `seed` | Seeders only |
| `seed:demo` | Demo user and CVs (refused when `APP_ENV=production`) |
| `key:generate` | Print a new `APP_KEY` |
| `admin:reset-password <email>` | Print a one-time temporary password for an administrator |
| `status` | Database, migrations and counts |

To try the AI import locally, set `GROQ_API_KEY` in `.env`, or sign in as the administrator and paste a token in **Settings**.

---

## 11. Deployment

### 11.1 Any PHP host

1. Run `composer install --no-dev --optimize-autoloader`.
2. Set the document root to `public/`.
3. Fill in `.env` with production values: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY`, the database settings, and a strong `ADMIN_INITIAL_PASSWORD`.
4. Keep `storage/` writable and persistent between releases.
5. Health check: `GET /health`.

Releases can be switched atomically: migrations run once under a database lock, and the seeders are idempotent. Template seeding refreshes layouts but keeps administrator edits.

### 11.2 CloudOps (resume.abdocloud.com)

The live instance is the CloudOps website `resume`:
- type **PHP 8.3**, document root `public`, health `/health`;
- MySQL database `co_resume`;
- source: Git, branch `main`;
- behind Cloudflare, with HTTPS.

CloudOps keeps the settings in `<app>/shared/.env` and does not link that file into plain PHP releases, so the application reads it from there (see `app/bootstrap.php`). A deployment is:

```bash
# on the server, as the CloudOps panel user
php8.3 artisan cloudops:deploy resume --no-assets
```

The deployment fetches the code, runs `composer install`, checks `/health` on the new release before and after the switch, and reverts on failure. On the first request the application applies any new migration and refreshes the templates.

After deploying the AI import, an administrator opens **Settings → CV import with AI (Groq)**, pastes the token, and clicks **Test the connection**. No restart is needed.

The PHP limits of CloudOps websites suit the import:
- 60 s per request, against import steps of at most 48 s;
- 16 MB uploads, against PDFs of at most 5 MB.

---

## 12. Security

| Concern | Control |
|---|---|
| **Passwords** | bcrypt via `password_hash`, rehashed on sign-in. At least 8 characters with a letter and a digit. Temporary passwords (admin-created or reset) must be changed at the next sign-in |
| **Sessions** | Strict mode, regenerated on sign-in, HttpOnly, SameSite=Lax, Secure on HTTPS, idle timeout. An HMAC stamp of the password hash signs out other sessions after a password change, reset or deactivation |
| **CSRF** | Per-session token on every non-GET request, compared with `hash_equals` |
| **Brute force** | Sign-in throttled per e-mail and IP; registrations throttled per IP. Same error message and timing for unknown e-mails |
| **Ownership (IDOR)** | Every CV, profile and import query is scoped by `user_id`; another user's id answers 404 |
| **Authorisation** | `admin` middleware on all administration routes. No self-demotion or self-deletion; the last administrator stays |
| **Injection** | PDO prepared statements only (no emulation). Output escaped with `e()`. Previews in a script-less sandboxed iframe. CSP `script-src 'self'` |
| **AI output** | Treated as untrusted input: JSON parsed, then `CvSchema::sanitize`. Prompts tell the model to ignore instructions inside CV text. Translations cannot change structure, links or contact details |
| **Uploads** | Photos: size limit, `finfo` type check, pixel bounds, re-encoded with GD, random names, stored outside the web root, served only to the owner. PDFs: size, MIME type and `%PDF-` signature checked; the file is deleted after text extraction |
| **Secrets** | `.env` is never committed. The Groq token is encrypted at rest (libsodium, key from `APP_KEY`), masked in the interface, and never logged or audited |
| **Downloads** | Ownership checked; file names built from letters and digits only (RFC 5987) |
| **Proxies** | The client IP and HTTPS come from forwarded headers only for `TRUSTED_PROXIES` |

---

## 13. Testing

```bash
vendor/bin/phpunit                    # all suites
vendor/bin/phpunit --testsuite Unit   # Unit | Pdf | Http
```

Tests use a separate database, `cvbuilder_test`, which is created and migrated automatically; the
bootstrap refuses any database whose name does not end in `_test`.

The HTTP suite starts PHP's built-in server on port 8765 (`TEST_HTTP_PORT`). It also starts a **local mock of Groq's API** on the next port (`tests/Support/groq-mock.php`):
- it accepts only the test token;
- it structures any CV and "translates" by prefixing `[en]` or `[zh]`.

Tests therefore never call the real AI service or need a key.

Coverage, current suite: **64 tests, 1,014 assertions, all passing.**

| Suite | Covers |
|---|---|
| Unit | Content schema (validation, whitelist, limits). Design and contrast rules. Labels in four languages. Catalogue (8 templates per country, 40 distinct). Photo service. **Importer**: text splitting under the output limit, CJK token estimate, translating only human-readable strings, long texts split and joined, merging parts, encryption and masking of the token, a new token read immediately, rate-limit durations, JSON answer parsing |
| Pdf | Every template in every supported language renders a one-page A4 PDF; Arabic and Chinese fonts are embedded; long CVs flow onto further pages; HTML is escaped; file names |
| Http | Public pages and headers. Authentication, CSRF and throttling. CV editor API (conflicts, preview, PDF). Ownership. Administrator user management. **Profile and import**: the token stored encrypted, masked and audited without the secret; a full import → review → edit → apply; another user gets 404; **a wrong token, then a corrected one, used by the running server without restart**; import disabled or a non-PDF refused; the profile fills new CVs in the template's order; "fill from profile" in the editor; languages without a version refused |

Optional browser check: `tests/browser/editor-check.js` (Chrome + puppeteer-core). The detailed results and what was not tested are in [docs/TEST-REPORT.md](docs/TEST-REPORT.md).

---

## 14. Operations and troubleshooting

| Symptom | Cause and fix |
|---|---|
| `/health` answers 503 | Database unreachable: check the `DB_*` settings and that MySQL is running |
| HTTP 500 right after a deployment | Usually a missing `.env`. Check `storage/logs/app-YYYY-MM-DD.log`; each error has a `ref` shown to the user |
| The "Import a PDF" button is missing, or the import says "not available yet" | No token, or the import is disabled: **Admin → Settings → CV import with AI** |
| "The Groq API token was refused" | Invalid or revoked token: paste a new one in Settings. It is used at once |
| "The AI model was not found" | Check the model name in Settings (it must exist on Groq) |
| An import keeps waiting "for the AI quota" | Free-tier per-minute limits; the page continues on its own. For faster imports, use a paid Groq tier and set `GROQ_MAX_TOKENS=8192` |
| "No text was found in this PDF" | Scanned or image-only PDF: export a text PDF from the word processor |
| An import stopped half-way (tab closed, network) | **My profile → Import a PDF → Recent imports → Continue** resumes it |
| Forgotten administrator password | `php bin/console admin:reset-password admin@…` prints a one-time password |
| `APP_KEY` changed | Users are signed out, and the stored Groq token can no longer be decrypted: enter it again in Settings |

Logs are JSON lines in `storage/logs/`. They never contain passwords, tokens or CV content. Useful events:
- `CV import started` / `CV import ready` (counts and language only);
- `AI request refused` (HTTP status, Groq code, Groq's message with the organisation id removed, wait time);
- `Unhandled exception` (with the `ref` shown to the user).

---

## 15. Extending the application

- **A new template:** add an entry to `config/templates.php` with `layout`, `header`, `heading`, `entry`, `skills`, fonts, accent, sizes, photo, `variants` and an optional `section_order`. It is seeded on the next request. Then run the Pdf suite, which renders it in every language.
- **A new section type:** `CvSchema::SECTION_TYPES` and `FIELDS`; labels in `config/cv/*.php`; rendering in `CvRenderer::sectionRows`; the editor's `KIND`, icon and name maps in `editor.js`; the field lists in `ResumeImporter` (`ITEM_TEXT`) and in the extraction prompt.
- **A new country:** `config/countries.php`, a flag in `public/assets/img/flags/`, templates, and sample content in `SampleContent`.
- **Another AI provider:** any OpenAI-compatible endpoint works through `GROQ_API_URL` and the model setting. The client lives in `app/Services/Ai/GroqClient.php`.
- **Another profile language:** `ProfileService::LANGUAGES` and `LANGUAGE_NAMES`, labels in `config/cv/`, and the translation prompt names in `ResumeImporter`.

---

## 16. Known limitations

- The live preview is HTML; the PDF is rendered by mPDF from the same markup and fonts. They match closely but not pixel for pixel, and page breaks in the preview are estimates until the exact count arrives.
- Changing a CV's language changes headings, dates and labels, but not your own text. Use the profile's translated versions, through **Fill from my profile**, for that.
- The profile has French, English and Chinese versions only. Arabic CVs are written directly in the CV editor.
- PDF import needs a text PDF (no OCR). AI output can contain mistakes, which is why the review step exists. On Groq's free tier an import takes one to a few minutes.
- One photo per CV, centre-cropped. There is no public sharing link, cover letter or DOCX export.
- Password-reset e-mails need SMTP settings.
- Tested on MariaDB 10.4 locally and MySQL 8 in production; Apache and Nginx configurations other than CloudOps' were not tested.

---

## 17. Licences

- **Application code:** all rights reserved by the repository owner, unless a licence is added.
- **Fonts:** SIL Open Font License 1.1 (`public/assets/fonts/*.txt`).
- **Icons:** Lucide, ISC licence.
- **Libraries:** mPDF (GPL-2.0-only), PHPMailer (LGPL-2.1), smalot/pdfparser (LGPL-3.0).
