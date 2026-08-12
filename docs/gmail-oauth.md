# Conectar Gmail con Google

Esta integración solo guarda la autorización local de una cuenta Gmail o Google Workspace. Todavía no lee, descarga ni importa mensajes; esa será una etapa posterior para seleccionar correos y crear tickets operativos.

## Configuración en Google Cloud

1. Creá o elegí un proyecto en [Google Cloud Console](https://console.cloud.google.com/).
2. Configurá la pantalla de consentimiento OAuth. Para una cuenta Workspace, verificá con el administrador si debe ser interna.
3. En **APIs y servicios**, habilitá la Gmail API.
4. Creá credenciales de tipo **ID de cliente OAuth**, aplicación web.
5. Agregá la URL de redirección autorizada local. Con el launcher del proyecto suele ser `http://127.0.0.1:8001/integrations/gmail/oauth/callback`; ajustá host o puerto si corresponde.
6. Copiá el ID y secreto al `.env` local, sin versionarlos:

```dotenv
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
GOOGLE_OAUTH_REDIRECT_URI=/integrations/gmail/oauth/callback
```

Podés usar una URL absoluta en `GOOGLE_OAUTH_REDIRECT_URI` si el host local no coincide con `APP_URL`.

## Permisos y almacenamiento

La autorización solicita únicamente `gmail.readonly`, `userinfo.email` y `userinfo.profile`; no puede enviar, borrar ni modificar correos. Los tokens se guardan en SQLite con casts `encrypted` de Laravel, cifrados con la clave local de la aplicación. Protegé `.env`, `APP_KEY` y `database/database.sqlite` como secretos locales. Desconectar borra los tokens almacenados, aunque no revoca el acceso en Google; para revocarlo completamente, retiralo también desde la cuenta de Google.
