Nombre del proyecto: Cine Concordia - Sistema web de venta de entradas online.

Integrantes
Leonardo Mover, Santiago Jacobo, Nazareno Rodriguez y Valentín Réboli

Descripción del proyecto
Cine Concordia será una plataforma web destinada a la gestión y venta de entradas online para un cine. El sistema permitirá a los usuarios consultar la cartelera, conocer la información de las películas, seleccionar una función, elegir el día y horario, y seleccionar sus butacas mediante un mapa interactivo que mostrará su disponibilidad en tiempo real.

El proyecto contará con un sistema de compra online y generación de entradas digitales con código QR. Además, se utilizará n8n para automatizar el envío de confirmaciones de compra mediante Telegram y correo electrónico.

El objetivo principal es desarrollar una aplicación web completa que permita digitalizar y centralizar la gestión del cine y el proceso de compra de entradas, integrando front-end, back-end y base de datos, y aplicando los conceptos trabajados durante la cursada.

Problema
La empresa parte de la necesidad de modernizar el proceso de venta y gestión de entradas del cine. La dependencia de procesos presenciales y la falta de una plataforma centralizada dificultan a los clientes consultar la cartelera, conocer la disponibilidad de butacas y adquirir sus entradas de manera rápida y cómoda.

A su vez, la gestión de películas, funciones, salas, horarios, butacas y ventas puede requerir tareas manuales que aumentan la posibilidad de errores, como la asignación incorrecta de butacas o la venta de un mismo lugar a más de un cliente.

Frente a esta problemática, la empresa necesita una solución que permita centralizar la información, digitalizar la venta de entradas y automatizar parte de las tareas de gestión y comunicación con los clientes.

Requerimientos
Para solucionar las problemáticas planteadas, el sistema deberá cumplir con los siguientes requerimientos:

Permitir a los usuarios consultar la cartelera y obtener información sobre las películas disponibles.
Permitir seleccionar una película y consultar las funciones disponibles según el día, horario y sala.
Mostrar un mapa interactivo de las butacas correspondientes a cada función.
Mostrar en tiempo real la disponibilidad de las butacas.
Permitir seleccionar una o varias butacas disponibles para una función.
Garantizar que una misma butaca no pueda ser adquirida por más de un usuario para una misma función.
Permitir realizar la compra de entradas de manera online.
Registrar las compras y entradas generadas en la base de datos.
Generar una entrada digital asociada a cada compra.
Generar un código QR para identificar y validar cada entrada.
Enviar automáticamente la confirmación de la compra mediante correo electrónico y Telegram utilizando n8n.
Permitir a los administradores gestionar películas, funciones, salas, horarios y butacas.
Contar con un sistema de autenticación y gestión de usuarios.
Centralizar la información del sistema en una única base de datos.
Problemas que resuelve
La implementación del sistema permitirá solucionar las principales problemáticas identificadas en el proceso actual:

Digitalización de la venta: permite a los clientes comprar sus entradas de manera online sin depender de la compra presencial.
Acceso a la información: permite consultar la cartelera, películas, funciones, horarios y salas desde una única plataforma.
Gestión de butacas: permite visualizar la disponibilidad en tiempo real y seleccionar los lugares antes de realizar la compra.
Prevención de errores: evita la asignación o venta duplicada de una misma butaca para una función.
Centralización de la información: concentra en una única base de datos la información relacionada con películas, funciones, salas, usuarios, compras y entradas.
Automatización de tareas: reduce las tareas manuales mediante el envío automático de confirmaciones y la generación de entradas digitales.
Validación de entradas: facilita el control de acceso mediante códigos QR asociados a cada entrada.
Gestión administrativa: proporciona al personal del cine una plataforma desde la cual administrar los diferentes elementos del sistema.
Mejora de la experiencia del cliente: ofrece un proceso de compra más rápido, accesible y organizado, desde la consulta de la cartelera hasta la obtención de la entrada.

