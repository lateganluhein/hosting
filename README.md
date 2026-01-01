# ManyCents Resources Website

Corporate marketing website for ManyCents Resources - a coal mining and trading company.

**Live:** https://www.manycents.co.za

## Tech Stack

- **Frontend:** HTML5, CSS (embedded), Vanilla JavaScript
- **Backend:** PHP (contact form only)
- **Server:** Apache with .htaccess

## Features

- Single-page responsive design
- Contact form with SMTP email
- Spam prevention (honeypot + IP rate limiting)
- Google Analytics integration
- HTTPS enforcement

## Files

```
index.html      - Main website (single-page with embedded CSS/JS)
contact.php     - Form handler with SMTP email
.htaccess       - Apache config (HTTPS, caching, gzip)
```

## Deployment

No build process required - deploy files directly to web server root.

## Contact Form

**POST /contact.php**

| Field | Required | Description |
|-------|----------|-------------|
| name | Yes | Visitor name |
| company | Yes | Company name |
| email | Yes | Email address |
| product | No | Product interest |
| message | Yes | Message content |

## Testing

```bash
# Test against production
python test_contact.py https://www.manycents.co.za
```
