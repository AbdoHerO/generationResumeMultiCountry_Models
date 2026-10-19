# Architecture

ResumeAtlas is a server-rendered PHP application with a small JSON API for the editor. It uses no
framework: `app/Core` holds about fifteen short classes that do what the application needs and
nothing else.

## Request lifecycle

```
public/index.php → app/bootstrap.php (autoload, helpers, .env) → App\Core\App::run()
  → Migrator::ensureCurrent()        (AUTO_MIGRATE: applies pending SQL under GET_LOCK, cached by signature)
  → Session::start()                 (strict mode, HttpOnly, SameSite=Lax, Secure on HTTPS, idle timeout)
  → Router::match()                  (routes/web.php; {id} placeholders with regex constraints)
  → middleware pipeline              (guest | auth | password | admin; csrf on every non-GET request)
  → controller → Response            (HTML view, JSON envelope, redirect or file)
  → SecurityHeaders::apply()         (CSP, nosniff, frame options, referrer policy, HSTS on HTTPS)
ErrorHandler turns exceptions into pages or JSON {ok:false, message, code}; 401 → sign-in,
419 → back with a message; details are logged, never shown when APP_DEBUG is false.
```

## Data model

| Table | Purpose |
|---|---|
| `users` | name, e-mail (unique, lower-case), bcrypt hash, role (`user`/`admin`), status, `must_change_password`, last sign-in |
| `templates` | 40 rows seeded from `config/templates.php`; admin-managed metadata plus code-owned `configuration_json` |
| `profiles` | one per user: `{fr, en, zh}` CV contents, `version` (optimistic locking) |
| `profile_imports` | a PDF import in progress or under review (resumable work state, then the three versions); deleted after 7 days |
| `cvs` | owner, title, country, language, template, `content_json`, `design_json`, status, `version` (optimistic locking), private photo id |
| `password_resets` | SHA-256 of single-use tokens, 60-minute expiry |
| `login_attempts` | throttling for sign-in and registration |
| `audit_logs` | administrative and security events (never secrets or CV content) |
| `settings` | application name, registration switch, support e-mail, CV limit, country overrides |

Migrations are plain SQL in `database/migrations`, applied once each and recorded. Seeders are
idempotent: the template seeder inserts missing templates and refreshes only their code-owned
configuration, so administrator edits to names or availability survive deployments.

## The CV document

A CV's content is one JSON document shared by every template, so switching template, country or
language never loses anything:

```json
{ "personal": { "fullName": "…", "email": "…", "phoneCode": "+33", "links": [{"label": "…", "url": "…"}], … },
  "sections": [ { "id": "s_…", "type": "experience", "title": "", "hidden": false,
                  "items": [ { "id": "i_…", "title": "…", "startDate": "2021-03", "current": true, "bullets": ["…"] } ] } ] }
```

`App\Services\Cv\CvSchema::sanitize()` is the only way content reaches the database. It
whitelists section types and fields (no mass assignment), cleans and truncates strings, accepts
only `http(s)` links and `YYYY` / `YYYY-MM` dates, de-duplicates ids and enforces limits
(30 sections, 50 items, 20 bullets, 400 KB). Design settings go through `Design::sanitize()`,
which rejects accent colours with less than 4.5:1 contrast on white and clamps sizes.

## Rendering and PDF

`CvRenderer` turns content + design + template configuration into HTML. Templates are
configuration, not separate files: each one picks a layout (`single`, `sidebar`, `gutter`), a
header style, a heading style, an entry style, a skills style, fonts, colours, sizes, photo shape
and a default section order. Labels, month names, levels and date formats come from
`config/cv/{en,fr,ar,zh}.php`; country conventions come from `config/countries.php`.

The same markup is used twice:

- **Preview** (`mode = preview`): a full HTML page with `@font-face` rules and a 210 mm page box,
  shown in a sandboxed iframe (`srcdoc`, no scripts). The editor double-buffers two iframes to
  avoid flicker and draws page-break guides every 297 mm.
