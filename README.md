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
