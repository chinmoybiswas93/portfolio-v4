# Owner's manual steps (hPanel / GitHub)

Claude never handles passwords or private keys. Do these steps yourself. Hostinger writes that Claude performs over SSH or the API are listed separately and each one needs your approval.

## A. Before the Phase 1 deploy proof (staging only)
1. **hPanel → Websites → dev.chinmoybiswas.com → Advanced:** check whether a "custom document root" option exists and note the result (ADR-0002 Option C).
2. **hPanel → Files → Inodes / Disk usage:** note the inode limit (ADR-0002, release retention).
3. **Approve** the commands Claude will propose (shown in full before running). For example:
   - `tar -czf ~/backups/dev.chinmoybiswas.com-$(date +%F).tgz -C ~/domains dev.chinmoybiswas.com`
   - create `~/domains/dev.chinmoybiswas.com/app/{releases,shared}`
   - rsync a skeleton Laravel 13 build (built locally, SQLite, no DB needed)
   - move `public_html` to `public_html.hostinger-default` and create the `public_html` symlink
   - create the staging cron (`* * * * *`) via the API
   - `curl` checks

## B. Before Phase 3 (deploy pipeline and app)

### B1. GitHub
1. Repo `chinmoybiswas93/portfolio-v4`: after Claude's first push, set the **default branch = `development`**.
2. **Settings → Branches → rulesets** for `main` and `development`: require a PR, require status check `ci`, block force-push and deletion.
3. **Settings → Environments:** create `staging` (branch `development` only) and `production` (branch `main` only, **required reviewer: you**).
4. **Deploy key pair (on your Mac):**
   `ssh-keygen -t ed25519 -C "gha-deploy-portfolio-v4" -f ~/.ssh/gha_portfolio_v4 -N ""`
   - Add the **public** key in **hPanel → Advanced → SSH Access → SSH keys**.
   - Put the **private** key into both Environments as secret `SSH_PRIVATE_KEY`, then delete the local private key file (or store it in your password manager).
5. **Environment secrets** (both envs, values differ where noted):
   | Secret | staging | production |
   |---|---|---|
   | `SSH_HOST` | server hostname/IP from hPanel → SSH Access | same |
   | `SSH_PORT` | SSH port from hPanel → SSH Access | same |
   | `SSH_USER` | your `u…` user | same |
   | `SSH_KNOWN_HOSTS` | output of `ssh-keyscan -p <port> <host>` (check the fingerprint against hPanel) | same |
   | `SSH_PRIVATE_KEY` | step 4 | same |
   | `DEPLOY_PATH` | `domains/dev.chinmoybiswas.com/app` | `domains/chinmoybiswas.com/app` |
   | `APP_URL` | `https://dev.chinmoybiswas.com` | `https://chinmoybiswas.com` |
6. **Settings → Code security:** enable Dependabot alerts and security updates, and secret scanning with push protection.

### B2. hPanel: databases
1. **Databases → Management:** create **two** databases and users, one for **staging** and one for **production** (for example `u…_pf4dev` / `u…_pf4prod`), each with a long random password stored in your password manager.
2. Record the MariaDB server version shown in hPanel (for ADR-0001).
3. **Quote secrets in `.env` with single quotes** (`DB_PASSWORD='…'`). An unquoted value starting with `#` is read as a comment, and `$`, spaces or `"` can also break parsing. Single quotes are read literally (the value itself must not contain `'`).
4. You write each `shared/.env` on the server yourself (or Claude writes a template **without** secrets and you fill it in over SSH). `chmod 600`.

### B3. hPanel: mailbox
1. **Emails → chinmoybiswas.com:** create **`hello@chinmoybiswas.com`** with a strong password.
2. Note the SMTP settings (`smtp.hostinger.com`, port 465 SSL), then put `MAIL_*` into each `shared/.env` yourself.
3. Check that **DKIM, SPF and DMARC** are enabled (Emails → DNS settings). Add `DMARC p=quarantine` if missing.
4. Send a test from the mailbox to mail-tester.com and aim for at least 9/10.

### B4. hPanel: cron (or approve Claude's API call)
- Staging: `* * * * *` → `/usr/bin/php /home/<user>/domains/dev.chinmoybiswas.com/app/current/artisan schedule:run`
- Production cron is added only at cutover (Phase 5).

## C. Before launch (Phase 5, listed now for planning)
1. DNS stays in **hPanel** (Hostinger nameservers, domain at Namecheap). No DNS provider change.
2. Confirm SSL is active for `chinmoybiswas.com` and `dev.` in hPanel. Turn on "Force HTTPS".
3. Hostinger CDN: keep it or turn it off per the deploy-proof result (ADR-0008).
4. Create a Cloudflare account **only** for the Web Analytics JS beacon (no DNS change) and copy the beacon token into production `.env`.
5. Back up WordPress (files + DB) before the docroot switch. Claude prepares the commands and you approve them.

## Deploying with SSH switched off between deploys (slice 3.8f)
SSH Access stays off in hPanel except while deploying.

1. Before merging into `development` (or before a manual run), turn **SSH Access on** in hPanel.
2. The Deploy workflow starts with a **preflight** job that connects over SSH. If SSH is off, it stops within seconds with: *"SSH is off — enable SSH Access in hPanel and re-run"*. CI and the build don't run.
3. To deploy again without a new commit: GitHub → **Actions → Deploy → Run workflow**, choose the branch, and run. Alternatively, open the failed run and use **Re-run all jobs**.
   - `development` deploys to staging.
   - `main` stops with *"Deploying main is enabled in Phase 5"* until production is switched on.
4. The deploy job checks SSH again just before uploading, in case it was turned off while CI ran.
5. **Rollback** uses the same check.
6. Turn SSH Access off again in hPanel when the run is green.

The preflight also tells two other failures apart:
- **SSH key rejected:** check the `SSH_PRIVATE_KEY` secret and the authorised keys in hPanel.
- **Host key mismatch:** stop and investigate before changing `SSH_KNOWN_HOSTS`.

## Private admin path (slice 3.8g)
The admin panel URL comes from `ADMIN_PATH`. The repo default is `admin`, for local work only; the real path is never committed.

1. In each server's `shared/.env`, add `ADMIN_PATH=<private path>` (staging and, in Phase 5, production).
2. Deploy, or run `php artisan optimize` so the cached config picks it up. The old `/admin` then returns 404.
3. `robots.txt` never lists the admin path; admin pages send `X-Robots-Tag: noindex, nofollow`.

## Authenticator label "CB Portfolio" (slice 3.8g)
New 2FA set-ups appear in your authenticator app as **CB Portfolio**. Existing entries keep their old label ("Chinmoy Biswas") because the app saves the label when you scan. Codes keep working because the secret is unchanged. To switch to the new label:

1. Sign in with your current code.
2. Open your profile → Two-factor authentication → turn the authenticator app off (it asks for a code), then on again.
3. Scan the new QR code (it shows "CB Portfolio") and confirm with a code from the new entry.
4. Save the new recovery codes somewhere safe, then delete the old entry from the app.

Do it on staging first, and keep the old entry until the new one has worked once.
