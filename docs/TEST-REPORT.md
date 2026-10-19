# Test report

Date: 2026-10-19 · Environment: Windows 11, PHP 8.2.12 (CLI and built-in server), MariaDB 10.4.32
(XAMPP), Chrome (headless, via puppeteer-core 23.11.1). Everything below was actually run; nothing
is reported as passing that was not executed. No CI workflow was used.

## Automated suites (PHPUnit 11)

`vendor/bin/phpunit` → **OK, 64 tests, 1,014 assertions** (about 18 s), on a dedicated
`cvbuilder_test` database.

| Suite | Tests | What it checks |
|---|---|---|
| Unit · `CvSchemaTest` | 12 | structure validation, unknown keys and section types dropped (mass assignment), unique section types, string cleaning and truncation, http(s)-only links, date formats, entry field whitelists, duplicate ids, section/item/bullet limits, status and completeness |
| Unit · `DesignAndLabelsTest` | 6 | design defaults per template and country (no photo for the US), rejection of unreadable colours, unknown fonts and out-of-range sizes, palette contrast ≥ 4.5:1, date formats in English, French and Chinese, RTL direction, completeness of the French, Arabic and Chinese label files |
| Unit · `CatalogueTest` | 5 | 5 countries × 8 templates (40 distinct), first template of each country is a single-column recommended layout, valid template settings, every font file present, country conventions, new-CV section order and phone code |
| Unit · `ImporterTest` | 8 | PDF text split into parts that fit the output limit with no text lost; CJK token estimate; translation touches only human-readable strings (names, e-mails, dates never sent) and puts them back in place; long texts split and joined; parts merged with a continued entry re-attached; token encryption, tampering refused, masking; a token changed by another process is read on the next call; rate-limit durations; JSON answers with code fences |
| Unit · `PhotoServiceTest` | 5 | re-encoded square JPEG + circle PNG stored outside `public/`, copy and delete, PHP file and fake image rejected, path traversal rejected, real upload required by default |
| Pdf · `PdfGenerationTest` | 7 | **every template in every language it supports (79 combinations) renders a one-page A4 PDF** (MediaBox 595.28 × 841.89 pt); Latin text extractable (smalot/pdfparser); XB Riyaz embedded for Arabic and Sun-ExtA for Chinese; long content flows onto 2+ pages in single and sidebar layouts; user HTML is escaped; an empty CV renders; file names (accents, Chinese, path characters) |
| Http · `AdminUserManagementTest` | 5 | template previews on the admin page open full size (40 thumbnails); an administrator creates an account (e-mail stored in lower case, bcrypt hash, temporary password shown once and never in the audit log), the new user must change it before anything else; duplicate and invalid input refused; edit name and e-mail (an e-mail used by another account refused); password reset signs the user out, the old password stops working, the temporary one forces a change; status from the user page; delete only with the exact e-mail typed, CVs deleted with the account; actions audited; an administrator cannot reset or delete their own account; users get 403 on every one of these routes |
| Http · `ProfileImportTest` | 5 | against a local mock of Groq's API (no network): administrator saves the token — encrypted at rest, masked on the page, absent from the audit log, connection test passes, users get 403; PDF upload → resumable steps → French/English/Chinese versions (names and e-mails kept) → review edits → 409 on a stale edit → apply once → profile has three versions; another user gets 404 on the import; **wrong token refused, then the corrected token used by the same running server without restart**; import disabled or a non-PDF refused; profile edited with conflict detection; new CV filled from the profile in the template's section order, and empty without the option; fill from profile in the editor (Chinese), languages without a version refused, other users 404 |
| Http · `HttpFlowTest` | 11 | starts `php -S` on the test database and goes through the real stack: public pages, CSP and security headers, HttpOnly + SameSite cookies, 404s, `/health`; the admin password never appears publicly; guests redirected to sign-in, API 401; CSRF refusal on forms and on the API (forged delete changes nothing); registration validation and duplicate e-mails; sign-in throttling (even the right password is refused while throttled); create → save → **409 on a stale version without overwriting** → preview → exact page count → PDF download with file name and page-count header; rename (Chinese), search, duplicate, delete; **another user gets 404 on every page and API action on someone else's CV, and the CV is unchanged**; users get 403 on administration; photo upload, privacy and rejection of a disguised PHP file; **administrator first sign-in forced to a new password (the initial one cannot be kept, administration blocked until then), bcrypt hash stored**, every admin page, deactivating a user signs them out and blocks sign-in, an admin cannot demote themselves, admin actions audited, no password in the audit log |

## Browser checks (headless Chrome)

