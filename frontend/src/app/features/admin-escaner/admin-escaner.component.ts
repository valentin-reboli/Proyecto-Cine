import { Component, ElementRef, OnDestroy, ViewChild, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive } from '@angular/router';
import jsQR from 'jsqr';
import { AuthService } from '../../core/services/auth.service';
import { EntradaService } from '../../core/services/entrada.service';
import { Entrada, EstadoAcceso } from '../../core/models/entrada.model';

type Paso = 'inicio' | 'camara' | 'buscando' | 'resultado';

interface Escaneo {
  codigo: string;
  cliente: string;
  pelicula: string;
  butacas: number;
  // confirmada: se marco la asistencia desde este celular.
  estado: EstadoAcceso | 'confirmada';
  hora: Date;
}

const TEXTO_ESTADO: Record<EstadoAcceso, string> = {
  valida: 'Entrada válida',
  usada: 'Ya fue usada',
  no_pagada: 'No está pagada',
  cancelada: 'Función cancelada',
};

// Cada cuanto se analiza un cuadro de la camara (no hace falta hacerlo en todos).
const MS_ENTRE_LECTURAS = 150;

@Component({
  selector: 'app-admin-escaner',
  imports: [DatePipe, FormsModule, RouterLink, RouterLinkActive],
  templateUrl: './admin-escaner.component.html',
  styleUrls: [
    '../admin-dashboard/admin-dashboard.component.css',
    '../admin-dashboard/admin-layout.css',
    './admin-escaner.component.css',
  ],
})
export class AdminEscanerComponent implements OnDestroy {
  private readonly service = inject(EntradaService);
  private readonly auth = inject(AuthService);

  @ViewChild('video') videoRef?: ElementRef<HTMLVideoElement>;

  readonly textoEstado = TEXTO_ESTADO;
  readonly paso = signal<Paso>('inicio');
  readonly entrada = signal<Entrada | null>(null);
  readonly error = signal('');
  readonly errorCamara = signal('');
  readonly confirmando = signal(false);
  readonly confirmada = signal(false);
  readonly historial = signal<Escaneo[]>([]);
  codigoManual = '';

  readonly estado = computed(() => this.entrada()?.estado_acceso ?? 'valida');
  // Butacas ordenadas por fila y numero (A1, A2, B5...).
  readonly butacas = computed(() =>
    (this.entrada()?.reserva_asientos ?? [])
      .map((r) => r.asiento)
      .filter((a) => !!a)
      .sort((a, b) => a.fila.localeCompare(b.fila) || a.numero - b.numero),
  );

  private stream: MediaStream | null = null;
  private canvas = document.createElement('canvas');
  private frame = 0;
  private ultimaLectura = 0;

  ngOnDestroy(): void {
    this.apagarCamara();
  }

