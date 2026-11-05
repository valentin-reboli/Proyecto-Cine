# n8n: correo de compra confirmada

Cuando Mercado Pago confirma un pago (webhook o vuelta de Checkout Pro), el backend genera el código de
descuento de regalo y le avisa a n8n (`CompraNotifier`). n8n descarga el comprobante en PDF y le manda al
comprador un correo con los datos de la entrada, el QR, el código de regalo y el PDF adjunto.

```
Mercado Pago ──webhook──▶ Laravel (PagoService) ──POST + X-Cine-Token──▶ n8n ──Gmail──▶ comprador
                                   ▲                                       │
                                   └──── GET /api/entradas/comprobante/{codigo}.pdf
```

Si n8n no responde, la compra queda confirmada igual y `php artisan comprobantes:reenviar` (corre solo
cada 10 minutos) lo vuelve a intentar durante 24 horas.

## 1. Levantar n8n en Railway

1. En el proyecto de Railway: **New → Template** y elegir **n8n** (el que trae Postgres). Deploy.
2. En el servicio de n8n, **Settings → Networking → Generate Domain** (por ejemplo `cine-n8n.up.railway.app`).
3. Revisar las variables del servicio de n8n (el template ya trae casi todas):
   - `WEBHOOK_URL=https://cine-n8n.up.railway.app/` (el dominio del paso anterior, con `/` al final)
   - `N8N_ENCRYPTION_KEY` con un valor fijo (si cambia, se pierden las credenciales guardadas)
   - `GENERIC_TIMEZONE=America/Argentina/Buenos_Aires`
4. Entrar al dominio y crear el usuario dueño de n8n.

## 2. Cuenta de Gmail que manda los correos

Usamos el nodo **Gmail** (API de Gmail por HTTPS) y no SMTP, porque Railway bloquea el SMTP saliente
(puertos 465/587) en los planes Free/Trial/Hobby. Gmail gratis permite unos 500 correos por día, de sobra
para el proyecto.

1. Crear una cuenta Gmail del equipo (por ejemplo `cineconcordia.utn@gmail.com`).
2. En [Google Cloud Console](https://console.cloud.google.com/), con esa cuenta:
   1. Crear un proyecto nuevo.
   2. **APIs y servicios → Biblioteca → Gmail API → Habilitar**.
   3. **Pantalla de consentimiento de OAuth**: tipo *Externo*, nombre "Cine Concordia", correo de soporte
      la cuenta del equipo.
   4. **Credenciales → Crear credenciales → ID de cliente de OAuth → Aplicación web**. En *URI de
      redireccionamiento autorizados* poner `https://cine-n8n.up.railway.app/rest/oauth2-credential/callback`
      (n8n muestra esta URL exacta al crear la credencial).
   5. En la pantalla de consentimiento, **Publicar app** (pasarla a *En producción*). Si queda en *Prueba*,
      Google vence el permiso cada 7 días y los correos dejan de salir.
3. En n8n: **Credentials → New → Gmail OAuth2 API**, pegar el *Client ID* y el *Client Secret* y tocar
   **Sign in with Google** con la cuenta del equipo. Google avisa que la app no está verificada:
   *Configuración avanzada → Ir a Cine Concordia* (es nuestra propia app).

## 3. Importar el workflow

1. Generar un token largo al azar, por ejemplo con `openssl rand -hex 32`.
2. En n8n: **Credentials → New → Header Auth**. Name: `X-Cine-Token`, Value: el token.
3. **Workflows → Import from file** → [`compra-confirmada.json`](compra-confirmada.json).
4. Abrir cada nodo y elegir su credencial:
   - **Webhook**: la credencial *Header Auth* del paso 2.
   - **Enviar correo (Gmail)**: la credencial *Gmail OAuth2 API*.
5. Guardar y activar el workflow (switch **Active**).

## 4. Variables del backend en Railway

En el servicio del cine (no en el de n8n):

| Variable | Valor |
| --- | --- |
| `N8N_COMPRA_WEBHOOK` | `https://cine-n8n.up.railway.app/webhook/compra-confirmada` |
| `N8N_WEBHOOK_TOKEN` | El mismo token de la credencial *Header Auth* |

`APP_URL` tiene que ser la URL pública del cine (`https://cineconcordia.up.railway.app`): con ella se arman
las URLs del QR y del PDF que usa n8n.

## 5. Probar

1. Hacer una compra en el sitio con una tarjeta de prueba de Mercado Pago y un correo propio.
2. En n8n, **Executions** muestra cada aviso recibido y si el correo salió.
3. Para probar solo el workflow, abrir el nodo Webhook, tocar **Listen for test event** y mandar el aviso a
   la URL de prueba (`/webhook-test/...`) con el ejemplo de abajo.

```bash
curl -X POST https://cine-n8n.up.railway.app/webhook-test/compra-confirmada \
  -H "Content-Type: application/json" -H "X-Cine-Token: <token>" \
  -d @ejemplo-payload.json
```

Para que el PDF del ejemplo exista, `comprobante_pdf_url` tiene que ser de una compra pagada real.

## Datos que manda el backend

Ver [`ejemplo-payload.json`](ejemplo-payload.json). `descuento_aplicado` es el código que usó el comprador en
esta compra (o `null`); `descuento` es el código de regalo para la próxima. `mensaje` es un resumen en texto
plano por si se quiere mandar también por Telegram.
