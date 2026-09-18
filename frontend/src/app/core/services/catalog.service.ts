import { Injectable } from '@angular/core';
import {
  COMING_SOON,
  LANG_LABELS,
  MOVIES,
  PRICE_GENERAL,
  PRICE_REDUCIDA,
  PROMOTIONS,
} from '../data/catalog.data';
import { ComingSoonMovie, Lang, Movie, Promotion } from '../models/movie.model';

@Injectable({ providedIn: 'root' })
export class CatalogService {
  readonly movies: Movie[] = MOVIES;
  readonly comingSoon: ComingSoonMovie[] = COMING_SOON;
  readonly promotions: Promotion[] = PROMOTIONS;

  readonly priceGeneral = PRICE_GENERAL;
  readonly priceReducida = PRICE_REDUCIDA;

  getMovieById(id: string): Movie | undefined {
    return this.movies.find((m) => m.id === id);
  }

  langLabel(lang: Lang): string {
    return LANG_LABELS[lang];
  }

  formatPrice(amount: number): string {
    return '$' + amount.toLocaleString('es-AR');
  }
}
