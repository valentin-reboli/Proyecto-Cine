import { HttpInterceptorFn } from '@angular/common/http';

const TOKEN_KEY = 'cc_admin_token';

// Agrega el Bearer token a todas las requests salientes, si hay uno guardado.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = localStorage.getItem(TOKEN_KEY);

  if (!token) {
    return next(req);
  }

  return next(
    req.clone({
      setHeaders: { Authorization: `Bearer ${token}` },
    }),
  );
};
