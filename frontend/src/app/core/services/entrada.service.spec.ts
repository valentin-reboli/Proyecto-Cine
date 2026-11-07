import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { EntradaService } from './entrada.service';
import { API_URL } from './api.config';

describe('EntradaService', () => {
  let service: EntradaService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(EntradaService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('consulta la entrada por su codigo QR sin marcarla', () => {
    service.consultar('abc-123').subscribe();

    const req = http.expectOne(`${API_URL}/entradas/consultar`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ codigo_qr: 'abc-123' });
    req.flush({});
  });

  it('confirma la asistencia con el endpoint de validar', () => {
    service.confirmarAsistencia('abc-123').subscribe();

    const req = http.expectOne(`${API_URL}/entradas/validar`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ codigo_qr: 'abc-123' });
    req.flush({});
  });
});
