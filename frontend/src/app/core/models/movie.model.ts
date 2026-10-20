export type Lang = 'DOB' | 'SUB';

export interface Showtime {
  time: string;
  lang: Lang;
  // Datos de la funcion real del backend. Opcionales porque los datos de
  // ejemplo (catalog.data.ts) no los tienen; el catalogo real siempre los trae.
  funcionId?: number;
  date?: string;
  price?: number;
  format?: string;
  sala?: string;
}

export interface Movie {
  id: string;
  title: string;
  poster: string;
  genre: string;
  genres: string[];
  duration: number;
  rating: string;
  formats: string[];
  sala: string;
  todayTimes: Showtime[];
  synopsis?: string;
  backdrop?: string;
  trailer?: string;
}

export interface ComingSoonMovie {
  id: string;
  title: string;
  poster: string;
  genre: string;
  releaseDate: string;
}

export interface Promotion {
  tag: string;
  title: string;
  description: string;
}
