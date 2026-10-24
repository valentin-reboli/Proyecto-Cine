import { Component, HostListener, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { forkJoin } from 'rxjs';
import { AdminCineService } from '../../core/services/admin-cine.service';
import { AdminPeliculaService } from '../../core/services/admin-pelicula.service';
import { AuthService } from '../../core/services/auth.service';
import { AdminPelicula } from '../../core/models/admin-pelicula.model';
import { AdminFuncion, Butaca, Formato, FuncionPayload, Idioma, Sala } from '../../core/models/admin-cine.model';

type Tab = 'funciones' | 'expiradas' | 'salas' | 'formatos';

interface FuncionForm {
  pelicula_id: number;
  sala_id: number;
  formato_id: number;
  fecha: string;
  hora: string;
  idioma: Idioma;
  precio_base: number;
}

const FUNCION_VACIA: FuncionForm = {
  pelicula_id: 0,
  sala_id: 0,
  formato_id: 0,
  fecha: '',
  hora: '',
  idioma: 'DOB',
  precio_base: 7200,
};

@Component({
  selector: 'app-admin-funciones',
  imports: [FormsModule, RouterLink, RouterLinkActive],
  templateUrl: './admin-funciones.component.html',
  // Mismo estilo que el resto del panel.
  styleUrls: ['../admin-dashboard/admin-dashboard.component.css', '../admin-dashboard/admin-layout.css'],
})
export class AdminFuncionesComponent implements OnInit {
  private readonly cine = inject(AdminCineService);
  private readonly peliculaService = inject(AdminPeliculaService);
  private readonly auth = inject(AuthService);

  readonly tab = signal<Tab>('funciones');
  // Funcion con el menu de acciones (⋯) abierto.
  readonly menuAbierto = signal<number | null>(null);

  toggleMenu(id: number, event: Event): void {
    event.stopPropagation();
    this.menuAbierto.update((actual) => (actual === id ? null : id));
  }

  @HostListener('document:click')
  @HostListener('document:keydown.escape')
  cerrarMenu(): void {
    this.menuAbierto.set(null);
  }

  // precio_base llega como texto ("3500.00"): se muestra como $3.500.
  precio(f: AdminFuncion): string {
    return '$' + Number(f.precio_base).toLocaleString('es-AR', { maximumFractionDigits: 2 });
  }
  readonly cargando = signal(true);
  readonly error = signal('');
  readonly aviso = signal('');

  readonly funciones = signal<AdminFuncion[]>([]);
  readonly peliculas = signal<AdminPelicula[]>([]);
  readonly salas = signal<Sala[]>([]);
  readonly formatos = signal<Formato[]>([]);
  readonly idiomas: { value: Idioma; label: string }[] = [
    { value: 'DOB', label: 'Doblada' },
    { value: 'SUB', label: 'Subtitulada' },
  ];

  // Funciones
  readonly filtroPelicula = signal(0);
  readonly mostrarCanceladas = signal(false);
  // La pestana Funciones muestra solo las proximas; las que ya pasaron quedan en Expiradas.
  readonly funcionesVisibles = computed(() =>
    this.funciones().filter(
      (f) =>
        !this.yaPaso(f) &&
        (this.mostrarCanceladas() || !f.cancelada) &&
        (!this.filtroPelicula() || f.pelicula_id === this.filtroPelicula()),
    ),
  );

  // Peliculas que tuvieron funciones pero no les queda ninguna proxima (sin contar canceladas).
  readonly peliculasExpiradas = computed(() => {
    const porPelicula = new Map<number, AdminFuncion[]>();
    for (const f of this.funciones()) {
      if (f.cancelada) continue;
      porPelicula.set(f.pelicula_id, [...(porPelicula.get(f.pelicula_id) ?? []), f]);
    }
    return [...porPelicula.values()]
      .filter((fs) => fs.every((f) => this.yaPaso(f)))
      .map((fs) => {
        const ultima = fs.reduce((a, b) => (a.fecha_hora > b.fecha_hora ? a : b));
        return { pelicula: ultima.pelicula, peliculaId: ultima.pelicula_id, ultima, cantidad: fs.length };
      })
      .sort((a, b) => b.ultima.fecha_hora.localeCompare(a.ultima.fecha_hora));
  });
  readonly mostrarFormFuncion = signal(false);
  readonly funcionEditandoId = signal<number | null>(null);
  readonly guardando = signal(false);
  readonly errores = signal<Record<string, string>>({});
  form: FuncionForm = { ...FUNCION_VACIA };

  // Salas
  nuevaSala = { nombre: '', filas: 8, butacas_por_fila: 12 };
  readonly salaButacas = signal<Sala | null>(null);
  readonly butacas = signal<Butaca[]>([]);
  readonly filasButacas = computed(() => {
    const porFila = new Map<string, Butaca[]>();
    for (const b of this.butacas()) {
      porFila.set(b.fila, [...(porFila.get(b.fila) ?? []), b]);
    }
    return [...porFila.entries()].map(([fila, butacas]) => ({ fila, butacas }));
  });
  readonly bloqueadas = computed(() => this.butacas().filter((b) => !b.activo).length);

  // Formatos
  nuevoFormato = '';

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    forkJoin([
      this.cine.listFunciones(),
      this.peliculaService.listCatalogo(),
      this.cine.listSalas(),
      this.cine.listFormatos(),
    ]).subscribe({
      next: ([funciones, catalogo, salas, formatos]) => {
        this.funciones.set(funciones);
        this.peliculas.set([...catalogo.enCartelera, ...catalogo.proximamente]);
        this.salas.set(salas);
        this.formatos.set(formatos);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar los datos.');
        this.cargando.set(false);
      },
    });
  }

  private recargarFunciones(): void {
    this.cine.listFunciones().subscribe((f) => this.funciones.set(f));
  }
  private recargarSalas(): void {
    this.cine.listSalas().subscribe((s) => this.salas.set(s));
  }
  private recargarFormatos(): void {
    this.cine.listFormatos().subscribe((f) => this.formatos.set(f));
  }

  private mostrarAviso(mensaje: string): void {
    this.aviso.set(mensaje);
    setTimeout(() => this.aviso.set(''), 3500);
  }

  private mostrarError(err: any, porDefecto: string): void {
    this.error.set(err?.error?.message ?? porDefecto);
  }

  fecha(f: AdminFuncion): string {
    const [y, m, d] = f.fecha_hora.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
  }
  hora(f: AdminFuncion): string {
    return f.fecha_hora.slice(11, 16);
  }
  idiomaLabel(v: Idioma): string {
    return this.idiomas.find((i) => i.value === v)?.label ?? v;
  }

  // --- Funciones ---

  // fecha_hora viene como "YYYY-MM-DD HH:mm:ss"; se compara en hora local, igual que se muestra.
  private yaPaso(f: AdminFuncion): boolean {
    return new Date(`${f.fecha_hora.slice(0, 10)}T${f.fecha_hora.slice(11, 16)}`).getTime() < Date.now();
  }

  reprogramar(peliculaId: number): void {
    this.tab.set('funciones');
    this.abrirNuevaFuncion(peliculaId);
  }

  abrirNuevaFuncion(peliculaId?: number): void {
    this.form = {
      ...FUNCION_VACIA,
      pelicula_id: peliculaId ?? this.peliculas()[0]?.id ?? 0,
      sala_id: this.salas()[0]?.id ?? 0,
      formato_id: this.formatos()[0]?.id ?? 0,
    };
    this.funcionEditandoId.set(null);
    this.errores.set({});
    this.mostrarFormFuncion.set(true);
  }

  abrirEdicionFuncion(f: AdminFuncion): void {
    this.form = {
      pelicula_id: f.pelicula_id,
      sala_id: f.sala_id,
      formato_id: f.formato_id,
      fecha: f.fecha_hora.slice(0, 10),
      hora: f.fecha_hora.slice(11, 16),
      idioma: f.idioma,
      precio_base: Number(f.precio_base),
    };
    this.funcionEditandoId.set(f.id);
    this.errores.set({});
    this.mostrarFormFuncion.set(true);
  }

  cerrarFormFuncion(): void {
    this.mostrarFormFuncion.set(false);
  }

  guardarFuncion(): void {
    const errores: Record<string, string> = {};
    if (!this.form.pelicula_id) errores['pelicula_id'] = 'Elegí una película.';
    if (!this.form.sala_id) errores['sala_id'] = 'Elegí una sala.';
    if (!this.form.formato_id) errores['formato_id'] = 'Elegí un formato.';
    if (!this.form.fecha) errores['fecha'] = 'Indicá la fecha.';
    if (!this.form.hora) errores['hora'] = 'Indicá el horario.';
    if (!(this.form.precio_base >= 0) || this.form.precio_base === null) errores['precio_base'] = 'Indicá el precio.';
    this.errores.set(errores);
    if (Object.keys(errores).length) return;

    const payload: FuncionPayload = {
      pelicula_id: this.form.pelicula_id,
      sala_id: this.form.sala_id,
      formato_id: this.form.formato_id,
      fecha_hora: `${this.form.fecha} ${this.form.hora}`,
      idioma: this.form.idioma,
      precio_base: this.form.precio_base,
    };
    const id = this.funcionEditandoId();
    this.guardando.set(true);
    this.error.set('');
    (id ? this.cine.updateFuncion(id, payload) : this.cine.createFuncion(payload)).subscribe({
      next: () => {
        this.guardando.set(false);
        this.mostrarFormFuncion.set(false);
        this.mostrarAviso(id ? 'Función actualizada.' : 'Función creada.');
        this.recargarFunciones();
      },
      error: (err) => {
        this.guardando.set(false);
        this.mostrarError(err, 'No se pudo guardar la función.');
      },
    });
  }

  cancelarFuncion(f: AdminFuncion): void {
    if (!confirm(`¿Cancelar la función de ${f.pelicula?.titulo} del ${this.fecha(f)} ${this.hora(f)}?`)) return;
    this.cine.cancelarFuncion(f.id).subscribe({
      next: () => {
        this.mostrarAviso('Función cancelada.');
        this.recargarFunciones();
      },
      error: (err) => this.mostrarError(err, 'No se pudo cancelar la función.'),
    });
  }

  // --- Salas y butacas ---

  crearSala(): void {
    const s = this.nuevaSala;
    if (!s.nombre.trim()) {
      this.error.set('Ponele un nombre a la sala.');
      return;
    }
    this.error.set('');
    this.cine.createSala({ ...s, nombre: s.nombre.trim() }).subscribe({
      next: () => {
        this.nuevaSala = { nombre: '', filas: 8, butacas_por_fila: 12 };
        this.mostrarAviso('Sala creada con sus butacas.');
        this.recargarSalas();
      },
      error: (err) => this.mostrarError(err, 'No se pudo crear la sala.'),
    });
  }

  renombrarSala(sala: Sala): void {
    const nombre = prompt('Nuevo nombre de la sala', sala.nombre)?.trim();
    if (!nombre || nombre === sala.nombre) return;
    this.cine.updateSala(sala.id, nombre).subscribe({
      next: () => this.recargarSalas(),
      error: (err) => this.mostrarError(err, 'No se pudo renombrar la sala.'),
    });
  }

  borrarSala(sala: Sala): void {
    if (!confirm(`¿Borrar ${sala.nombre} y sus butacas?`)) return;
    this.cine.deleteSala(sala.id).subscribe({
      next: () => {
        this.mostrarAviso('Sala borrada.');
        this.recargarSalas();
      },
      error: (err) => this.mostrarError(err, 'No se pudo borrar la sala.'),
    });
  }

  abrirButacas(sala: Sala): void {
    this.salaButacas.set(sala);
    this.butacas.set([]);
    this.cine.listButacas(sala.id).subscribe((b) => this.butacas.set(b));
  }

  cerrarButacas(): void {
    this.salaButacas.set(null);
  }

  toggleButaca(b: Butaca): void {
    this.cine.setButacaActiva(b.id, !b.activo).subscribe({
      next: (nueva) => this.butacas.update((lista) => lista.map((x) => (x.id === b.id ? { ...x, activo: nueva.activo } : x))),
      error: (err) => this.mostrarError(err, 'No se pudo cambiar la butaca.'),
    });
  }

  // --- Formatos ---

  crearFormato(): void {
    const nombre = this.nuevoFormato.trim();
    if (!nombre) return;
    this.cine.createFormato(nombre).subscribe({
      next: () => {
        this.nuevoFormato = '';
        this.recargarFormatos();
      },
      error: (err) => this.mostrarError(err, 'No se pudo crear el formato.'),
    });
  }

  renombrarFormato(f: Formato): void {
    const nombre = prompt('Nuevo nombre del formato', f.nombre)?.trim();
    if (!nombre || nombre === f.nombre) return;
    this.cine.updateFormato(f.id, nombre).subscribe({
      next: () => {
        this.recargarFormatos();
        this.recargarFunciones();
      },
      error: (err) => this.mostrarError(err, 'No se pudo renombrar el formato.'),
    });
  }

  borrarFormato(f: Formato): void {
    if (!confirm(`¿Borrar el formato ${f.nombre}?`)) return;
    this.cine.deleteFormato(f.id).subscribe({
      next: () => this.recargarFormatos(),
      error: (err) => this.mostrarError(err, 'No se pudo borrar el formato.'),
    });
  }

  cerrarSesion(): void {
    this.auth.logout();
  }
}
