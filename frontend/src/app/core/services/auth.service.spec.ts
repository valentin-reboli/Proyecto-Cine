import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { AuthService } from './auth.service';
import { API_URL } from './api.config';

describe('AuthService', () => {
  let service: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
  });

  it('si el usuario tiene Google Authenticator no guarda sesion hasta mandar el codigo', () => {
    service.login('jacobo@cineconcordia.com', 'secreto123').subscribe((res) => {
      expect(res.requiere_codigo).toBeTrue();
    });
    http.expectOne(`${API_URL}/login`).flush({ requiere_codigo: true });

    expect(service.isLoggedIn()).toBeFalse();
    expect(service.getToken()).toBeNull();

    service.login('jacobo@cineconcordia.com', 'secreto123', '123456').subscribe();
    const req = http.expectOne(`${API_URL}/login`);
    expect(req.request.body).toEqual({ email: 'jacobo@cineconcordia.com', password: 'secreto123', codigo: '123456' });
    req.flush({ user: { id: 1, name: 'Jacobo', email: 'jacobo@cineconcordia.com' }, token: 'tok-1' });

    expect(service.isLoggedIn()).toBeTrue();
    expect(service.getToken()).toBe('tok-1');
  });

  it('el login con Google manda el credential y el codigo', () => {
    service.loginWithGoogle('jwt-de-google', '654321').subscribe();
    const req = http.expectOne(`${API_URL}/login/google`);
    expect(req.request.body).toEqual({ credential: 'jwt-de-google', codigo: '654321' });
    req.flush({ user: { id: 1, name: 'Jacobo', email: 'jacobo@cineconcordia.com' }, token: 'tok-2' });

    expect(service.getToken()).toBe('tok-2');
  });
});
