# Test report

Date: 2026-10-19 · Environment: Windows 11, PHP 8.2.12 (CLI and built-in server), MariaDB 10.4.32
(XAMPP), Chrome (headless, via puppeteer-core 23.11.1). Everything below was actually run; nothing
is reported as passing that was not executed. No CI workflow was used.

## Automated suites (PHPUnit 11)

`vendor/bin/phpunit` → **OK, 46 tests, 661 assertions** (about 10 s), on a dedicated
`cvbuilder_test` database.

| Suite | Tests | What it checks |
|---|---|---|
| Unit · `CvSchemaTest` | 13 | structure validation, unknown keys and section types dropped (mass assignment), unique section types, string cleaning and truncation, http(s)-only links, date formats, entry field whitelists, duplicate ids, section/item/bullet limits, status and completeness |
| Unit · `DesignAndLabelsTest` | 6 | design defaults per template and country (no photo for the US), rejection of unreadable colours, unknown fonts and out-of-range sizes, palette contrast ≥ 4.5:1, date formats in English, French and Chinese, RTL direction, completeness of the French, Arabic and Chinese label files |
| Unit · `CatalogueTest` | 5 | 5 countries × ≥ 5 templates (25 distinct), first template of each country is a single-column recommended layout, valid template settings, every font file present, country conventions, new-CV section order and phone code |
| Unit · `PhotoServiceTest` | 5 | re-encoded square JPEG + circle PNG stored outside `public/`, copy and delete, PHP file and fake image rejected, path traversal rejected, real upload required by default |
| Pdf · `PdfGenerationTest` | 7 | **every template in every language it supports (49 combinations) renders a one-page A4 PDF** (MediaBox 595.28 × 841.89 pt); Latin text extractable (smalot/pdfparser); XB Riyaz embedded for Arabic and Sun-ExtA for Chinese; long content flows onto 2+ pages in single and sidebar layouts; user HTML is escaped; an empty CV renders; file names (accents, Chinese, path characters) |
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

## Not tested, or only partly

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
