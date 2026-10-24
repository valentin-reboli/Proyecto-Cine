import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Router, provideRouter } from '@angular/router';
import { AuthService } from './auth.service';
import { API_URL } from './api.config';

describe('AuthService', () => {
  let service: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    service = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
  });

  it('el login con contraseña guarda la sesion', () => {
    expect(service.isLoggedIn()).toBeFalse();

    service.login('jacobo@cineconcordia.com', 'secreto123').subscribe();
    const req = http.expectOne(`${API_URL}/login`);
    expect(req.request.body).toEqual({ email: 'jacobo@cineconcordia.com', password: 'secreto123' });
    req.flush({ user: { id: 1, name: 'Jacobo', email: 'jacobo@cineconcordia.com' }, token: 'tok-1' });

    expect(service.isLoggedIn()).toBeTrue();
    expect(service.getToken()).toBe('tok-1');
  });

  it('el login con Google manda el credential y guarda la sesion', () => {
    service.loginWithGoogle('jwt-de-google').subscribe();
    const req = http.expectOne(`${API_URL}/login/google`);
    expect(req.request.body).toEqual({ credential: 'jwt-de-google' });
    req.flush({ user: { id: 1, name: 'Jacobo', email: 'jacobo@cineconcordia.com' }, token: 'tok-2' });

    expect(service.getToken()).toBe('tok-2');
  });

  it('cerrar sesion borra el token y lleva al login, aunque falle la API', () => {
    const router = TestBed.inject(Router);
    const navegar = spyOn(router, 'navigateByUrl').and.resolveTo(true);

    localStorage.setItem('cc_admin_token', 'tok-3');
    service.isLoggedIn.set(true);
    service.logout();
    http.expectOne(`${API_URL}/logout`).flush(null, { status: 204, statusText: 'No Content' });

    expect(service.getToken()).toBeNull();
    expect(service.isLoggedIn()).toBeFalse();
    expect(navegar).toHaveBeenCalledWith('/login');

    localStorage.setItem('cc_admin_token', 'tok-4');
    service.logout();
    http.expectOne(`${API_URL}/logout`).flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(service.getToken()).toBeNull();
    expect(navegar).toHaveBeenCalledTimes(2);
  });
});
