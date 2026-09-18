import { Injectable, signal } from '@angular/core';
import { Movie, Showtime } from '../models/movie.model';

export interface BookingSelection {
  movie: Movie;
  showtime: Showtime;
}

@Injectable({ providedIn: 'root' })
export class BookingStateService {
  private readonly _selection = signal<BookingSelection | null>(null);
  readonly selection = this._selection.asReadonly();

  select(movie: Movie, showtime: Showtime): void {
    this._selection.set({ movie, showtime });
  }

  clear(): void {
    this._selection.set(null);
  }
}
