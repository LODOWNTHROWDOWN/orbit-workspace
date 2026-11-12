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
7. Configure Railway usage/budget alerts. **Before widely sharing the public demo**, implement request-rate and workspace-creation limits, monitoring, and a scheduled cleanup policy. The current starter is intentionally not abuse-hardened for unrestricted public traffic.

Railway documentation: https://docs.railway.com/builds/dockerfiles and https://docs.railway.com/databases/mysql

## What is implemented
- Session-based temporary workspaces with 24-hour expiry and HttpOnly cookies
- Separate projects and tasks for every workspace (API scopes reads/writes to the visitor's workspace)
- Automatic seed data (fictional)
- Dashboard, project creation, task creation, Kanban status changes, search and priority filter
- PHP + PDO prepared statements, JSON API, CSRF token for write requests
- Responsive SCSS starter design, native dialog with focus handling, clear status feedback
- Basic health endpoint and Railway-ready Dockerfile

## What comes next
Project/task editing, task deletion, more robust accessible error states, date filtering, list view, automated tests, CI, production-grade abuse controls, metrics, keyboard audits, and detailed portfolio case study.

## Security notes
This is a learning starter. The UI and API deliberately cap projects and tasks, but there is **no request rate limiting or service-wide workspace quota yet**. If deployed publicly, bots could create workspaces indefinitely. Add edge/server rate limiting, a workspace quota, and regular expiry cleanup before using it as an openly advertised portfolio demo. Do not store real personal data in demo workspaces.

In production Railway provides HTTPS termination and the cookie is marked Secure. In local Docker it is HTTP-only. CSRF tokens are intentionally bound to the visitor's workspace. The health endpoint does not verify database connectivity; the app's startup migration does. The starter has not been end-to-end tested in Docker in the build environment.
