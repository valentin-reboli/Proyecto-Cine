import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { API_URL } from './api.config';
import { ApiFuncion, ApiPelicula } from '../models/api-pelicula.model';
import { ComingSoonMovie, Movie, Showtime } from '../models/movie.model';

// Traduce la forma real del backend (ApiPelicula) a la forma Movie /
// ComingSoonMovie que ya usa el HTML de la home, asi no hace falta reescribir
// el template para conectar los datos reales.
function mapToShowtime(f: ApiFuncion): Showtime {
  return {
    time: f.fecha_hora.slice(11, 16),
    lang: f.idioma,
    funcionId: f.id,
    date: f.fecha_hora.slice(0, 10),
    price: Number(f.precio_base),
  };
}

export function mapToMovie(p: ApiPelicula): Movie {
  const funciones = p.funciones ?? [];
  const formats = [
    ...new Set(
      funciones.map((f) => f.formato?.nombre).filter((nombre): nombre is string => !!nombre),
    ),
  ];
  const genreName = p.genero?.nombre ?? 'Sin género';

  return {
    id: String(p.id),
    title: p.titulo,
    poster: p.poster_url ?? '',
    genre: genreName,
    genres: [genreName],
    duration: p.duracion_min,
    rating: p.clasificacion ?? 'ATP',
    formats: formats.length ? formats : ['2D'],
    sala: funciones[0]?.sala?.nombre ?? '',
    todayTimes: funciones.map(mapToShowtime),
  };
}

export function mapToComingSoonMovie(p: ApiPelicula): ComingSoonMovie {
  return {
    id: String(p.id),
    title: p.titulo,
    poster: p.poster_url ?? '',
    genre: p.genero?.nombre ?? 'Sin género',
    releaseDate: p.fecha_estreno ?? '',
  };
}

@Injectable({ providedIn: 'root' })
export class CatalogApiService {
  private readonly http = inject(HttpClient);

  getCartelera(): Observable<Movie[]> {
    return this.http
      .get<ApiPelicula[]>(`${API_URL}/peliculas`)
      .pipe(map((peliculas) => peliculas.map(mapToMovie)));
  }

  getProximamente(): Observable<ComingSoonMovie[]> {
    return this.http
      .get<ApiPelicula[]>(`${API_URL}/peliculas`, { params: { proximamente: 1 } })
      .pipe(map((peliculas) => peliculas.map(mapToComingSoonMovie)));
  }
}
