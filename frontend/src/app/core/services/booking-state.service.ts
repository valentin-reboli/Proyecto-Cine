import { Injectable, signal } from '@angular/core';
import { Movie, Showtime } from '../models/movie.model';

export interface BookingSelection {
  movie: Movie;
  // Se elige en la pantalla de reserva, entre las funciones programadas de la pelicula.
  showtime: Showtime | null;
}

@Injectable({ providedIn: 'root' })
export class BookingStateService {
  private readonly _selection = signal<BookingSelection | null>(null);
  readonly selection = this._selection.asReadonly();

  select(movie: Movie, showtime: Showtime | null = null): void {
    this._selection.set({ movie, showtime });
  }

  clear(): void {
    this._selection.set(null);
  }
}
