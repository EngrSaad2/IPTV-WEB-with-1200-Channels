## 👨‍💻 Developer & Team Contacts

* **Developer:** Engr Saad
* **Company:** [Triangle Technology](https://triangletech.com.bd/)
* **Email:** [saad@triangletech.com.bd](mailto:saad@triangletech.com.bd)
* **Website:** [https://engr-saad.com](https://engr-saad.com)
* **LinkedIn:** [engr-saad](https://www.linkedin.com/in/engrsaad2/)
* **Facebook:** [Engr.Saad.2](https://www.facebook.com/Engr.Saad.2/)
* **WhatsApp:** [+8801810536303](https://wa.me/8801810536303)

# LiveTVweb - Premium IPTV Web Application & Movie Portal

<img width="1891" height="895" alt="image" src="https://github.com/user-attachments/assets/b3f4f0c3-d917-454a-8aec-ab3443547a79" />


LiveTVweb is a state-of-the-art, high-performance web application built to stream over 1200+ Live IPTV channels and offer an immersive movie portal. Migrated from an Android native codebase, it delivers an optimized, responsive, and secure streaming experience directly in modern web browsers.

---

## 🚀 Key Features

### 📺 Live TV & IPTV Streaming
* **1200+ Live Channels:** Stream high-definition television feeds seamlessly.
* **Low-Latency Streaming Player:** Custom-tuned Hls.js player parameters (optimized buffer, live sync edge, and instant startup) for buffering-free playback.
* **Sports Channel Prioritization:** Automatically bubbles up top FIFA World Cup 2026 broadcasters and sports channels (`T Sports HD`, `PTV Sports HD`, `A Sports HD`, `DD Sports`) to the top of the sports list.
* **Auto-Play on Boot:** Automatically boots up and runs `T Sports HD` as the default stream on player load.
* **Smart Stream Upgrades:** Automatically detects and upgrades duplicate channels from insecure HTTP to secure HTTPS streams to bypass mixed-content browser restrictions.
* **Dynamic Category Filters:** Quickly filter channels by categories: `Bangladeshi`, `Movies`, `Sports`, `Global`, and `Islamic`.

### 🎬 Movie Hub (TMDB Integration)
* **Concurrent Page Loading:** Fetches data across multiple pages concurrently for fast browsing.
* **Smart Categorization:** Separate sections for Trending, New Releases, Top Rated, and Genre-based filters.
* **Adult Filter:** Strict filtering based on genres, vote counts, and blacklisted keywords to keep recommendations safe.
* **Search Engine:** Query movies instantly by title.

### 🛠️ Advanced Utilities
* **Favorites System:** Save preferred channels and movies locally for quick access.
* **Screen Wake Lock:** Integrates the browser's Wake Lock API to prevent screens from sleeping during long streaming sessions.
* **Developer Profiles:** Manage custom settings and user profile states.

---

## 🛠️ Technical Architecture

### Tech Stack
* **Backend Framework:** Laravel 11 / PHP 8.2+
* **Frontend Design:** Vanilla CSS with HSL design variables and CSS Grid layouts.
* **Streaming Engine:** Hls.js library integration with custom buffering parameters.
* **Database / Cache:** Local Laravel cache with automated fallback to native JSON feeds.

### Connectivity Optimization
* **JsDelivr CDN integration:** Channel feeds are loaded via IPv6-enabled CDN paths for high availability and low latency (< 100ms).
* **Dual IP Fallback:** Adaptive HTTP client (`adaptiveGet`) automatically falls back between IPv4 and IPv6 to bypass strict server outgoing blocks (e.g., Hostinger IPv4 outbound firewall blocks).

---

## ⚙️ Installation & Configuration

### Prerequisites
* PHP 8.2+
* Composer
* MySQL / SQLite

### Setup Instructions

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/EngrSaad2/IPTV-WEB-with-1200-Channels.git
   cd IPTV-WEB-with-1200-Channels
   ```

2. **Install Dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment Variables:**
   Copy `.env.example` to `.env` and fill in the required keys:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Environment Variables Reference:**
   * `TMDB_API_KEY`: API key for The Movie Database integration.
   * `TMDB_READ_TOKEN`: API read access token for movie metadata.
   * `SUPABASE_URL` / `SUPABASE_KEY`: Supabase credentials for remote storage.
   * `RAPIDAPI_KEY`: API keys for supplementary feeds.
   * `WEBHOOK_SECRET`: HMAC SHA-256 signature secret for automated deployments.

5. **Run Migrations & Cache Optimization:**
   ```bash
   php artisan migrate
   php artisan optimize
   ```

6. **Start Local Development Server:**
   ```bash
   php artisan serve
   ```

---

## 🛡️ Secure CI/CD Webhook Deployment

This project includes a secure deployment script [deploy-webhook.php](file:///c:/xampp/htdocs/LiveTVweb/public/deploy-webhook.php) to facilitate automated updates from GitHub. 

* **Signature Verification:** Uses GitHub webhook payloads with HMAC SHA-256 signatures validated against the private `WEBHOOK_SECRET` stored securely in `.env`.
* **Zero-Downtime Pipeline:** Executes safe Git pull, reset, composer install, migrations, and optimization steps cleanly in a single execution flow.
* **Logging:** Logs deployment status, step outputs, and failures into `storage/logs/deploy.log` for easy troubleshooting.

---