Script: `tests/browser/editor-check.js` (plus one-off scripts for the admin, registration and
wizard flows), against the development server with the demo data.

| Area | Result |
|---|---|
| Landing, gallery, sign-in, registration, dashboard, wizard, account | rendered; no console or network errors |
| Editor (French, US, Arabic, Chinese CVs) | form and live preview render; typing → "Unsaved changes" → autosave → "All changes saved"; undo restored the previous headline and redo re-applied it (first run); moving a section with the up button, adding a custom section, hiding it, changing the accent colour, switching template; page count switched from the estimate to the PDF's count; manual save |
| Arabic preview | right-to-left layout and shaping correct; e-mail, phone and URL kept left-to-right |
| Phone layout (390 × 844) | editor Edit/Preview switch, dashboard and landing usable |
| Admin (first sign-in) | forced password change, every admin page, template hide/show with message |
| Registration → wizard (Morocco, Arabic) → editor | worked; another user's CV answered 404 |

Bugs found by these checks and fixed before this report:

- the editor failed to render sections that were already open (initialisation order);
- the gallery's lazy loader set `src="undefined"` when a frame was observed twice (stray 404s);
- hidden templates had no preview on the admin page;
- the country label in the editor was duplicated ("France · France");
- an Arabic location now uses the Arabic comma.

PDFs were also inspected visually during development (French, English, Arabic and Chinese samples
in every layout).

## Real AI service checks (Groq, `qwen/qwen3.8-27b`)

Run by hand with the owner's free-tier key from the local `.env`, on 2026-10-19:

| Check | Result |
|---|---|
| Connection test | answered in 538 ms |
| Extraction of the French sample CV (1,519 characters) | 3.4–4 s; French detected; personal details, 7 sections, dates and the current job correct |
| Full import over HTTP (upload → 2 extraction parts → English and Chinese → review → edit → apply → CV from profile → PDF) | **14/14 checks passed**; about 110 s in total, about 100 s of which waiting for Groq's per-minute quota |
| Chinese version | translated text; names, e-mails, dates and booleans unchanged |
| Free-tier limit found | Groq refuses any request with `max_completion_tokens` above 1,000 (output tokens per minute) for this model, which is why the import works in parts (`GROQ_MAX_TOKENS=950`) |

The browser check of the new pages (profile in three languages, import page, wizard option,
CV created from the profile with a new template, "Fill from my profile", admin AI settings)
showed no console or network error.

## Import speed fix (2026-10-19, measured with the real API)

An import on the live site took more than 8 minutes and was abandoned. The server logs showed that
every request waited for the free-tier quota (1,000 output tokens per minute), and that the
structuring step made the model re-type the whole CV. After the fix, measured on the same two-page
CV with the same key:

| | Before | After |
|---|---|---|
| Calls | 7 (+7 refused) | 6 |
| Output tokens | 3,135 | about 1,660 |
| Total time | 169 s | about 50 s |

French text rebuilt word for word from the PDF (line references, wrapped bullets joined), English and
Chinese versions complete; the split between parts now falls before a section heading (a project
bullet that had been lost at a part boundary is kept). Unit tests added for line references and
splitting; the HTTP suite's Groq mock answers with a line reference; `vendor/bin/phpunit` → 66 tests,
all passing.

## Not tested, or only partly

- **The AI import on the production server** (it needs the administrator to enter a token there), and imports of long multi-page CVs, Arabic or Chinese PDFs, or unusual layouts with the real model. Model output quality varies; the review step exists for that.

- **MySQL 8 itself.** Only MariaDB 10.4 was available locally. The SQL is standard (InnoDB,
  utf8mb4, no MariaDB-only syntax), but the first run on MySQL 8 is the real check.
- **Apache and Nginx.** Only PHP's built-in server was used; the `.htaccess` files and the Nginx
  example were not run.
- **Production deployment** at resume.abdocloud.com: not done (see the hand-over notes).
- **Real SMTP e-mail.** Password reset was exercised only with `MAIL_MAILER=log` and `disabled`.
- **Browsers other than Chrome**, and real phones; **screen readers** (only keyboard flows and
  ARIA markup were checked); **drag and drop by mouse** (reordering was tested with the buttons
  that share the same code path, not with a simulated drag).
- **Concurrency and load**: no load test. On Windows, the single-threaded built-in server
  occasionally failed a request (`ERR_NO_BUFFER_SPACE`) when the gallery loaded many previews at
  once. This is a limitation of the development server, not of the application.
- **Preview vs PDF parity** was compared by eye, not pixel by pixel; the two can differ slightly
  in line breaks.
