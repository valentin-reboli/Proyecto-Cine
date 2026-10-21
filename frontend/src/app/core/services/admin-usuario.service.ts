import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from './api.config';
import { AdminUsuario, AdminUsuarioForm, CambioPasswordForm, DosPasosQr } from '../models/admin-usuario.model';

@Injectable({ providedIn: 'root' })
export class AdminUsuarioService {
  private readonly http = inject(HttpClient);

  list(): Observable<AdminUsuario[]> {
    return this.http.get<AdminUsuario[]>(`${API_URL}/usuarios`);
  }

  create(data: AdminUsuarioForm): Observable<AdminUsuario> {
    return this.http.post<AdminUsuario>(`${API_URL}/usuarios`, data);
  }

  update(id: number, data: Pick<AdminUsuario, 'name' | 'email'>): Observable<AdminUsuario> {
    return this.http.put<AdminUsuario>(`${API_URL}/usuarios/${id}`, data);
  }

  cambiarPassword(id: number, data: CambioPasswordForm): Observable<void> {
    return this.http.put<void>(`${API_URL}/usuarios/${id}/password`, data);
  }

  iniciarDosPasos(id: number): Observable<DosPasosQr> {
    return this.http.post<DosPasosQr>(`${API_URL}/usuarios/${id}/dos-pasos`, {});
  }

  confirmarDosPasos(id: number, codigo: string): Observable<AdminUsuario> {
    return this.http.post<AdminUsuario>(`${API_URL}/usuarios/${id}/dos-pasos/confirmar`, { codigo });
  }

  desactivarDosPasos(id: number): Observable<AdminUsuario> {
    return this.http.delete<AdminUsuario>(`${API_URL}/usuarios/${id}/dos-pasos`);
  }
}
