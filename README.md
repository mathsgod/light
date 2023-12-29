# light

## Database setup

config are based on [mathsgod/r-db](https://github.com/mathsgod/r-db)

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

## Auth Lock Policy
.env
```ini
AUTH_LOCK_TIME =  # in seconds, default: 180
AUTH_LOCK_MAX_ATTEMPTS = # default: 5
```
