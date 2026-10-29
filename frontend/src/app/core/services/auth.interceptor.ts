import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';

const TOKEN_KEY = 'cc_admin_token';

// Agrega el Bearer token a todas las requests salientes, si hay uno guardado.
// Si la API lo rechaza (401), la sesion vencio en el servidor: se cierra y se vuelve al login.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = localStorage.getItem(TOKEN_KEY);

  if (!token) {
    return next(req);
  }

  const auth = inject(AuthService);

  return next(
    req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
    }),
  ).pipe(
    catchError((err: unknown) => {
      if (err instanceof HttpErrorResponse && err.status === 401) {
        auth.cerrarPorInactividad();
      }
      return throwError(() => err);
    }),
  );
};
