import { Component, HostListener, OnDestroy, computed, inject, signal } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { CatalogService } from '../../core/services/catalog.service';
import { BookingStateService } from '../../core/services/booking-state.service';
import { ComingSoonMovie, Movie, Showtime } from '../../core/models/movie.model';
import { Dia, agruparPorDia } from '../../core/utils/funciones';
import { RevealDirective } from '../../core/directives/reveal.directive';

@Component({
  selector: 'app-home',
  imports: [FormsModule, RevealDirective],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css',
})
export class HomeComponent implements OnDestroy {
  private readonly catalog = inject(CatalogService);
  private readonly bookingState = inject(BookingStateService);
  private readonly router = inject(Router);
  private readonly sanitizer = inject(DomSanitizer);

  readonly movies = this.catalog.movies;
  readonly comingSoon = this.catalog.comingSoon;
  readonly loading = this.catalog.loading;
  readonly error = this.catalog.error;
  // Portada: va rotando entre las peliculas en cartelera.
  readonly heroIndex = signal(0);
  readonly heroMovies = computed(() => this.movies().slice(0, 6));
  readonly heroMovie = computed(() => {
    const lista = this.heroMovies();
    return lista[this.heroIndex() % (lista.length || 1)] ?? null;
  });
  private heroTimer: ReturnType<typeof setInterval> | null = null;
  private heroPausado = false;

  // Ficha de la pelicula (modal con sinopsis, trailer y funciones).
  readonly detalle = signal<Movie | null>(null);
  readonly detalleDias = computed((): Dia[] => agruparPorDia(this.detalle()?.todayTimes ?? []));
  readonly trailerUrl = computed((): SafeResourceUrl | null => {
    const id = this.youtubeId(this.detalle()?.trailer);
    return id ? this.sanitizer.bypassSecurityTrustResourceUrl(`https://www.youtube.com/embed/${id}`) : null;
  });
  readonly trailerLink = computed(() => {
    const url = this.detalle()?.trailer;
    return url && /^https?:\/\//i.test(url) && !this.youtubeId(url) ? url : null;
  });

  constructor() {
    this.heroTimer = setInterval(() => {
      if (!this.heroPausado && !this.detalle() && this.heroMovies().length > 1) this.siguienteHero();
    }, 7000);
  }

  ngOnDestroy(): void {
    if (this.heroTimer) clearInterval(this.heroTimer);
  }

  siguienteHero(): void {
    this.heroIndex.update((i) => (i + 1) % (this.heroMovies().length || 1));
  }

  anteriorHero(): void {
    const n = this.heroMovies().length || 1;
    this.heroIndex.update((i) => (i - 1 + n) % n);
  }

  irAHero(i: number): void {
    this.heroIndex.set(i);
  }

  pausarHero(pausa: boolean): void {
    this.heroPausado = pausa;
  }
  readonly promotions = this.catalog.promotions;

  readonly searchQuery = signal('');
  readonly filterGenre = signal('Todos');
  readonly filterFormat = signal('Todos');

  readonly genreOptions = computed(() => [
    'Todos',
    ...new Set(this.movies().flatMap((m) => m.genres)),
  ]);
  readonly formatOptions = ['Todos', '2D', '3D', 'ATMOS'];

  readonly filteredMovies = computed(() => {
    const query = this.searchQuery().toLowerCase();
    const genre = this.filterGenre();
    const format = this.filterFormat();

    return this.movies().filter(
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

  langLabel(f: Showtime): string {
    return this.catalog.langLabel(f.lang);
  }

  formatPrice(amount: number): string {
    return this.catalog.formatPrice(amount);
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

  // Acepta youtube.com/watch?v=ID, youtu.be/ID y youtube.com/embed/ID.
  private youtubeId(url: string | undefined): string | null {
    const m = url?.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/);
    return m ? m[1] : null;
  }

  // Sin pelicula se arranca eligiendola; desde el detalle se salta a sus funciones (o a las butacas si se toco una funcion).
  comprar(movie?: Movie, funcion?: Showtime): void {
    this.bookingState.clear();
    const queryParams = movie ? { pelicula: movie.id, funcion: funcion?.funcionId ?? null } : {};
    this.router.navigate(['/reserva'], { queryParams });
  }

  verDetalle(movie: Movie): void {
    this.detalle.set(movie);
  }

  @HostListener('document:keydown.escape')
  cerrarDetalle(): void {
    this.detalle.set(null);
  }

  verProximamente(p: ComingSoonMovie): void {
    this.detalle.set({
      id: p.id,
      title: p.title,
      poster: p.poster,
      genre: p.genre,
      genres: [p.genre],
      duration: p.duration ?? 0,
      rating: p.rating ?? '',
      formats: [],
      sala: '',
      todayTimes: [],
      synopsis: p.synopsis,
      backdrop: p.backdrop,
      trailer: p.trailer,
      releaseDate: p.releaseDate,
    });
  }

  fechaLabel(iso: string | undefined): string {
    if (!iso) return '';
    const [y, m, d] = iso.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
  }

}
