import { AdminPelicula } from './admin-pelicula.model';

export interface Sala {
  id: number;
  nombre: string;
  asientos_count?: number;
}

export interface Formato {
  id: number;
  nombre: string;
}

export interface Butaca {
  id: number;
  fila: string;
  numero: number;
  activo: boolean;
}

export type Idioma = 'DOB' | 'SUB';

export interface AdminFuncion {
  id: number;
  pelicula_id: number;
  sala_id: number;
  formato_id: number;
  fecha_hora: string;
  idioma: Idioma;
  precio_base: string;
  cancelada: boolean;
  pelicula?: AdminPelicula;
  sala?: Sala;
  formato?: Formato;
}

export interface FuncionPayload {
  pelicula_id: number;
  sala_id: number;
  formato_id: number;
  fecha_hora: string;
  idioma: Idioma;
  precio_base: number;
}

export interface SalaPayload {
  nombre: string;
  filas: number;
  butacas_por_fila: number;
}
