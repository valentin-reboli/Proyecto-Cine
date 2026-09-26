import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { CatalogApiService } from './catalog-api.service';
import { API_URL } from './api.config';
import { ApiPelicula } from '../models/api-pelicula.model';
import { Movie } from '../models/movie.model';

const pelicula: ApiPelicula = {
  id: 7,
  titulo: 'Duro de Matar',
  sinopsis: null,
  duracion_min: 132,
  clasificacion: '+16',
  poster_url: '/posters/dm.jpg',
  backdrop_url: null,
  trailer_url: null,
  en_cartelera: true,
  fecha_estreno: null,
  activa: true,
  genero_id: 1,
  genero: { id: 1, nombre: 'Accion' },
  funciones: [
    {
      id: 11,
      pelicula_id: 7,
      sala_id: 1,
      formato_id: 1,
      fecha_hora: '2026-09-25 15:30:00',
      idioma: 'DOB',
      precio_base: '3500.00',
      cancelada: false,
      formato: { id: 1, nombre: '2D' },
    },
    {
      id: 12,
      pelicula_id: 7,
      sala_id: 1,
      formato_id: 2,
      fecha_hora: '2026-09-26 21:00:00',
      idioma: 'SUB',
      precio_base: 4200,
      cancelada: false,
      formato: { id: 2, nombre: '3D' },
    },
  ],
};

describe('CatalogApiService', () => {
  let service: CatalogApiService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(CatalogApiService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('trae la cartelera y conserva el id, fecha y precio de cada funcion', () => {
    let movies: Movie[] = [];
    service.getCartelera().subscribe((m) => (movies = m));

    http.expectOne(`${API_URL}/peliculas`).flush([pelicula]);

    expect(movies.length).toBe(1);
    expect(movies[0].title).toBe('Duro de Matar');
    expect(movies[0].id).toBe('7');
    expect(movies[0].genre).toBe('Accion');
    expect(movies[0].formats).toEqual(['2D', '3D']);
    expect(movies[0].todayTimes).toEqual([
      { time: '15:30', lang: 'DOB', funcionId: 11, date: '2026-09-25', price: 3500 },
      { time: '21:00', lang: 'SUB', funcionId: 12, date: '2026-09-26', price: 4200 },
    ]);
  });

  it('una pelicula sin funciones queda con la lista de horarios vacia', () => {
    let movies: Movie[] = [];
    service.getCartelera().subscribe((m) => (movies = m));

    http.expectOne(`${API_URL}/peliculas`).flush([{ ...pelicula, funciones: [] }]);

    expect(movies[0].todayTimes).toEqual([]);
    expect(movies[0].formats).toEqual(['2D']);
  });

  it('pide proximamente con el parametro y arma la fecha de estreno', () => {
    let proximas: { releaseDate: string; title: string }[] = [];
    service.getProximamente().subscribe((m) => (proximas = m));

    const req = http.expectOne((r) => r.url === `${API_URL}/peliculas`);
    expect(req.request.params.get('proximamente')).toBe('1');
    req.flush([{ ...pelicula, en_cartelera: false, fecha_estreno: '2026-12-01', funciones: [] }]);

    expect(proximas[0].title).toBe('Duro de Matar');
    expect(proximas[0].releaseDate).toBe('2026-12-01');
  });
});
