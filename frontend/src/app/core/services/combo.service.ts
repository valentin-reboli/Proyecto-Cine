import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from './api.config';
import { Combo, ComboPayload } from '../models/combo.model';

@Injectable({ providedIn: 'root' })
export class ComboService {
  private readonly http = inject(HttpClient);

  // Publico: solo los activos (los que se ofrecen al comprar).
  listActivos(): Observable<Combo[]> {
    return this.http.get<Combo[]>(`${API_URL}/combos`);
  }
  // Admin: todos.
  listTodos(): Observable<Combo[]> {
    return this.http.get<Combo[]>(`${API_URL}/admin/combos`);
  }
  create(data: ComboPayload): Observable<Combo> {
    return this.http.post<Combo>(`${API_URL}/combos`, data);
  }
  update(id: number, data: ComboPayload): Observable<Combo> {
    return this.http.put<Combo>(`${API_URL}/combos/${id}`, data);
  }
  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/combos/${id}`);
  }
}
