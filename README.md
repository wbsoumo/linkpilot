# LinkPilot AI - AI CRM & Intelligent Email Outreach Platform

LinkPilot AI is an enterprise-grade, next-generation Customer Relationship Management (CRM) and cold outreach ecosystem. It combines instant email synchronization, asynchronous AI-driven intelligence parsing, companion Chrome extension integration, and powerful automated workflow pipelines.

---

## 🚀 Key Architectural Modules

### 1. **AI CRM Dashboard & Control Hub (`dashboard/`)**
* **Dynamic Analytics**: Real-time stats, pipelines, and visual chart boards for pipeline health, lead scores, and AI efficiency tracking.
* **Global Search Autocomplete**: Search bar focused instantly with keyboard shortcut `Ctrl + /` (Windows/Linux) or `Cmd + /` (Mac) with debounced auto-completion searching across emails, leads, companies, and contacts.
* **Premium UX/UI**: Implements rich visual aesthetics, harmonious glassmorphism gradients, and custom sequence-aligned **skeleton loaders** to handle async loading states smoothly.

### 2. **Intelligent Email Sync & Queue Engine**
* **Instant Sync (`backend/sync_helper.php`)**: Connects to user-configured IMAP accounts to retrieve new incoming emails and saves them raw in **under 1 second**, bypasses the synchronous AI API latency.
* **Spam & Promotion Heuristics**: Custom filter rules (keyword, email sender, domain) classify and isolate newsletters, alerts, and promotions locally (taking 0.01s with 0 tokens used).
* **Asynchronous AI Ingestion Queue (`backend/queue_worker.php`)**:
  * A background queue worker processes pending emails.
  * Formulates structured prompts for LLM providers (Gemini, OpenRouter, GPT-4o-mini).
  * Extracts contact details (Name, Company, Website, Phone, Location, Services Requested, Budgets, and Deadlines).
  * Automatically provisions CRM Companies, Contacts, and Lead profiles.
  * Triggers category-specific workflows (e.g., auto-scheduling follow-ups, assigning employees, or dispatching welcome emails).

### 3. **Smart Threaded Conversations & Composer**
* **Threaded Replies**: Sent replies are stored under the corresponding original email `parent_id` thread.
* **Spam Prevention & Threading Headers**: Configures custom `In-Reply-To` and `References` headers matching the original email's `message_id` to ensure recipient clients (like Gmail) group conversations correctly and bypass spam spam filters.
* **Multi-Account Sender Selector**: The compose window lists all connected SMTP accounts, allowing users to select their outbound identity dynamically.
* **Attachment Support**: Restricts and allows uploading attachments in `pdf, jpg, jpeg, png, webp, mp3, mp4, m4a` formats.
* **Emoji & UTF-8 Encoding**: Configured with a default UTF-8 charset block on PHPMailer to prevent encoding failures for emojis and special typography (like em-dashes).

### 4. **Chrome Scraper Extension (`extension/`)**
* Companion extension allowing outreach teams to scrape prospects on the fly (from LinkedIn, target websites, etc.) and inject leads directly into the CRM database pipeline.

---

## 🛠️ Installation & Setup

### 1. Database Migrations
Run the public self-healing migration script to set up all initial tables (`users`, `crm_leads`, `received_emails`, `email_attachments`, `crm_timeline`, etc.):
```bash
php backend/api/crm/migrate.php
```
*(Or navigate to `https://your-domain.com/backend/api/crm/migrate.php` in a web browser)*

### 2. Configuration Settings
* Copy `backend/config.example.php` to `backend/config.php`.
* Set your database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
* Configure your primary API keys (`OPENROUTER_API_KEY`, `GITHUB_TOKEN`, etc.).

### 3. Background Synchronization Cron Job
To ensure emails are pulled and processed by the AI in the background, set up a cron job pointing to the consolidated synchronization script:

#### Option A: CLI Crontab (Recommended)
```bash
* * * * * php /path/to/project/backend/cron_sync.php >> /path/to/project/backend/logs/cron.log 2>&1
```

#### Option B: HTTP Trigger (cPanel/cURL)
```bash
* * * * * curl -s "https://your-domain.com/backend/cron_sync.php?token=LP_CRON_SYNC_KEY_5a4c98" > /dev/null 2>&1
```

---

## 📂 Codebase Directory Map

* **`dashboard/`**: Single Page Application (SPA) dashboard files, custom templates, views, and routing mechanisms (`assets/js/crm.js`).
* **`backend/`**:
  * `config.php`: Database connection, AI endpoints, and helper modules.
  * `sync_helper.php`: Fast IMAP email downloader.
  * `queue_worker.php`: Asynchronous background AI analyst.
  * `cron_sync.php`: Unified scheduler entry point.
  * `smtp_helper.php` / `imap_helper.php`: Network connectors for SMTP outbound/IMAP inbound.
  * `jwt_helper.php`: Secure JSON Web Token authentication system.
* **`extension/`**: Source files for the companion Chrome prospect extension.
* **`scraper_service/`**: Core scripts for lead web intelligence scraping.
* **`assets/`**: Static site branding and marketing assets.
