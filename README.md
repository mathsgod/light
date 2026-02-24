[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/mathsgod/light)

# light

## Database setup

config are based on [mathsgod/light-db](https://github.com/mathsgod/light-db)

.env
```ini
DATABASE_HOSTNAME=
DATABASE_DATABASE=
DATABASE_USERNAME=
DATABASE_PASSWORD=
DATABASE_PORT=
DATABASE_CHARSET=
```


## Secret
    
Random string for jwt secret

.env
```ini
JWT_SECRET= # jwt secret
```

## Google Signin

Install google api client
```
composer require google/apiclient
```

.env
```ini
GOOGLE_CLIENT_ID=
```

Quick start
-----------

1. Copy .env.example to .env and fill values (especially DATABASE_ and JWT_SECRET):

```bash
cp .env.example .env
# generate a strong JWT_SECRET (example):
php -r "echo bin2hex(random_bytes(32));"
```

2. Install dependencies:

```bash
composer install
```

3. Run with PHP built-in server (development):

```bash
php -S 0.0.0.0:8080 -t public index.php
```

Notes on JWT_SECRET
-------------------
- The application uses HS256 for JWT by default. Use a strong random secret (at least 32 bytes hex) and keep it private.
- For higher security consider using asymmetric keys (RS256) and storing private keys securely.
