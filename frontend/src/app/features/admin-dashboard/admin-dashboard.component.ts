import { Component, HostListener, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink, RouterLinkActive } from '@angular/router';
import { AdminPeliculaService } from '../../core/services/admin-pelicula.service';
import { AdminUsuarioService } from '../../core/services/admin-usuario.service';
import { AuthService } from '../../core/services/auth.service';
import { AdminPelicula, AdminPeliculaForm, Genero } from '../../core/models/admin-pelicula.model';
import { AdminUsuario, AdminUsuarioForm, CambioPasswordForm } from '../../core/models/admin-usuario.model';

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

const FORM_USUARIO_VACIO: AdminUsuarioForm = {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
};

type Seccion = 'cartelera' | 'proximamente';
type Vista = 'cartelera' | 'configuracion';

@Component({
  selector: 'app-admin-dashboard',
  imports: [DatePipe, FormsModule, RouterLink, RouterLinkActive],
  templateUrl: './admin-dashboard.component.html',
  styleUrls: ['./admin-dashboard.component.css', './admin-layout.css'],
})
export class AdminDashboardComponent implements OnInit {
  private readonly peliculaService = inject(AdminPeliculaService);
  private readonly usuarioService = inject(AdminUsuarioService);
  private readonly auth = inject(AuthService);

  // /admin muestra la cartelera y /admin/configuracion los usuarios (ver app.routes.ts)
  readonly vista: Vista = inject(ActivatedRoute).snapshot.data['vista'] ?? 'cartelera';

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
  // Fila con el menu de acciones (⋯) abierto: 'pelicula-3' o 'usuario-1'.
  readonly menuAbierto = signal<string | null>(null);

  toggleMenu(id: string, event: Event): void {
    event.stopPropagation();
    this.menuAbierto.update((actual) => (actual === id ? null : id));
  }

  @HostListener('document:click')
  @HostListener('document:keydown.escape')
  cerrarMenu(): void {
    this.menuAbierto.set(null);
  }

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

  // Usuarios del panel
  readonly usuarios = signal<AdminUsuario[]>([]);
  readonly miId = signal<number | null>(null);
  readonly mostrarFormUsuario = signal(false);
  readonly guardandoUsuario = signal(false);
  readonly erroresUsuario = signal<Record<string, string>>({});
  formUsuario: AdminUsuarioForm = { ...FORM_USUARIO_VACIO };

  // Cambio de contraseña (null = modal cerrado)
  readonly usuarioPassword = signal<AdminUsuario | null>(null);
  formPassword: CambioPasswordForm = { password: '', password_confirmation: '' };

  // Edicion de nombre / email (null = modal cerrado)
  readonly usuarioEditando = signal<AdminUsuario | null>(null);
  formEdicion = { name: '', email: '' };


