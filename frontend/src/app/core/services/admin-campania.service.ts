import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from './api.config';
import { Campania, CampaniaPayload, Cliente } from '../models/admin-campania.model';

@Injectable({ providedIn: 'root' })
export class AdminCampaniaService {
  private readonly http = inject(HttpClient);

  listClientes(): Observable<Cliente[]> {
    return this.http.get<Cliente[]>(`${API_URL}/clientes`);
  }
  list(): Observable<Campania[]> {
    return this.http.get<Campania[]>(`${API_URL}/campanias`);
  }
  create(data: CampaniaPayload): Observable<Campania> {
    return this.http.post<Campania>(`${API_URL}/campanias`, data);
  }
  update(id: number, data: CampaniaPayload): Observable<Campania> {
    return this.http.put<Campania>(`${API_URL}/campanias/${id}`, data);
  }
  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/campanias/${id}`);
  }
  enviar(id: number): Observable<Campania> {
    return this.http.post<Campania>(`${API_URL}/campanias/${id}/enviar`, {});
  }
}
