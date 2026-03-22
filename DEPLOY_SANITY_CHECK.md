# Production deploy sanity check

Use this before running your first (or next) production deploy so you don’t break anything.

---

## 1. Must fix before deploy

### 1.1 Production host

**File:** `trellis/hosts/production`

Replace the placeholder with your real server hostname or IP:

```ini
[production]
your_server_hostname   # ← change to e.g. brandonrp.com or 123.45.67.89

[web]
your_server_hostname   # ← same value
```

- The server must be reachable via SSH (key-based auth).
- If you use a hostname, it must resolve (DNS or `trellis/group_vars/all/known_hosts.yml`).

### 1.2 Production vault (brandonrp.com)

**File:** `trellis/group_vars/production/vault.yml`

- A `brandonrp.com` entry was added under `vault_wordpress_sites` so Trellis validation passes (it requires every `wordpress_sites` key to exist in the vault).
- **Before first production deploy**, set production-only secrets:
  - `admin_password`: WordPress admin password for production.
  - `env.db_password`: Strong DB password for production.
  - `env.auth_key`, `secure_auth_key`, `logged_in_key`, `nonce_key`, `auth_salt`, `secure_auth_salt`, `logged_in_salt`, `nonce_salt`: generate new values (e.g. [roots.io salt generator](https://roots.io/salts.html) for the keys/salts; use a long random string for `db_password`).

Edit the vault:

```bash
cd trellis
ansible-vault edit group_vars/production/vault.yml
```

---

## 2. Server prerequisites (first-time setup)

If this is the **first** deploy to this server, the server must already be provisioned with Trellis (LEMP stack, PHP, MariaDB, Nginx, etc.). If not:

1. Fix `trellis/hosts/production` (step 1.1).
2. Run the server playbook (from the `trellis` directory):
   ```bash
   ansible-playbook server.yml -e env=production
   ```
3. Then run the deploy (step 4).

If the server is **already** provisioned (e.g. you’ve run `server.yml` before), you only need deploy.

---

## 3. Deploy checklist

| Check | Notes |
|-------|--------|
| **Hosts** | `trellis/hosts/production` uses your real hostname/IP (not `your_server_hostname`). |
| **Vault** | `vault_wordpress_sites.brandonrp.com` exists and has production secrets set (step 1.2). |
| **Repo** | Production `wordpress_sites` uses `repo: git@github.com:brandonrp/brandonrp.git` and `repo_subtree_path: site` — deploy will pull `site/` from `master`. |
| **SSH access** | From your Mac, `ssh your_server_hostname` (or `ssh web_user@host`) works with your SSH key. |
| **GitHub deploy key** | Server (or the `web_user` on the server) can clone the repo (e.g. deploy key added to GitHub, or SSH key that has access). |
| **Theme assets** | `site/web/app/themes/brandonrp/dist/` (main.css, main.js, fonts) is committed — production will serve these. No extra build step on the server. |

---

## 4. Run deploy

From the **trellis** directory:

```bash
cd trellis
ansible-playbook deploy.yml -e env=production -e site=brandonrp.com
```

You may be prompted for the vault password if the vault is encrypted.

---

## 5. After deploy

- **SSL:** In `group_vars/production/wordpress_sites.yml` you have `ssl.enabled: false` and `ssl.provider: letsencrypt`. When you’re ready for HTTPS, set `ssl.enabled: true` and re-run the server playbook (or the Lets Encrypt role) so certificates are issued; then Nginx will serve HTTPS.
- **URLs:** Production uses `brandonrp.com` (and redirects `www.brandonrp.com`). Ensure DNS for `brandonrp.com` points to this server.
- **Rollback:** If something breaks, you can rollback: `ansible-playbook rollback.yml -e env=production -e site=brandonrp.com` (see Trellis docs for rollback behavior).

---

## 6. Quick validation (no deploy)

To confirm Ansible can see production and the vault is valid, run:

```bash
cd trellis
ansible-playbook deploy.yml -e env=production -e site=brandonrp.com --list-tasks
```

If this fails (e.g. “site not valid”, “vault key missing”, or “host unreachable”), fix the issue before running a real deploy.
