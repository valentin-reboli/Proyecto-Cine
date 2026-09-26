// Forma real que devuelve el backend (Laravel) para peliculas y funciones.
// Es distinta del modelo Movie que usa el HTML de la home: catalog-api.service.ts
// se encarga de traducir entre las dos.
export interface ApiGenero {
  id: number;
  nombre: string;
}

export interface ApiSala {
  id: number;
  nombre: string;
}

export interface ApiFormato {
  id: number;
  nombre: string;
}

export interface ApiFuncion {
  id: number;
  pelicula_id: number;
  sala_id: number;
  formato_id: number;
  fecha_hora: string;
  idioma: 'DOB' | 'SUB';
  precio_base: number | string;
  cancelada: boolean;
  sala?: ApiSala;
  formato?: ApiFormato;
}

export interface ApiPelicula {
  id: number;
  titulo: string;
  sinopsis: string | null;
  duracion_min: number;
  clasificacion: string | null;
  poster_url: string | null;
  backdrop_url: string | null;
  trailer_url: string | null;
  en_cartelera: boolean;
  fecha_estreno: string | null;
  activa: boolean;
  genero_id: number;
  genero?: ApiGenero;
  funciones?: ApiFuncion[];
}
