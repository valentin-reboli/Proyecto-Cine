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

    http.expectOne(`${API_URL}/pagos/config`).flush({ habilitado: true, public_key: 'TEST-123', modo_prueba: true });

    expect((await primera).public_key).toBe('TEST-123');
    expect((await segunda).modo_prueba).toBeTrue();
  });

  it('manda el token de la tarjeta y el correo al backend, nunca los datos de la tarjeta', () => {
    service
      .pagarConTarjeta(5, 'ana@test.com', { token: 'tok-1', payment_method_id: 'visa', installments: 1 })
      .subscribe();

    const req = http.expectOne(`${API_URL}/reservas/5/pago`);
    expect(req.request.body).toEqual({
      token: 'tok-1',
      payment_method_id: 'visa',
      installments: 1,
      correo: 'ana@test.com',
    });
    req.flush({ estado: 'aprobado' });
  });

  it('verifica el pago de Checkout Pro contra el backend', () => {
    let estado = '';
    service.verificar(5, 'ana@test.com', '1234').subscribe((r) => (estado = r.estado));

    const req = http.expectOne(`${API_URL}/reservas/5/pago/verificar`);
    expect(req.request.body).toEqual({ correo: 'ana@test.com', pago_id: '1234' });
    req.flush({ estado: 'aprobado' });

    expect(estado).toBe('aprobado');
  });

  it('avisa si Mercado Pago no esta configurado', async () => {
    const promesa = service.montarFormularioTarjeta('mp-card-form', 1000, 'ana@test.com', async () => {});
    http.expectOne(`${API_URL}/pagos/config`).flush({ habilitado: false, public_key: null, modo_prueba: true });

    await expectAsync(promesa).toBeRejectedWithError('Los pagos con Mercado Pago no están configurados.');
  });
});
