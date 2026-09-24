import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, forkJoin, map } from 'rxjs';
import { API_URL } from './api.config';
import { AdminPelicula, AdminPeliculaForm, Genero } from '../models/admin-pelicula.model';

export interface CatalogoAdmin {
  enCartelera: AdminPelicula[];
  proximamente: AdminPelicula[];
}

@Injectable({ providedIn: 'root' })
export class AdminPeliculaService {
  private readonly http = inject(HttpClient);

  listCatalogo(): Observable<CatalogoAdmin> {
    return forkJoin([
      this.http.get<AdminPelicula[]>(`${API_URL}/peliculas`),
      this.http.get<AdminPelicula[]>(`${API_URL}/peliculas`, { params: { proximamente: 1 } }),
    ]).pipe(map(([enCartelera, proximamente]) => ({ enCartelera, proximamente })));
  }

  listInactivas(): Observable<AdminPelicula[]> {
    return this.http.get<AdminPelicula[]>(`${API_URL}/peliculas/inactivas`);
  }

  create(data: AdminPeliculaForm): Observable<AdminPelicula> {
    return this.http.post<AdminPelicula>(`${API_URL}/peliculas`, data);
  }

  update(id: number, data: AdminPeliculaForm): Observable<AdminPelicula> {
    return this.http.put<AdminPelicula>(`${API_URL}/peliculas/${id}`, data);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/peliculas/${id}`);
  }

  restaurar(id: number): Observable<AdminPelicula> {
    return this.http.post<AdminPelicula>(`${API_URL}/peliculas/${id}/restaurar`, {});
  }

  getGeneros(): Observable<Genero[]> {
    return this.http.get<Genero[]>(`${API_URL}/generos`);
  }
}