- **PDF** (`mode = pdf`): `PdfService` gives the body to mPDF with A4 page size and the design's
  margins. Sidebar backgrounds are drawn as a page background image so they continue on every
  page. Script detection selects XB Riyaz for Arabic and Sun-ExtA for Chinese. RTL documents swap
  page margins and keep e-mails, phone numbers and URLs in left-to-right order.

mPDF supports a subset of CSS, so the renderer uses tables for side-by-side content and avoids
flexbox and grid in CV markup.

## Editor

`public/assets/js/editor.js` keeps one state object (title, country, language, template, content,
design). Every edit goes through `change()`, which:

1. records an undo snapshot (keystrokes in the same field within 1.2 s are grouped),
2. schedules a preview render (`POST /api/cvs/{id}/preview`, 300 ms debounce, aborts stale requests),
3. schedules an autosave (`POST /api/cvs/{id}/save` with `version`, 1.2 s debounce),
4. schedules an exact page count (`POST /api/cvs/{id}/pages`, 4 s debounce).

Save failures are handled explicitly:

| Failure | Response |
|---|---|
| 409 conflict | The user keeps their version or loads the other one |
| 401 | Sign in again in another tab, then retry |
| 419 | The CSRF token is refreshed |
| Network or server error | Retry with backoff |

Unsaved state is mirrored to `localStorage` whenever a save fails or the tab is hidden, and
offered back on the next visit.

## Security

| Concern | Control |
|---|---|
| Passwords | `password_hash()` (bcrypt), rehash on sign-in, minimum length and confirmation, forced change for the seeded administrator |
| Sessions | strict mode, regenerated on sign-in, HttpOnly, SameSite=Lax, Secure on HTTPS, idle timeout; an HMAC stamp of the password hash ends other sessions after a password change or deactivation |
| CSRF | per-session token, required on every non-GET request (form field or `X-CSRF-Token` header), compared with `hash_equals` |
| Brute force | sign-in throttled per e-mail and IP (5 failures / 15 min), registrations per IP (10 / hour), uniform error message and dummy hash for unknown e-mails |
| Ownership (IDOR) | every CV query is scoped by `user_id` (`Cv::findOwned`); another user's id answers 404 |
| Authorization | `admin` middleware on every administration route; administrators cannot demote, deactivate, reset or delete themselves, and the last active administrator cannot be removed |
| Account management | accounts created or reset by an administrator get a random temporary password (shown once on the next page, kept only in the session until then, never logged) and must choose their own at the next sign-in; a reset changes the hash, which ends the user's sessions |
| Injection | PDO prepared statements only, emulation off; output escaped with `e()`; CV previews in a script-less sandboxed iframe; strict CSP (`script-src 'self'`) |
| Uploads | size limit, MIME by `finfo`, `getimagesize` bounds, re-encoded with GD (drops metadata and payloads), random 128-bit names, stored under `storage/` outside the web root, served only to the owner |
| Downloads | ownership checked, RFC 5987 file names built from letters and digits only |
| Secrets | only in `.env` (never committed); errors and logs carry no passwords, tokens or CV content; password-reset links use `APP_URL`, never the Host header |
| Proxies | client IP and HTTPS taken from forwarded headers only when the peer is in `TRUSTED_PROXIES` |

## Profile and AI import

See [DOCUMENTATION.md §4](../DOCUMENTATION.md#4-the-ai-import-in-detail). In short:

- `ResumeImporter` extracts the PDF's text (smalot/pdfparser), splits it into parts that fit the
  model's output limit, structures each part, merges them, then translates only human-readable
  strings in batches.
- The work is resumable: `POST /api/profile/imports/{id}/continue` advances it for at most 48 s
  and saves the state after every model call.
- `GroqClient` reloads its settings (encrypted token, model, reasoning) from the database before
  every call, so an administrator's new token is used at once. It handles HTTP 429 by waiting on
  the server (short waits) or returning `retryAfter` to the page.
- The profile editor and the import review reuse `editor.js` in `profile` and `import` modes.
