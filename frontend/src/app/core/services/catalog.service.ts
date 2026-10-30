import { Injectable, inject, signal } from '@angular/core';
import { CatalogApiService } from './catalog-api.service';
import { LANG_LABELS, PROMOTIONS } from '../data/catalog.data';
import { ComingSoonMovie, Lang, Movie, Promotion } from '../models/movie.model';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly api = inject(CatalogApiService);

  // Cartelera y proximos estrenos vienen de la API (los mismos datos que edita el panel de admin).
  readonly movies = signal<Movie[]>([]);
  readonly comingSoon = signal<ComingSoonMovie[]>([]);
  readonly loading = signal(true);
  readonly error = signal(false);
  readonly promotions: Promotion[] = PROMOTIONS;

  constructor() {
    this.load();
  }

  load(): void {
    this.loading.set(true);
    this.error.set(false);
    this.api.getCartelera().subscribe({
      next: (movies) => {
        this.movies.set(movies);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
    this.api.getProximamente().subscribe({
      next: (movies) => this.comingSoon.set(movies),
      error: () => this.error.set(true),
    });
  }

  langLabel(lang: Lang): string {
    return LANG_LABELS[lang];
  }

  formatPrice(amount: number): string {
    return '$' + amount.toLocaleString('es-AR');
  }
}
