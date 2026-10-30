import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { MercadoPagoService } from './mercado-pago.service';
import { API_URL } from './api.config';

describe('MercadoPagoService', () => {
  let service: MercadoPagoService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), MercadoPagoService],
    });
    service = TestBed.inject(MercadoPagoService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('pide la configuracion una sola vez', async () => {
    const primera = service.config();
    const segunda = service.config();

    http.expectOne(`${API_URL}/pagos/config`).flush({ modo_prueba: true });

    expect((await primera).modo_prueba).toBeTrue();
    expect(await segunda).toBe(await primera);
  });

  it('crea la preferencia de Checkout Pro y devuelve el link de pago', () => {
    let url = '';
    service.crearPreferencia(5, 'ana@test.com').subscribe((r) => (url = r.url));

    const req = http.expectOne(`${API_URL}/reservas/5/pago/preferencia`);
    expect(req.request.body).toEqual({ correo: 'ana@test.com' });
    req.flush({ id: 'pref-1', url: 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=pref-1' });

    expect(url).toContain('mercadopago.com.ar');
  });

  it('verifica el pago de Checkout Pro contra el backend', () => {
    let estado = '';
    service.verificar(5, 'ana@test.com', '1234').subscribe((r) => (estado = r.estado));

    const req = http.expectOne(`${API_URL}/reservas/5/pago/verificar`);
    expect(req.request.body).toEqual({ correo: 'ana@test.com', pago_id: '1234' });
    req.flush({ estado: 'aprobado' });

    expect(estado).toBe('aprobado');
  });
});
