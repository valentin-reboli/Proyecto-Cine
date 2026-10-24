import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { ComboService } from '../../core/services/combo.service';
import { AuthService } from '../../core/services/auth.service';
import { CATEGORIAS_COMBO, Combo, ComboPayload } from '../../core/models/combo.model';

interface ComboForm {
  nombre: string;
  categoria: ComboPayload['categoria'];
  descripcion: string;
  precio: number;
  imagen_url: string;
  activo: boolean;
}

const FORM_VACIO: ComboForm = { nombre: '', categoria: 'combo', descripcion: '', precio: 0, imagen_url: '', activo: true };

@Component({
  selector: 'app-admin-combos',
  imports: [FormsModule, RouterLink, RouterLinkActive],
  templateUrl: './admin-combos.component.html',
  // Mismo estilo que el resto del panel.
  styleUrls: ['../admin-dashboard/admin-dashboard.component.css', '../admin-dashboard/admin-layout.css'],
})
export class AdminCombosComponent implements OnInit {
  private readonly service = inject(ComboService);
  private readonly auth = inject(AuthService);

  readonly categorias = CATEGORIAS_COMBO;
  readonly combos = signal<Combo[]>([]);
  readonly cargando = signal(true);
  readonly error = signal('');
  readonly aviso = signal('');
  readonly mostrarForm = signal(false);
  readonly editandoId = signal<number | null>(null);
  readonly guardando = signal(false);
  readonly errores = signal<Record<string, string>>({});
  form: ComboForm = { ...FORM_VACIO };

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.service.listTodos().subscribe({
      next: (combos) => {
        this.combos.set(combos);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar los combos.');
        this.cargando.set(false);
      },
    });
  }

  private mostrarAviso(mensaje: string): void {
    this.aviso.set(mensaje);
    setTimeout(() => this.aviso.set(''), 3500);
  }

  categoriaLabel(c: string): string {
    return this.categorias.find((x) => x.value === c)?.label ?? c;
  }

  abrirNuevo(): void {
    this.form = { ...FORM_VACIO };
    this.editandoId.set(null);
    this.errores.set({});
    this.mostrarForm.set(true);
  }

  abrirEdicion(c: Combo): void {
    this.form = {
      nombre: c.nombre,
      categoria: c.categoria,
      descripcion: c.descripcion ?? '',
      precio: Number(c.precio),
      imagen_url: c.imagen_url ?? '',
      activo: c.activo,
    };
    this.editandoId.set(c.id);
    this.errores.set({});
    this.mostrarForm.set(true);
  }

  cerrarForm(): void {
    this.mostrarForm.set(false);
  }

  private payload(f: ComboForm = this.form): ComboPayload {
    return {
      nombre: f.nombre.trim(),
      categoria: f.categoria,
      descripcion: f.descripcion.trim() || null,
      precio: f.precio,
      imagen_url: f.imagen_url.trim() || null,
      activo: f.activo,
    };
  }

  guardar(): void {
    const errores: Record<string, string> = {};
    if (!this.form.nombre.trim()) errores['nombre'] = 'Ponele un nombre.';
    if (this.form.precio === null || !(this.form.precio >= 0)) errores['precio'] = 'Indicá el precio.';
    this.errores.set(errores);
    if (Object.keys(errores).length) return;

    const id = this.editandoId();
    this.guardando.set(true);
    this.error.set('');
    (id ? this.service.update(id, this.payload()) : this.service.create(this.payload())).subscribe({
      next: () => {
        this.guardando.set(false);
        this.mostrarForm.set(false);
        this.mostrarAviso(id ? 'Combo actualizado.' : 'Combo creado.');
        this.cargar();
      },
      error: (err) => {
        this.guardando.set(false);
        this.error.set(err?.error?.message ?? 'No se pudo guardar el combo.');
      },
    });
  }

  toggleActivo(c: Combo): void {
    this.service.update(c.id, { ...c, descripcion: c.descripcion, imagen_url: c.imagen_url, activo: !c.activo }).subscribe({
      next: () => this.cargar(),
      error: (err) => this.error.set(err?.error?.message ?? 'No se pudo cambiar el estado.'),
    });
  }

  eliminar(c: Combo): void {
    if (!confirm(`¿Borrar "${c.nombre}"?`)) return;
    this.service.delete(c.id).subscribe({
      next: () => {
        this.mostrarAviso('Combo borrado.');
        this.cargar();
      },
      error: (err) => this.error.set(err?.error?.message ?? 'No se pudo borrar el combo.'),
    });
  }

  cerrarSesion(): void {
    this.auth.logout();
  }
}
