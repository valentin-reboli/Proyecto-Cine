import { Component, computed, inject, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CatalogService } from '../../core/services/catalog.service';
import { Movie, Showtime } from '../../core/models/movie.model';

@Component({
  selector: 'app-home',
  imports: [FormsModule],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css',
})
export class HomeComponent {
  private readonly catalog = inject(CatalogService);

  readonly selectMovie = output<{ movie: Movie; showtime: Showtime }>();

  readonly heroMovie = this.catalog.movies[0];

  readonly movies = this.catalog.movies;
  readonly comingSoon = this.catalog.comingSoon;
  readonly promotions = this.catalog.promotions;

  readonly searchQuery = signal('');
  readonly filterGenre = signal('Todos');
  readonly filterFormat = signal('Todos');

  readonly genreOptions = computed(() => [
    'Todos',
    ...new Set(this.movies.flatMap((m) => m.genres)),
  ]);
  readonly formatOptions = ['Todos', '2D', '3D', 'ATMOS'];

  readonly filteredMovies = computed(() => {
    const query = this.searchQuery().toLowerCase();
    const genre = this.filterGenre();
    const format = this.filterFormat();

    return this.movies.filter(
      (m) =>
        (genre === 'Todos' || m.genres.includes(genre)) &&
        (format === 'Todos' || m.formats.includes(format)) &&
        (query === '' || m.title.toLowerCase().includes(query)),
    );
  });

  langBadgesFor(movie: Movie): string[] {
    const langs = new Set(movie.todayTimes.map((t) => t.lang));
    return [...langs].map((l) => this.catalog.langLabel(l));
  }

  onSearchInput(value: string): void {
    this.searchQuery.set(value);
  }

  onGenreChange(value: string): void {
    this.filterGenre.set(value);
  }

  onFormatChange(value: string): void {
    this.filterFormat.set(value);
  }

  openMovie(movie: Movie): void {
    this.selectMovie.emit({ movie, showtime: movie.todayTimes[0] });
  }

  openHeroMovie(): void {
    this.openMovie(this.heroMovie);
  }
}
