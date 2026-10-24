import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { forkJoin } from 'rxjs';
import { AdminCampaniaService } from '../../core/services/admin-campania.service';
import { AuthService } from '../../core/services/auth.service';
import { Campania, CampaniaPayload, CanalCampania, Cliente } from '../../core/models/admin-campania.model';

type Tab = 'campanias' | 'clientes';

interface CampaniaForm {
  nombre: string;
  canal: CanalCampania;
  asunto: string;
  mensaje: string;
  programar: boolean;
  fecha_hora: string; // datetime-local, hora del navegador
}

const FORM_VACIO: CampaniaForm = { nombre: '', canal: 'email', asunto: '', mensaje: '', programar: false, fecha_hora: '' };

@Component({
  selector: 'app-admin-campanias',
  imports: [DatePipe, FormsModule, RouterLink, RouterLinkActive],
  templateUrl: './admin-campanias.component.html',
  // Mismo estilo que el resto del panel.
  styleUrls: ['../admin-dashboard/admin-dashboard.component.css', '../admin-dashboard/admin-layout.css'],
})
export class AdminCampaniasComponent implements OnInit {
  private readonly service = inject(AdminCampaniaService);
  private readonly auth = inject(AuthService);

  readonly tab = signal<Tab>('campanias');
  readonly cargando = signal(true);
  readonly error = signal('');
  readonly aviso = signal('');
  readonly campanias = signal<Campania[]>([]);
  readonly clientes = signal<Cliente[]>([]);
  readonly conPermiso = computed(() => this.clientes().filter((c) => c.acepta_marketing).length);

  readonly canales: { value: CanalCampania; label: string }[] = [
    { value: 'email', label: 'Correo electrónico' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'telegram', label: 'Telegram' },
  ];

  readonly mostrarForm = signal(false);
  readonly editandoId = signal<number | null>(null);
  readonly guardando = signal(false);
  readonly enviandoId = signal<number | null>(null);
  readonly errores = signal<Record<string, string>>({});
  form: CampaniaForm = { ...FORM_VACIO };

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    forkJoin([this.service.list(), this.service.listClientes()]).subscribe({
      next: ([campanias, clientes]) => {
        this.campanias.set(campanias);
        this.clientes.set(clientes);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudieron cargar los datos.');
        this.cargando.set(false);
      },
    });
  }

  private recargarCampanias(): void {
    this.service.list().subscribe((c) => this.campanias.set(c));
  }

  private mostrarAviso(mensaje: string): void {
    this.aviso.set(mensaje);
    setTimeout(() => this.aviso.set(''), 3500);
  }

  canalLabel(c: CanalCampania): string {
    return this.canales.find((x) => x.value === c)?.label ?? c;
  }

  destinatariosPosibles(canal: CanalCampania): number {
    return this.clientes().filter((c) => c.acepta_marketing && (canal === 'email' ? !!c.correo : !!c.telefono)).length;
  }

  // El navegador da la hora local; al backend se manda en ISO (UTC) para que programe bien.
  private aLocalInput(iso: string): string {
    const d = new Date(iso);
    const p = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
  }

  abrirNueva(): void {
    this.form = { ...FORM_VACIO };
    this.editandoId.set(null);
    this.errores.set({});
    this.mostrarForm.set(true);
  }

  abrirEdicion(c: Campania): void {
    this.form = {
      nombre: c.nombre,
      canal: c.canal,
      asunto: c.asunto ?? '',
      mensaje: c.mensaje,
      programar: !!c.programada_para,
      fecha_hora: c.programada_para ? this.aLocalInput(c.programada_para) : '',
    };
    this.editandoId.set(c.id);
    this.errores.set({});
    this.mostrarForm.set(true);
  }

  cerrarForm(): void {
    this.mostrarForm.set(false);
  }

  guardar(): void {
    const f = this.form;
    const errores: Record<string, string> = {};
    if (!f.nombre.trim()) errores['nombre'] = 'Ponele un nombre a la campaña.';
    if (f.canal === 'email' && !f.asunto.trim()) errores['asunto'] = 'El correo necesita un asunto.';
    if (!f.mensaje.trim()) errores['mensaje'] = 'Escribí el mensaje.';
    if (f.programar && (!f.fecha_hora || new Date(f.fecha_hora) <= new Date())) {
      errores['fecha_hora'] = 'Elegí una fecha y hora futuras.';
    }
    this.errores.set(errores);
    if (Object.keys(errores).length) return;

    const payload: CampaniaPayload = {
      nombre: f.nombre.trim(),
      canal: f.canal,
      asunto: f.canal === 'email' ? f.asunto.trim() : null,
      mensaje: f.mensaje.trim(),
      programada_para: f.programar ? new Date(f.fecha_hora).toISOString() : null,
    };
    const id = this.editandoId();
    this.guardando.set(true);
    this.error.set('');
    (id ? this.service.update(id, payload) : this.service.create(payload)).subscribe({
      next: () => {
        this.guardando.set(false);
        this.mostrarForm.set(false);
        this.mostrarAviso(id ? 'Campaña actualizada.' : 'Campaña creada.');
        this.recargarCampanias();
      },
      error: (err) => {
        this.guardando.set(false);
        this.error.set(err?.error?.message ?? 'No se pudo guardar la campaña.');
      },
    });
  }

  enviar(c: Campania): void {
    const n = this.destinatariosPosibles(c.canal);
    if (!confirm(`Se va a enviar "${c.nombre}" a ${n} cliente(s) que aceptaron recibir publicidad. ¿Enviar ahora?`)) return;
    this.enviandoId.set(c.id);
    this.error.set('');
    this.service.enviar(c.id).subscribe({
      next: (res) => {
        this.enviandoId.set(null);
        if (res.estado === 'enviada') this.mostrarAviso(`Enviada a ${res.destinatarios} cliente(s).`);
        else this.error.set(res.error ?? 'No se pudo enviar la campaña.');
        this.recargarCampanias();
      },
      error: (err) => {
        this.enviandoId.set(null);
        this.error.set(err?.error?.message ?? 'No se pudo enviar la campaña.');
      },
    });
  }

  eliminar(c: Campania): void {
    if (!confirm(`¿Eliminar la campaña "${c.nombre}"?`)) return;
    this.service.delete(c.id).subscribe({
      next: () => {
        this.mostrarAviso('Campaña eliminada.');
        this.recargarCampanias();
      },
      error: (err) => this.error.set(err?.error?.message ?? 'No se pudo eliminar la campaña.'),
    });
  }

  cerrarSesion(): void {
    this.auth.logout();
  }
}
