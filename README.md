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

