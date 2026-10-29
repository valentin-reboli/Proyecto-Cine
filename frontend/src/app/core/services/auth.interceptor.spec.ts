import { TestBed } from '@angular/core/testing';
import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from './auth.service';
import { API_URL } from './api.config';

describe('authInterceptor', () => {
  let http: HttpClient;
  let backend: HttpTestingController;
  let auth: AuthService;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
    auth = TestBed.inject(AuthService);
  });

  afterEach(() => {
    backend.verify();
    localStorage.clear();
  });

  it('manda el token guardado', () => {
    localStorage.setItem('cc_admin_token', 'tok-1');
    http.get(`${API_URL}/usuarios`).subscribe();

    const req = backend.expectOne(`${API_URL}/usuarios`);
    expect(req.request.headers.get('Authorization')).toBe('Bearer tok-1');
    req.flush([]);
  });

  it('si la API rechaza el token (sesion vencida) cierra la sesion', () => {
    localStorage.setItem('cc_admin_token', 'tok-vencido');
    const cerrar = spyOn(auth, 'cerrarPorInactividad');

    http.get(`${API_URL}/usuarios`).subscribe({ error: () => {} });
    backend.expectOne(`${API_URL}/usuarios`).flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(cerrar).toHaveBeenCalled();
  });

  it('otros errores no cierran la sesion', () => {
    localStorage.setItem('cc_admin_token', 'tok-1');
    const cerrar = spyOn(auth, 'cerrarPorInactividad');

    http.get(`${API_URL}/usuarios`).subscribe({ error: () => {} });
    backend.expectOne(`${API_URL}/usuarios`).flush(null, { status: 500, statusText: 'Error' });

    expect(cerrar).not.toHaveBeenCalled();
  });
});
