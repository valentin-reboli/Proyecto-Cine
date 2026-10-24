import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, firstValueFrom } from 'rxjs';
import { API_URL } from './api.config';

// Lo que devuelve el Card Payment Brick al enviar el formulario (la tarjeta ya tokenizada).
export interface DatosTarjeta {
  token: string;
  payment_method_id: string;
  installments: number;
  issuer_id?: string;
  payer?: { email?: string; identification?: { type: string; number: string } };
}

// Estado del pago segun Mercado Pago (aprobado, pendiente, rechazado o reembolsado).
export interface RespuestaPago {
  estado: string;
  message?: string;
}

export interface ConfigPagos {
  habilitado: boolean;
  public_key: string | null;
  modo_prueba: boolean;
}

// SDK oficial de Mercado Pago para el navegador (https://sdk.mercadopago.com/js/v2).
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type MercadoPagoSdk = any;
export interface BrickController {
  unmount(): void;
}

declare global {
  interface Window {
    MercadoPago?: new (publicKey: string, options?: { locale: string }) => MercadoPagoSdk;
  }
}

const SDK_URL = 'https://sdk.mercadopago.com/js/v2';

@Injectable({ providedIn: 'root' })
export class MercadoPagoService {
  private readonly http = inject(HttpClient);
  private sdk: Promise<MercadoPagoSdk> | null = null;
  private configuracion: Promise<ConfigPagos> | null = null;

  config(): Promise<ConfigPagos> {
    this.configuracion ??= firstValueFrom(this.http.get<ConfigPagos>(`${API_URL}/pagos/config`)).catch((error) => {
      this.configuracion = null;
      throw error;
    });
    return this.configuracion;
  }

  // Formulario de tarjeta de Mercado Pago (Card Payment Brick). Los datos de la tarjeta
  // nunca pasan por nuestro servidor: Mercado Pago los convierte en un token.
  async montarFormularioTarjeta(
    contenedorId: string,
    monto: number,
    correo: string,
    alEnviar: (datos: DatosTarjeta) => Promise<void>,
  ): Promise<BrickController> {
    const mp = await this.cargarSdk();
    return mp.bricks().create('cardPayment', contenedorId, {
      initialization: { amount: monto, payer: { email: correo } },
      customization: {
        visual: { style: { theme: 'dark' } },
        paymentMethods: { maxInstallments: 1 },
      },
      callbacks: {
        onReady: () => {},
        onSubmit: (datos: DatosTarjeta) => alEnviar(datos),
        onError: (error: unknown) => console.error('Mercado Pago:', error),
      },
    });
  }

  pagarConTarjeta(reservaId: number, correo: string, datos: DatosTarjeta): Observable<RespuestaPago> {
    return this.http.post<RespuestaPago>(`${API_URL}/reservas/${reservaId}/pago`, { ...datos, correo });
  }

  // Checkout Pro: link de Mercado Pago para pagar con la cuenta (dinero en cuenta, tarjetas guardadas).
  crearPreferencia(reservaId: number, correo: string): Observable<{ id: string; url: string }> {
    return this.http.post<{ id: string; url: string }>(`${API_URL}/reservas/${reservaId}/pago/preferencia`, { correo });
  }

  // Al volver de Checkout Pro el servidor consulta el pago a Mercado Pago.
  verificar(reservaId: number, correo: string, pagoId: string | null): Observable<RespuestaPago> {
    return this.http.post<RespuestaPago>(`${API_URL}/reservas/${reservaId}/pago/verificar`, { correo, pago_id: pagoId });
  }

  private cargarSdk(): Promise<MercadoPagoSdk> {
    this.sdk ??= (async () => {
      const config = await this.config();
      if (!config.habilitado || !config.public_key) {
        throw new Error('Los pagos con Mercado Pago no están configurados.');
      }
      await this.cargarScript();
      return new window.MercadoPago!(config.public_key, { locale: 'es-AR' });
    })().catch((error) => {
      this.sdk = null;
      throw error;
    });
    return this.sdk;
  }

  private cargarScript(): Promise<void> {
    if (window.MercadoPago) return Promise.resolve();
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = SDK_URL;
      script.onload = () => resolve();
      script.onerror = () => {
        script.remove();
        reject(new Error('No se pudo cargar Mercado Pago.'));
      };
      document.head.appendChild(script);
    });
  }
}
