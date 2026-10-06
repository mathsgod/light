# WebAuthn origins

WebAuthn requires HTTPS by default. HTTP localhost development is supported
only when explicitly configured:

```dotenv
RP_ID=localhost
WEBAUTHN_ALLOWED_ORIGINS=http://localhost:3000
```

Open the frontend using exactly `http://localhost:3000`, not a different port
or IP address. The RP ID is a hostname only, without scheme or port. Local
credentials are separate from production credentials; production passkeys
cannot be used on localhost.

For production, either keep the default HTTPS/RP-ID validation or configure
exact HTTPS origins:

```dotenv
RP_ID=example.com
WEBAUTHN_ALLOWED_ORIGINS=https://app.example.com
```

Separate multiple origins with commas. Each must match RP_ID or its subdomain.
HTTP non-localhost origins, paths, queries and wildcard origins are rejected.
Subdomain matching is not automatic: enumerate each allowed full origin.
Do not copy development settings to production.

This setting is used by both registration and login validation. It does not
disable challenge, signature, user verification or RP-ID hash checks. Creation
options omit null authenticatorAttachment values to avoid browser warnings.
