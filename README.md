# Orbit Workspace — starter (Milestone 1)

An original portfolio project using vanilla JavaScript, SCSS, PHP, and MySQL. Includes a responsive dashboard, simple Kanban board, demo workspace isolation, API, sample data, and Docker deployment. **This is a starter, not a finished production SaaS.**

## Requirements
- Docker Desktop (or Docker Engine + Compose)
- Node.js 22 and npm for optional frontend hot reload

## Start locally (fastest)

```bash
docker compose up --build
```

Visit http://localhost:8080. MySQL will initialize and the web container runs the schema migrations. The first visit seeds three sample projects with tasks into a private 24-hour demo workspace. Changes persist across page refreshes.

To enable Vite hot reload in a second terminal while Docker is running:

```bash
npm install
npm run dev
```

Visit http://localhost:5173. Vite proxies `/api` requests to Docker at port 8080. The browser uses a separate cookie for localhost, so both ports access the same demo workspace.

To stop: `docker compose down` (keeps MySQL data). For a completely fresh local database only: `docker compose down -v` (deletes local demo data).

## Railway setup
1. Push this repository to your GitHub account. `Dockerfile` must be in the repository root.
2. In Railway, create a new project, then add a MySQL database service (the service is commonly named `MySQL`; use the actual name in the references below).
3. Add a new service from the GitHub repo. Railway detects the root `Dockerfile`.
4. In the **web app** service's Variables panel, add (using Railway's variable reference syntax):

```
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
CSRF_SECRET=<a-long-unique-random-value>
```

Use the actual service name in place of `MySQL` if different. Never commit real credentials to Git. Do not expose the MySQL service publicly.
5. Apply changes / redeploy as needed. The startup script will wait for MySQL and run idempotent `CREATE TABLE IF NOT EXISTS` migrations. Visit the web service's Settings → Networking → Generate Domain.
6. Open `https://YOUR-DOMAIN/api/health` (should return `{"status":"ok"}`), then open the root page.
7. Configure Railway usage/budget alerts.
8. After deploying, confirm the rate limiter sees real visitor IPs: load the site, then check the web service's Deploy Logs. Each request is logged as `real_ip=… peer=…`; `real_ip` should match your own public IP. If it shows a Railway/CDN address instead, the per-IP limit will group visitors together, so raise `WORKSPACES_PER_IP` in `server/src/Limits.php` (the global cap still protects the database).

Railway documentation: https://docs.railway.com/builds/dockerfiles and https://docs.railway.com/databases/mysql

## What is implemented
- Session-based temporary workspaces with 24-hour expiry and HttpOnly cookies
- Separate projects and tasks for every workspace (API scopes reads/writes to the visitor's workspace)
- Automatic seed data (fictional)
- Dashboard, project creation, task creation, Kanban status changes, search and priority filter
- PHP + PDO prepared statements, JSON API, CSRF token for write requests
- Responsive SCSS starter design, native dialog with focus handling, clear status feedback
- Basic health endpoint and Railway-ready Dockerfile
- Abuse limits for the public demo (see Security notes)

## What comes next
Project/task editing, task deletion, more robust accessible error states, date filtering, list view, automated tests, CI, production-grade abuse controls, metrics, keyboard audits, and detailed portfolio case study.

## Security notes
The public demo has these protections (all values live in `server/src/Limits.php`):

| Protection | Limit | What visitors see when it triggers |
|---|---|---|
| New workspaces only on page load | Other API calls without a valid session get 401 | "Your demo session has expired. Refresh the page…" |
| New workspaces per IP address | 10 per hour | 429 "Too many new demo sessions from your network…" |
| Active workspaces overall | 500 at once | 503 "The demo is at capacity right now…" |
| API requests per workspace | 120 per minute | 429 "Too many requests. Please slow down…" |
| Content per workspace | 10 projects, 150 tasks per project | 422 "Demo limit: …" |

Counters are kept in the MySQL `rate_limits` table; IP addresses are stored only as keyed hashes. Expired workspaces and old counters are removed opportunistically (on about 1% of requests).

The per-IP limit trusts Railway's `X-Real-IP` header (falling back to the connection address locally). Railway documents that its edge sets this header, but some users have reported it containing proxy addresses — verify it with the deploy logs as described in step 8 above. The global cap and the page-load-only rule do not depend on IP addresses.

Do not store real personal data in demo workspaces. In production Railway provides HTTPS termination and the cookie is marked Secure; in local Docker it is HTTP-only. CSRF tokens are bound to the visitor's workspace. The health endpoint opens a database connection, so it fails if MySQL is unreachable.