  async abrirCamara(): Promise<void> {
    this.errorCamara.set('');
    this.error.set('');
    if (!navigator.mediaDevices?.getUserMedia) {
      this.errorCamara.set(
        'Este navegador no permite usar la cámara (hace falta entrar por https). Podés escribir el código a mano.',
      );
      return;
    }
    try {
      // facingMode environment = camara trasera del celular.
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false,
      });
    } catch (err) {
      const nombre = (err as DOMException)?.name;
      this.errorCamara.set(
        nombre === 'NotAllowedError'
          ? 'No diste permiso para usar la cámara. Habilitalo en el navegador y probá de nuevo.'
          : 'No pudimos abrir la cámara. Podés escribir el código a mano.',
      );
      return;
    }
    this.paso.set('camara');
    // Esperamos a que Angular dibuje el <video> antes de conectarle la camara.
    setTimeout(() => this.conectarVideo());
  }

  private conectarVideo(): void {
    const video = this.videoRef?.nativeElement;
    if (!video || !this.stream) return;
    video.srcObject = this.stream;
    video.setAttribute('playsinline', 'true'); // iOS: que no abra el video a pantalla completa
    video.play().catch(() => undefined);
    this.frame = requestAnimationFrame(() => this.leerCuadro());
  }

  private leerCuadro(): void {
    const video = this.videoRef?.nativeElement;
    if (!video || !this.stream || this.paso() !== 'camara') return;

    const ahora = performance.now();
    if (video.readyState === video.HAVE_ENOUGH_DATA && ahora - this.ultimaLectura > MS_ENTRE_LECTURAS) {
      this.ultimaLectura = ahora;
      const codigo = this.decodificar(video);
      if (codigo) {
        navigator.vibrate?.(120);
        this.buscar(codigo);
        return;
      }
    }
    this.frame = requestAnimationFrame(() => this.leerCuadro());
  }

  private decodificar(video: HTMLVideoElement): string | null {
    // Achicamos el cuadro para que la lectura sea rapida en celulares.
    const escala = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight));
    const ancho = Math.round(video.videoWidth * escala);
    const alto = Math.round(video.videoHeight * escala);
    if (!ancho || !alto) return null;

    this.canvas.width = ancho;
    this.canvas.height = alto;
    const ctx = this.canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) return null;
    ctx.drawImage(video, 0, 0, ancho, alto);
    const imagen = ctx.getImageData(0, 0, ancho, alto);
    const qr = jsQR(imagen.data, ancho, alto, { inversionAttempts: 'dontInvert' });
    return qr?.data?.trim() || null;
  }

  apagarCamara(): void {
    cancelAnimationFrame(this.frame);
    this.stream?.getTracks().forEach((track) => track.stop());
    this.stream = null;
  }

  cerrarCamara(): void {
    this.apagarCamara();
    this.paso.set('inicio');
  }

  buscarManual(): void {
    const codigo = this.codigoManual.trim();
    if (!codigo) return;
    this.buscar(codigo);
  }

  private buscar(texto: string): void {
    this.apagarCamara();
    const codigo = this.extraerCodigo(texto);
    this.paso.set('buscando');
    this.error.set('');
    this.entrada.set(null);
    this.confirmada.set(false);

    this.service.consultar(codigo).subscribe({
      next: (entrada) => {
        this.entrada.set(entrada);
        this.paso.set('resultado');
        this.codigoManual = '';
        this.agregarAlHistorial(entrada);
      },
      error: (err) => {
        this.error.set(
          err?.status === 404
            ? 'Ese QR no corresponde a ninguna entrada del cine.'
            : (err?.error?.message ?? 'No pudimos consultar la entrada. Probá de nuevo.'),
        );
        this.paso.set('resultado');
      },
    });
  }

  // El QR trae el codigo de la reserva. Si alguna vez trae un link, usamos lo ultimo de la ruta.
  private extraerCodigo(texto: string): string {
    const limpio = texto.trim();
    if (!/^https?:\/\//i.test(limpio)) return limpio;
    const partes = limpio.split(/[/?#]/).filter(Boolean);
    return (partes[partes.length - 1] ?? limpio).replace(/\.(png|pdf)$/i, '');
  }

  confirmarAsistencia(): void {
    const actual = this.entrada();
    if (!actual || this.confirmando()) return;
    this.confirmando.set(true);
    this.error.set('');

    this.service.confirmarAsistencia(actual.codigo_qr).subscribe({
      next: (entrada) => {
        this.confirmando.set(false);
        this.confirmada.set(true);
        this.entrada.set({ ...actual, usada_en: entrada.usada_en, estado_acceso: 'usada' });
        this.actualizarHistorial(actual.codigo_qr, 'confirmada');
        navigator.vibrate?.([80, 60, 80]);
      },
      error: (err) => {
        this.confirmando.set(false);
        if (err?.status === 409) {
          // Alguien la escaneo justo antes desde otro celular.
          this.entrada.set({ ...actual, usada_en: err.error?.usada_en ?? actual.usada_en, estado_acceso: 'usada' });
          this.actualizarHistorial(actual.codigo_qr, 'usada');
        }
        this.error.set(err?.error?.message ?? 'No pudimos confirmar la asistencia.');
      },
    });
  }

  escanearOtra(): void {
    this.entrada.set(null);
    this.error.set('');
    this.confirmada.set(false);
    this.abrirCamara();
  }

  volverAlInicio(): void {
    this.entrada.set(null);
    this.error.set('');
    this.confirmada.set(false);
    this.paso.set('inicio');
  }

  private agregarAlHistorial(entrada: Entrada): void {
    const escaneo: Escaneo = {
      codigo: entrada.codigo_qr,
      cliente: entrada.invitado_nombre,
      pelicula: entrada.funcion?.pelicula?.titulo ?? '—',
      butacas: entrada.reserva_asientos?.length ?? 0,
      estado: entrada.estado_acceso ?? 'valida',
      hora: new Date(),
    };
    this.historial.update((lista) => [escaneo, ...lista.filter((e) => e.codigo !== escaneo.codigo)].slice(0, 10));
  }

  private actualizarHistorial(codigo: string, estado: Escaneo['estado']): void {
    this.historial.update((lista) => lista.map((e) => (e.codigo === codigo ? { ...e, estado, hora: new Date() } : e)));
  }

  cerrarSesion(): void {
    this.apagarCamara();
    this.auth.logout();
  }
}