## Autenticación con Google

Este proyecto puede integrar un inicio de sesión con Google usando OAuth 2.0.

La variable que se usa en la app para el identificador del cliente de Google es:

- `GOOGLE_CLIENT_ID`

Importante: para inicio de sesión con Google no se usa una "API key" como tal, sino un Client ID de OAuth (público) y, en algunos flujos del backend, también puede existir un `GOOGLE_CLIENT_SECRET` para intercambiar el código de autenticación.

En este repositorio, la configuración se ve así:

- `backend/.env` o `.env.example` incluye:
  ```env
  GOOGLE_CLIENT_ID=tu_client_id_de_google.apps.googleusercontent.com
  ```
- En el backend se lee en:
  ```php
  'google' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
  ],
  ```

Pasos para configurarlo:

1. Crear un proyecto en Google Cloud Console.
2. Habilitar la API de "Google Identity / OAuth 2.0".
3. Crear un OAuth Client ID del tipo "Web application".
4. Agregar los dominios o URLs autorizadas (por ejemplo `http://localhost:4200`).
5. Copiar el Client ID y guardarlo en la variable `GOOGLE_CLIENT_ID`.

Si se va a usar login con Google desde el frontend, normalmente solo hace falta el `GOOGLE_CLIENT_ID`. Si el backend intercambia el token con Google, puede requerir además `GOOGLE_CLIENT_SECRET`.


## Despliegue en Railway

Se despliega como un solo servicio desde el `Dockerfile` de la raíz (Caddy sirve el front y reenvía `/api` y `/docs` a Laravel) más una base PostgreSQL. Railway despliega solo cada vez que se pushea a `main`.

Variables del servicio web (cargarlas en Railway → Variables; nunca commitear valores reales):

| Variable | Para qué sirve |
|---|---|
| `APP_KEY` | Clave de Laravel. Generar con `php artisan key:generate --show`. |
| `APP_ENV` / `APP_DEBUG` | `production` / `false`. |
| `APP_URL` | URL pública, por ejemplo `https://cineconcordia.up.railway.app`. De acá sale el link del QR en los comprobantes. |
| `LOG_CHANNEL` | `stderr`, para ver los logs en Railway. |
| `DB_CONNECTION` | `pgsql`. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Referencias al servicio Postgres: `${{Postgres.PGHOST}}`, `${{Postgres.PGPORT}}`, `${{Postgres.PGDATABASE}}`, `${{Postgres.PGUSER}}`, `${{Postgres.PGPASSWORD}}`. |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Admin inicial del panel. La contraseña se vuelve a aplicar en cada arranque. |
| `GOOGLE_CLIENT_ID` | Client ID de Google (debe ser el mismo que usa el front en `environment.production.ts`). |
| `MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY`, `MP_WEBHOOK_SECRET`, `MP_MODO_PRUEBA` | Credenciales de Mercado Pago (Checkout Pro) y modo prueba (`true`/`false`). |
| `MP_MONTO_COBRO` | Opcional. Monto fijo en pesos que se le cobra a Mercado Pago por compra (por ejemplo `0.10`), sin cambiar el precio que ve el cliente. Solo para probar cobros reales; **dejar vacío para cobrar el total**. |
| `N8N_COMPRA_WEBHOOK` | Webhook de n8n que manda el comprobante, el QR y el código de descuento al comprador. |
| `N8N_CAMPANIAS_WEBHOOK` | Webhook de n8n que envía las campañas de publicidad. |
| `DESCUENTO_PORCENTAJE`, `DESCUENTO_VIGENCIA_DIAS` | Descuento que se regala en cada compra (por defecto 10 % y 30 días). Opcionales. |
| `PORT` | Opcional; Railway lo define solo. |

Las dos variables de n8n son opcionales: sin ellas la compra se confirma igual, pero no se envían los mensajes.

Después de un deploy, verificar `/api/estado` (estado de la base) y `/docs` (Swagger).
