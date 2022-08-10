# Example of Symfony authentication with Keycloak server as SSO

## Start Keycloak server

The application is intended to be used with a Keycloak server in a Docker container. To start it:

```bash
$ docker compose up -d
```
Keycloak now runs on the arbitrary chosen port `52957`. In your browser, go to `http://localhost:52957/` and follow *Administration Console* link. The credentials are **admin**/**admin**.

## Keycloak configuration

### Client configuration

First, let's create a new OpenId client. Go to *Clients* link in the menu and use the *Create* button to add a client. Use `symfony-app` as *Client ID* and keep `openid-connect` as *Client Protocol*.

After creation, a screen with a lot of configuration options appears. In the *Access type* field of the *Settings* tab, choose `confidential`. With this access type, you need at least one redirect url. Type `http://localhost:8000/redirect-uri` in *Valid Redirect URIs* (also in the *Settings* tab). You can now save your modifications.

Go to the *Credentials* tab and copy the *Secret* field content somewhere. You are going to need it for the Symfony application configuration.

### Add Symfony specific roles
We are going to add a specific role for the application. Go to *Roles* menu entry, click on the *Add Role* button, type `ROLE_USER` as *role name* (case matters) and save your modification.

### User creation
Let's create an user for logging into our Symfony application. Go to the *Users* link from the left menu and click on the *Add user* button. Fill the *username*, *email*, *first name* and *last name* fields. Then save the user.

Some extra configuration options are now available. Go to the *Credentials* tab, chose a password, disable the *temporary* feature and save your modifications (a confirmation modal should appear, you can confirm your modifications).

You also need to add the `ROLE_USER` previously created to your user to be allowed to access to the profile page in the Symfony application. Go to *Role Mapping* and move `ROLE_USER` from *Available roles* to *Assigned roles*.

### Add roles to ID token
By default, role are not present in the ID token. To be allowed to get roles from the ID token, go to *Client Scopes* (left menu) and click on the *roles* scope. Then chose the *Mappers* tab, edit the *realm roles* line and set the *Add to ID token* toggle to `ON`. Save your modification. For a sake of transparency, in the settings tab, swith on the *Include In Token Scope* toggle. Otherwise roles won't be displayed in the scope list of keycloak responses.

### Public key
Keycloak configuration is done. But you need the public key to check JWT signature. Go to the menu entry *Realm Settings* and chose the *Keys* tab. Click on the *Public Key* button of the `RSA256` algorythm for a signing (`SIG`) usage. Copy the displayed value somewhere.

### Disconnect from admin account

Don't forget to logout, you can't use admin to login in the Symfony application since admin has no email (having an email is only a requirement for our implementation, not a general rule).

## Symfony application configuration

## Environment variables

You need to create a `.env.local` file at the root of the project. Add the following content:
```env
KEYCLOAK_CLIENDID=symfony-app
KEYCLOAK_CLIENTSECRET=624e2565-a612-4255-9522-35d27636e8c7
KEYCLOAK_PK="-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAhHUOz9Fwkx9TFR07flcEmn2aVCxKM9dLhTBvHwOYLzCSETWk3/lf/xwg/f2sicrsY2W/EZLrpDyKZSCuSzwbPp7DLSN9Ww8DnLJNLxFWL+LXgSY+IqoUZSKq/lPS/2N4bW61kz7clVgOMI1iWt2I+FAs6oRLfDRbOjIVWgMyT1W/pSrX5Y6nR8Q1VE+MfCE0QAlsYLpb9vxuh4jiOkpY+P+RqSj1ciTxuqic/k0HOvAaI1vJmIdJe3iQlVK/lxzHlaB+nY20WdVV2LVlFthvCVO6pH+I+pbHk1NkgYmXoKsm+on7epazT7Bg1K8eVpumcBG2sPX9R04RL5hz4WmWwwIDAQAB
-----END PUBLIC KEY-----"
KEYCLOAK_VERIFY_PEER=false
KEYCLOAK_VERIFY_HOST=false
```
Replace `KEYCLOAK_CLIENTSECRET` and `KEYCLOAK_PK` content by your own values you have previously copied.

Update `KEYCLOAK_VERIFY_PEER` and `KEYCLOAK_VERIFY_HOST` by true if you want to verify the peer/host when calling the Keyclock server.

## Start the Symfony application

For the sake of simplicity, we use the [Symfony local web server](https://symfony.com/doc/5.4/setup/symfony_server.html). At least PHP 8.0 is needed to run the application. Start the server:

```bash
$ symfony serve -d
```

Then, install the dependencies:

```bash
$ symfony composer install
```

You can now go to `http://localhost:8000` in your browser and try to login into the application with the user account you previously created :)