  ngOnInit(): void {
    if (this.vista === 'configuracion') {
      this.auth.me().subscribe({ next: (yo) => this.miId.set(yo.id) });
      this.cargarUsuarios();
      return;
    }

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

  // --- Usuarios ---

  cargarUsuarios(): void {
    this.usuarioService.list().subscribe({
      next: (usuarios) => this.usuarios.set(usuarios),
      error: () => this.error.set('No se pudieron cargar los usuarios.'),
    });
  }

  abrirNuevoUsuario(): void {
    this.formUsuario = { ...FORM_USUARIO_VACIO };
    this.erroresUsuario.set({});
    this.mostrarFormUsuario.set(true);
  }

  cerrarFormUsuario(): void {
    if (this.guardandoUsuario()) {
      return;
    }

    this.mostrarFormUsuario.set(false);
  }

  private validarUsuario(): boolean {
    const errores: Record<string, string> = {};
    const f = this.formUsuario;

    Object.assign(errores, this.erroresDatos(f), this.erroresPassword(f));

    this.erroresUsuario.set(errores);

    return Object.keys(errores).length === 0;
  }

  private erroresDatos(f: { name: string; email: string }): Record<string, string> {
    const errores: Record<string, string> = {};

    if (!f.name.trim()) {
      errores['name'] = 'El nombre es obligatorio.';
    }
    if (!/^\S+@\S+\.\S+$/.test(f.email.trim())) {
      errores['email'] = 'Ingresá un email válido.';
    }

    return errores;
  }

  abrirEdicionUsuario(usuario: AdminUsuario): void {
    this.formEdicion = { name: usuario.name, email: usuario.email };
    this.erroresUsuario.set({});
    this.usuarioEditando.set(usuario);
  }

  cerrarEdicionUsuario(): void {
    if (this.guardandoUsuario()) {
      return;
    }

    this.usuarioEditando.set(null);
  }

  guardarEdicionUsuario(): void {
    const usuario = this.usuarioEditando();
    const errores = this.erroresDatos(this.formEdicion);

    this.erroresUsuario.set(errores);

    if (!usuario || Object.keys(errores).length > 0) {
      return;
    }

    this.guardandoUsuario.set(true);
    this.error.set('');

    const payload = { name: this.formEdicion.name.trim(), email: this.formEdicion.email.trim() };

    this.usuarioService.update(usuario.id, payload).subscribe({
      next: () => {
        this.guardandoUsuario.set(false);
        this.usuarioEditando.set(null);
        this.mostrarAviso('Usuario actualizado.');
        this.cargarUsuarios();
      },
      error: (err) => {
        this.guardandoUsuario.set(false);

        if (err?.error?.errors?.email) {
          this.erroresUsuario.set({ email: 'Ya existe un usuario con ese email.' });
        } else {
          this.error.set(err?.error?.message ?? 'No se pudo actualizar el usuario.');
        }
      },
    });
  }

  private erroresPassword(f: CambioPasswordForm): Record<string, string> {
    const errores: Record<string, string> = {};

    if (f.password.length < 8) {
      errores['password'] = 'La contraseña tiene que tener al menos 8 caracteres.';
    }
    if (f.password_confirmation !== f.password) {
      errores['password_confirmation'] = 'Las contraseñas no coinciden.';
    }

    return errores;
  }

  abrirCambioPassword(usuario: AdminUsuario): void {
    this.formPassword = { password: '', password_confirmation: '' };
    this.erroresUsuario.set({});
    this.usuarioPassword.set(usuario);
  }

  cerrarCambioPassword(): void {
    if (this.guardandoUsuario()) {
      return;
    }

    this.usuarioPassword.set(null);
  }

  guardarPassword(): void {
    const usuario = this.usuarioPassword();
    const errores = this.erroresPassword(this.formPassword);

    this.erroresUsuario.set(errores);

    if (!usuario || Object.keys(errores).length > 0) {
      return;
    }

    this.guardandoUsuario.set(true);
    this.error.set('');

    this.usuarioService.cambiarPassword(usuario.id, this.formPassword).subscribe({
      next: () => {
        this.guardandoUsuario.set(false);
        this.usuarioPassword.set(null);
        this.mostrarAviso(`Contraseña de ${usuario.email} actualizada.`);
      },
      error: (err) => {
        this.guardandoUsuario.set(false);
        this.error.set(err?.error?.message ?? 'No se pudo cambiar la contraseña.');
      },
    });
  }

  guardarUsuario(): void {
    if (!this.validarUsuario()) {
      return;
    }

    this.guardandoUsuario.set(true);
    this.error.set('');

    const payload: AdminUsuarioForm = {
      ...this.formUsuario,
      name: this.formUsuario.name.trim(),
      email: this.formUsuario.email.trim(),
    };

    this.usuarioService.create(payload).subscribe({
      next: (usuario) => {
        this.guardandoUsuario.set(false);
        this.mostrarFormUsuario.set(false);
        this.mostrarAviso(`Usuario ${usuario.email} creado. Ya puede iniciar sesión.`);
        this.cargarUsuarios();
      },
      error: (err) => {
        this.guardandoUsuario.set(false);

        if (err?.error?.errors?.email) {
          this.erroresUsuario.set({ email: 'Ya existe un usuario con ese email.' });
        } else {
          this.error.set(err?.error?.message ?? 'No se pudo crear el usuario.');
        }
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
