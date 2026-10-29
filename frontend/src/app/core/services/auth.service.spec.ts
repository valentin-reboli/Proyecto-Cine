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

describe('AuthService: cierre por inactividad', () => {
  const HORA = 60 * 60 * 1000;
  let navegar: jasmine.Spy;

  function crearServicio(): AuthService {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    navegar = spyOn(TestBed.inject(Router), 'navigate').and.resolveTo(true);
    return TestBed.inject(AuthService);
  }

  function iniciarSesion(service: AuthService): void {
    service.login('admin@cine.test', 'secreto123').subscribe();
    TestBed.inject(HttpTestingController)
      .expectOne(`${API_URL}/login`)
      .flush({ user: { id: 1, name: 'Admin', email: 'admin@cine.test' }, token: 'tok' });
  }

  beforeEach(() => {
    localStorage.clear();
    jasmine.clock().install();
    jasmine.clock().mockDate(new Date(2026, 9, 29, 10, 0, 0));
  });

  afterEach(() => {
    jasmine.clock().uninstall();
    localStorage.clear();
  });

  it('despues de 4 horas sin usar el panel cierra la sesion y avisa en el login', () => {
    const service = crearServicio();
    iniciarSesion(service);

    jasmine.clock().tick(4 * HORA - 60_000);
    expect(service.isLoggedIn()).toBeTrue();

    jasmine.clock().tick(2 * 60_000);
    expect(service.isLoggedIn()).toBeFalse();
    expect(service.getToken()).toBeNull();
    expect(navegar).toHaveBeenCalledWith(['/login'], { queryParams: { motivo: 'inactividad' } });
  });

  it('mientras se usa el panel la sesion no se corta', () => {
    const service = crearServicio();
    iniciarSesion(service);

    // Un clic cada 3 horas durante 12 horas.
    for (let i = 0; i < 4; i++) {
      jasmine.clock().tick(3 * HORA);
      document.dispatchEvent(new MouseEvent('click'));
    }
    expect(service.isLoggedIn()).toBeTrue();
    expect(navegar).not.toHaveBeenCalled();
    // Cada clic tambien renueva el token en el backend.
    expect(TestBed.inject(HttpTestingController).match(`${API_URL}/user`).length).toBe(4);
  });

  it('al volver a abrir la pagina despues de 4 horas la sesion ya no vale', () => {
    localStorage.setItem('cc_admin_token', 'tok-viejo');
    localStorage.setItem('cc_admin_ultima_actividad', String(Date.now() - 5 * HORA));

    const service = crearServicio();

    expect(service.isLoggedIn()).toBeFalse();
    expect(service.getToken()).toBeNull();
  });
});
