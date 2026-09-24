import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AdminPeliculaService } from '../../core/services/admin-pelicula.service';
import { AuthService } from '../../core/services/auth.service';
import { AdminPelicula, AdminPeliculaForm, Genero } from '../../core/models/admin-pelicula.model';

export const CLASIFICACIONES = ['ATP', '+13', '+16', '+18'];

const FORM_VACIO: AdminPeliculaForm = {
  titulo: '',
  sinopsis: '',
  duracion_min: 90,
  clasificacion: 'ATP',
  poster_url: '',
  backdrop_url: '',
  trailer_url: '',
  en_cartelera: true,
  fecha_estreno: null,
  genero_id: 0,
};

type Seccion = 'cartelera' | 'proximamente';

@Component({
  selector: 'app-admin-dashboard',
  imports: [FormsModule, RouterLink],
  templateUrl: './admin-dashboard.component.html',
  styleUrl: './admin-dashboard.component.css',
})
export class AdminDashboardComponent implements OnInit {
  private readonly peliculaService = inject(AdminPeliculaService);
  private readonly auth = inject(AuthService);

  readonly clasificaciones = CLASIFICACIONES;

  // Datos
  readonly enCartelera = signal<AdminPelicula[]>([]);
  readonly proximamente = signal<AdminPelicula[]>([]);
  readonly inactivas = signal<AdminPelicula[]>([]);
  readonly generos = signal<Genero[]>([]);

  // Estado de carga / feedback
  readonly cargando = signal(true);
  readonly error = signal('');
  readonly aviso = signal('');
  readonly filaProcesando = signal<number | null>(null);

  // Búsqueda y filtro (aplican a ambas listas)
  busqueda = '';
  generoFiltroId = 0;
  readonly mostrarPapelera = signal(false);

  enCarteleraFiltrada(): AdminPelicula[] {
    return this.filtrar(this.enCartelera());
  }

  proximamenteFiltrada(): AdminPelicula[] {
    return this.filtrar(this.proximamente());
  }

  // Formulario modal
  readonly mostrarForm = signal(false);
  readonly editandoId = signal<number | null>(null);
  readonly guardando = signal(false);
  readonly erroresForm = signal<Record<string, string>>({});
  form: AdminPeliculaForm = { ...FORM_VACIO };

  ngOnInit(): void {
    this.peliculaService.getGeneros().subscribe({
      next: (generos) => this.generos.set(generos),
      error: () => this.error.set('No se pudieron cargar los géneros.'),
    });

    this.cargarCatalogo();
  }

  private filtrar(lista: AdminPelicula[]): AdminPelicula[] {
    const texto = this.busqueda.trim().toLowerCase();

    return lista.filter((p) => {
      const coincideTexto = texto === '' || p.titulo.toLowerCase().includes(texto);
      const coincideGenero = this.generoFiltroId === 0 || p.genero_id === this.generoFiltroId;

      return coincideTexto && coincideGenero;
    });
  }

  cargarCatalogo(): void {
    this.cargando.set(true);
    this.error.set('');

    this.peliculaService.listCatalogo().subscribe({
      next: (catalogo) => {
        this.enCartelera.set(catalogo.enCartelera);
        this.proximamente.set(catalogo.proximamente);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudo cargar la cartelera. ¿Está corriendo el backend?');
        this.cargando.set(false);
      },
    });
  }

  toggleLimpiarFiltros(): void {
    this.busqueda = '';
    this.generoFiltroId = 0;
  }

  togglePapelera(): void {
    const abrir = !this.mostrarPapelera();
    this.mostrarPapelera.set(abrir);

    if (abrir && this.inactivas().length === 0) {
      this.peliculaService.listInactivas().subscribe({
        next: (lista) => this.inactivas.set(lista),
        error: () => this.error.set('No se pudo cargar la papelera.'),
      });
    }
  }

  // --- Alta / edición ---

  abrirNueva(seccion: Seccion): void {
    this.editandoId.set(null);
    this.erroresForm.set({});
    this.form = {
      ...FORM_VACIO,
      genero_id: this.generos()[0]?.id ?? 0,
      en_cartelera: seccion === 'cartelera',
    };
    this.mostrarForm.set(true);
  }

  abrirEdicion(pelicula: AdminPelicula): void {
    this.editandoId.set(pelicula.id);
    this.erroresForm.set({});
    this.form = {
      titulo: pelicula.titulo,
      sinopsis: pelicula.sinopsis ?? '',
      duracion_min: pelicula.duracion_min,
      clasificacion: pelicula.clasificacion ?? '',
      poster_url: pelicula.poster_url ?? '',
      backdrop_url: pelicula.backdrop_url ?? '',
      trailer_url: pelicula.trailer_url ?? '',
      en_cartelera: pelicula.en_cartelera,
      fecha_estreno: pelicula.fecha_estreno,
      genero_id: pelicula.genero_id,
    };
    this.mostrarForm.set(true);
  }

