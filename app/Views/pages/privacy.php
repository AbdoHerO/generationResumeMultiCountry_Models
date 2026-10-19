<section class="container" style="padding-top:40px;max-width:820px">
  <h1>Privacy &amp; security</h1>
  <p class="muted">How <?= e(app_name()) ?> handles the personal information in your CVs.</p>
  <div class="card card-pad stack">
    <div><h2>What we store</h2><p>Your account (name, e-mail address and a one-way hash of your password), the CVs you create, their design settings and, if you upload one, your profile photo. We keep a small security log of sign-in attempts and administrative actions, without CV content.</p></div>
    <div><h2>Who can see your CVs</h2><p>Only you. Every request to open, save, download or delete a CV, its photo or its PDF is checked on the server against your account. Administrators manage accounts and templates and can see how many CVs exist, but not their content.</p></div>
    <div><h2>Sensitive details are optional</h2><p>Photo, date of birth, nationality and similar details are never required. The editor tells you, country by country, when it is usual to leave them out.</p></div>
    <div><h2>Security measures</h2><ul class="check-list" style="margin-top:8px">
      <li><?= icon('check') ?> Passwords hashed with PHP's password_hash(); sessions protected with HttpOnly, SameSite and (over HTTPS) Secure cookies.</li>
      <li><?= icon('check') ?> Protection against cross-site request forgery on every form and API call, and throttling of sign-in attempts.</li>
      <li><?= icon('check') ?> Uploaded photos are checked, re-encoded and stored outside the public web folder.</li>
      <li><?= icon('check') ?> PDFs are generated on demand for the signed-in owner and never published at a public address.</li></ul></div>
    <div><h2>Deleting your data</h2><p>You can delete any CV from your dashboard, and your whole account — with every CV and photo — from the Account page. Deletion is immediate and permanent.</p></div>
  </div>
</section>
