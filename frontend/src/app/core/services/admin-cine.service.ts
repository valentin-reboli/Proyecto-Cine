import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from './api.config';
import { AdminFuncion, Butaca, Formato, FuncionPayload, Sala, SalaPayload } from '../models/admin-cine.model';

// Funciones, salas, butacas y formatos del cine (todo requiere login salvo los listados).
@Injectable({ providedIn: 'root' })
export class AdminCineService {
  private readonly http = inject(HttpClient);

  listFunciones(): Observable<AdminFuncion[]> {
    return this.http.get<AdminFuncion[]>(`${API_URL}/admin/funciones`);
  }
  createFuncion(data: FuncionPayload): Observable<AdminFuncion> {
    return this.http.post<AdminFuncion>(`${API_URL}/funciones`, data);
  }
  updateFuncion(id: number, data: FuncionPayload): Observable<AdminFuncion> {
    return this.http.put<AdminFuncion>(`${API_URL}/funciones/${id}`, data);
  }
  cancelarFuncion(id: number): Observable<AdminFuncion> {
    return this.http.post<AdminFuncion>(`${API_URL}/funciones/${id}/cancelar`, {});
  }

  listSalas(): Observable<Sala[]> {
    return this.http.get<Sala[]>(`${API_URL}/salas`);
  }
  createSala(data: SalaPayload): Observable<Sala> {
    return this.http.post<Sala>(`${API_URL}/salas`, data);
  }
  updateSala(id: number, nombre: string): Observable<Sala> {
    return this.http.put<Sala>(`${API_URL}/salas/${id}`, { nombre });
  }
  deleteSala(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/salas/${id}`);
  }
  listButacas(salaId: number): Observable<Butaca[]> {
    return this.http.get<Butaca[]>(`${API_URL}/salas/${salaId}/asientos`);
  }
  setButacaActiva(id: number, activo: boolean): Observable<Butaca> {
    return this.http.put<Butaca>(`${API_URL}/asientos/${id}`, { activo });
  }

  listFormatos(): Observable<Formato[]> {
    return this.http.get<Formato[]>(`${API_URL}/formatos`);
  }
  createFormato(nombre: string): Observable<Formato> {
    return this.http.post<Formato>(`${API_URL}/formatos`, { nombre });
  }
  updateFormato(id: number, nombre: string): Observable<Formato> {
    return this.http.put<Formato>(`${API_URL}/formatos/${id}`, { nombre });
  }
  deleteFormato(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/formatos/${id}`);
  }
}
