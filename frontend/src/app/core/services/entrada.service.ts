import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from './api.config';
import { Entrada } from '../models/entrada.model';

// Control de acceso: el admin escanea el QR del comprobante desde el panel.
@Injectable({ providedIn: 'root' })
export class EntradaService {
  private readonly http = inject(HttpClient);

  // Trae los datos de la entrada sin marcarla.
  consultar(codigo_qr: string): Observable<Entrada> {
    return this.http.post<Entrada>(`${API_URL}/entradas/consultar`, { codigo_qr });
  }

  // Marca la entrada como usada (asistio).
  confirmarAsistencia(codigo_qr: string): Observable<Entrada> {
    return this.http.post<Entrada>(`${API_URL}/entradas/validar`, { codigo_qr });
  }
}
