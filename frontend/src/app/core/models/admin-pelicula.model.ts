// Forma real que expone el backend (Laravel), distinta del Movie de mockup
// que usa la home (features/home). Se usa solo en el panel de admin.
export interface Genero {
  id: number;
  nombre: string;
}

export interface AdminPelicula {
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
  genero?: Genero;
}

export type AdminPeliculaForm = Omit<AdminPelicula, 'id' | 'activa' | 'genero'>;
