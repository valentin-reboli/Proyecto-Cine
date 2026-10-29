import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, firstValueFrom } from 'rxjs';
import { API_URL } from './api.config';

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

@Injectable({ providedIn: 'root' })
export class MercadoPagoService {
  private readonly http = inject(HttpClient);
  private configuracion: Promise<ConfigPagos> | null = null;

  config(): Promise<ConfigPagos> {
    this.configuracion ??= firstValueFrom(this.http.get<ConfigPagos>(`${API_URL}/pagos/config`)).catch((error) => {
      this.configuracion = null;
      throw error;
    });
    return this.configuracion;
  }

  // Checkout Pro: link de Mercado Pago donde el comprador elige tarjeta, debito o dinero en cuenta.
  crearPreferencia(reservaId: number, correo: string): Observable<{ id: string; url: string }> {
    return this.http.post<{ id: string; url: string }>(`${API_URL}/reservas/${reservaId}/pago/preferencia`, { correo });
  }

  // Al volver de Checkout Pro el servidor consulta el pago a Mercado Pago.
  verificar(reservaId: number, correo: string, pagoId: string | null): Observable<RespuestaPago> {
    return this.http.post<RespuestaPago>(`${API_URL}/reservas/${reservaId}/pago/verificar`, { correo, pago_id: pagoId });
  }
}
