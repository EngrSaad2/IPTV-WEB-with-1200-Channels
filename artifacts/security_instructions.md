# Cloudflare DDoS Protection & Security Configuration Guide

While application-level rate limiting and input filtering (via Laravel middlewares) protect your server's backend resources, full protection against volumetric DDoS attacks requires a network-level shield. Cloudflare is recommended to act as your reverse proxy and security perimeter.

---

## 1. Cloudflare DNS Proxying

To block direct DDoS attacks to your hosting IP, you must hide your origin IP address behind Cloudflare's network.

1. Log in to your **Cloudflare Dashboard**.
2. Navigate to **DNS > Records**.
3. Locate your root domain (`example.com`) and subdomains (`www`).
4. Ensure the **Proxy Status** toggled is set to **Proxied** (orange cloud icon). This routes all incoming traffic through Cloudflare's globally distributed Edge servers.

---

## 2. Web Application Firewall (WAF) Configuration

Set up basic firewall rules in Cloudflare to intercept automated bots and malicious path scanners before they even touch your Laravel server.

1. Navigate to **Security > WAF > Custom Rules**.
2. Click **Create rule**.
3. Configure the following basic rule to block scanning tools:
   - **Rule Name**: Block Sensitive Path Scanners
   - **Field**: `URI Path`
   - **Operator**: `matches regex`
   - **Value**: `(\.env|\.git|wp-admin|wp-login|xmlrpc\.php|composer\.(json|lock))`
   - **Action**: **Block**
4. Configure another rule to challenge suspicious or automated traffic:
   - **Rule Name**: Challenge Automated Bots
   - **Field**: `Threat Score`
   - **Operator**: `greater than`
   - **Value**: `10`
   - **Action**: **Managed Challenge (JS Challenge)**

---

## 3. Rate Limiting Rules (DNS level)

Configure rate limits at Cloudflare's Edge to prevent high-frequency scraping or DDoS hits from exhausting host resources.

1. Under **Security > WAF**, select **Rate limiting rules**.
2. Click **Create rule**.
3. Set the following parameters:
   - **Rule Name**: Rate Limit API Endpoints
   - **Field**: `URI Path`
   - **Operator**: `starts with`
   - **Value**: `/api/`
   - **Rate Limit Condition**:
     - **Requests**: `60`
     - **Period**: `1 minute`
   - **Action**: **Block** or **Managed Challenge** for 1 hour.

---

## 4. Under Attack Mode (Emergency Only)

If your website is actively experiencing a massive volumetric DDoS attack that slows down the site:

1. On the Cloudflare home page, locate the **Quick Actions** panel.
2. Toggle **Under Attack Mode** to **On**.
3. This will display a clean cryptographic challenge (Cloudflare Turnstile) to all visitors for 5 seconds before letting them pass, filtering out 99.9% of DDoS botnets.