  cerrarForm(): void {
    if (this.guardando()) {
      return;
    }

    this.mostrarForm.set(false);
    this.editandoId.set(null);
  }

  private validar(): boolean {
    const errores: Record<string, string> = {};

    if (!this.form.titulo.trim()) {
      errores['titulo'] = 'El título es obligatorio.';
    }
    if (!this.form.duracion_min || this.form.duracion_min < 1) {
      errores['duracion_min'] = 'La duración tiene que ser mayor a 0.';
    }
    if (!this.form.genero_id) {
      errores['genero_id'] = 'Elegí un género.';
    }
    if (!this.form.en_cartelera && !this.form.fecha_estreno) {
      errores['fecha_estreno'] = 'Las películas de "Próximamente" necesitan fecha de estreno.';
    }

    this.erroresForm.set(errores);

    return Object.keys(errores).length === 0;
  }

  guardar(): void {
    if (!this.validar()) {
      return;
    }

    this.guardando.set(true);
    this.error.set('');

    const id = this.editandoId();
    const payload: AdminPeliculaForm = {
      ...this.form,
      sinopsis: this.form.sinopsis || null,
      clasificacion: this.form.clasificacion || null,
      poster_url: this.form.poster_url || null,
      backdrop_url: this.form.backdrop_url || null,
      trailer_url: this.form.trailer_url || null,
      fecha_estreno: this.form.en_cartelera ? null : this.form.fecha_estreno || null,
    };

    const request = id ? this.peliculaService.update(id, payload) : this.peliculaService.create(payload);

    request.subscribe({
      next: () => {
        this.guardando.set(false);
        this.mostrarForm.set(false);
        this.editandoId.set(null);
        this.mostrarAviso(id ? 'Película actualizada.' : 'Película creada.');
        this.cargarCatalogo();
      },
      error: (err) => {
        this.guardando.set(false);
        this.error.set(err?.error?.message ?? 'No se pudo guardar la película.');
      },
    });
  }

  // --- Acciones rápidas (sin abrir el formulario) ---

  publicarAhora(pelicula: AdminPelicula): void {
    this.filaProcesando.set(pelicula.id);

    const payload: AdminPeliculaForm = {
      titulo: pelicula.titulo,
      sinopsis: pelicula.sinopsis,
      duracion_min: pelicula.duracion_min,
      clasificacion: pelicula.clasificacion,
      poster_url: pelicula.poster_url,
      backdrop_url: pelicula.backdrop_url,
      trailer_url: pelicula.trailer_url,
      en_cartelera: true,
      fecha_estreno: null,
      genero_id: pelicula.genero_id,
    };

    this.peliculaService.update(pelicula.id, payload).subscribe({
      next: () => {
        this.filaProcesando.set(null);
        this.mostrarAviso(`"${pelicula.titulo}" ya está en cartelera.`);
        this.cargarCatalogo();
      },
      error: () => {
        this.filaProcesando.set(null);
        this.error.set('No se pudo mover la película a cartelera.');
      },
    });
  }

  eliminar(pelicula: AdminPelicula): void {
    if (!confirm(`¿Dar de baja "${pelicula.titulo}"? Vas a poder restaurarla después desde la papelera.`)) {
      return;
    }

    this.filaProcesando.set(pelicula.id);

    this.peliculaService.delete(pelicula.id).subscribe({
      next: () => {
        this.filaProcesando.set(null);
        this.mostrarAviso(`"${pelicula.titulo}" se dio de baja.`);
        this.cargarCatalogo();
        this.inactivas.set([]);
      },
      error: () => {
        this.filaProcesando.set(null);
        this.error.set('No se pudo dar de baja la película.');
      },
    });
  }

  restaurar(pelicula: AdminPelicula): void {
    this.filaProcesando.set(pelicula.id);

    this.peliculaService.restaurar(pelicula.id).subscribe({
      next: () => {
        this.filaProcesando.set(null);
        this.mostrarAviso(`"${pelicula.titulo}" fue restaurada.`);
        this.inactivas.update((lista) => lista.filter((p) => p.id !== pelicula.id));
        this.cargarCatalogo();
      },
      error: () => {
        this.filaProcesando.set(null);
        this.error.set('No se pudo restaurar la película.');
      },
    });
  }

  private mostrarAviso(mensaje: string): void {
    this.aviso.set(mensaje);
    setTimeout(() => this.aviso.set(''), 3500);
  }

  cerrarSesion(): void {
    this.auth.logout();
  }
}
