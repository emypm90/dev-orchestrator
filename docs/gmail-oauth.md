# Conectar Gmail con Google

Esta integración guarda la autorización local de una cuenta Gmail o Google Workspace y puede leer cadenas reales mediante la API de Gmail en modo solo lectura.

## Flujo actual por etapas

1. OAuth conecta Gmail y almacena la autorización local.
2. La pantalla de Gmail permite buscar cadenas recientes, importar una seleccionada y crear un borrador de ticket. También permite pegar una cadena manualmente.
3. El borrador presenta resumen, expectativas funcionales y preguntas abiertas para revisión explícita.
4. Solo después de revisar se crea un ticket operativo en `triage`.

Los hilos importados se normalizan y pasan por el mismo generador. Cuando OpenAI no está configurado, usa el generador local determinista. Importar una cadena nunca crea un ticket; la revisión explícita sigue siendo obligatoria.

## Borradores con OpenAI

Para generar borradores con OpenAI, abrí **Configuración** en el dashboard y cargá la clave, el modelo y la opción de habilitación. Los secretos se cifran en SQLite con `APP_KEY` y nunca se muestran completos. Como alternativa o fallback, podés agregar estas variables a tu `.env` local, sin versionar la clave:

```dotenv
OPENAI_API_KEY=
OPENAI_TICKET_DRAFT_MODEL=gpt-5.5
OPENAI_TICKET_DRAFT_ENABLED=true
```

Con una clave configurada y la opción habilitada, la aplicación envía el asunto, los participantes y el texto completo de la cadena a OpenAI Responses para obtener un JSON estructurado. Solo se conserva el payload propuesto (resumen, expectativas, preguntas y campos del ticket); no se guarda la respuesta cruda de OpenAI. Si falta la clave, la opción está deshabilitada, la API falla o devuelve un payload inválido, se usa automáticamente el generador local determinista sin bloquear el borrador.

El texto de los correos solo se envía a OpenAI cuando hay una clave configurada y la generación está habilitada. Revisá las políticas de privacidad y retención aplicables antes de usar esta opción con información sensible. La clave no se muestra ni registra; cuando se guarda desde el dashboard, queda cifrada en SQLite.

## Configuración en Google Cloud

1. Creá o elegí un proyecto en [Google Cloud Console](https://console.cloud.google.com/).
2. Configurá la pantalla de consentimiento OAuth. Para una cuenta Workspace, verificá con el administrador si debe ser interna.
3. En **APIs y servicios**, habilitá la Gmail API.
4. Creá credenciales de tipo **ID de cliente OAuth**, aplicación web.
5. Agregá la URL de redirección autorizada local. Con el launcher del proyecto suele ser `http://127.0.0.1:8001/integrations/gmail/oauth/callback`; ajustá host o puerto si corresponde.
6. Copiá el ID, secreto y URI de redirección en **Configuración** del dashboard. Como alternativa o fallback, podés cargarlos al `.env` local, sin versionarlos:

```dotenv
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
GOOGLE_OAUTH_REDIRECT_URI=/integrations/gmail/oauth/callback
```

Podés usar una URL absoluta en `GOOGLE_OAUTH_REDIRECT_URI` si el host local no coincide con `APP_URL`.

## Permisos y almacenamiento

La autorización solicita únicamente `gmail.readonly`, `userinfo.email` y `userinfo.profile`; no puede enviar, borrar ni modificar correos. Los tokens y credenciales guardadas desde el dashboard se almacenan en SQLite con casts `encrypted` de Laravel, cifrados con la clave local de la aplicación. Protegé `.env`, `APP_KEY` y `database/database.sqlite` como secretos locales: perder `APP_KEY` impide descifrar los valores guardados. Desconectar borra los tokens almacenados, aunque no revoca el acceso en Google; para revocarlo completamente, retiralo también desde la cuenta de Google.
