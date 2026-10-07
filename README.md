# e-Sarkari Yojna CMS

Sarkari yojnaon ki jaankari wali website (esarkariyojna.com) aur uska admin panel (WordPress jaisa).
PHP 8 + MySQL, koi framework ya composer nahi, isliye seedha CloudPanel VPS par chalta hai.

## Kya-kya hai

- **Admin panel** `/admin/`: yojnayein, pages, categories, media library, header/footer menu, logo, colors, header/footer text, SEO.
- **Code & Tracking**: Google Analytics 4 ID, Google Tag Manager ID, header/body/footer me custom code.
- **Apni tracking (database me)**: har page visit aur har link click. Samay, page, source (Google/WhatsApp/Facebook...), UTM, IP, city/state/country, device, OS aur browser save hote hain. CSV export bhi hai.
- **Tracking links** `/go/<naam>`: WhatsApp, YouTube etc. par share karne ke liye short links, har click count hota hai.
- **Users, roles, permissions**: Administrator, Editor, Author, Analyst ke saath apne roles bhi bana sakte hain.
- **Activity log**: kis user ne kab kya badla.

## Pehli baar setup (VPS par)

1. CloudPanel → **Databases → Add Database**. Database name, user aur password note kar lein.
2. Code `main` branch me push hote hi GitHub Actions use VPS par bhej deta hai (`.github/workflows/deploy-vps.yml`).
3. Browser me `https://esarkariyojna.com/install.php` kholein. DB details aur apna admin email/password daalein.
4. Install ke baad `https://esarkariyojna.com/admin/` par login karein.

Config file (`esy-config.php`) web folder ke bahar `htdocs/` me banti hai, isliye deploy use kabhi overwrite nahi karta.
Uploaded images `uploads/` me rehti hain. Deploy kuch delete nahi karta, isliye ye bhi safe hain.

## Code structure

```
index.php            Website router (home, /yojna/, /page/, /category/, /go/, sitemap, robots)
install.php          Pehli baar install
app/bootstrap.php    DB, settings, auth, permissions, helpers
app/tracking.php     Visits / clicks / IP location
app/schema.sql       Database tables
app/seed/*.json      Shuruaati content (install par import hota hai)
app/views/           Website templates
admin/               Admin panel (admin/pages/* har screen)
assets/              CSS, JS, logo
```

## Local par chalana

```
php -S 127.0.0.1:8080
```
Phir `http://127.0.0.1:8080/install.php` kholein (local MySQL chahiye).
