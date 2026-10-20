import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import qrcode from 'qrcode-generator';
import { API_URL } from '../../core/services/api.config';
import { CatalogService } from '../../core/services/catalog.service';
import { BookingStateService } from '../../core/services/booking-state.service';
import { Movie, Showtime } from '../../core/models/movie.model';
import { Dia, agruparPorDia } from '../../core/utils/funciones';

type Step = 'pelicula' | 'funcion' | 'seats' | 'datos' | 'payment' | 'confirmation';
type PaymentMethodKey = 'tarjeta' | 'mercadopago';

interface ApiAsiento {
  id: number;
  fila: string;
  numero: number;
  estado: 'disponible' | 'temporal' | 'ocupado' | 'bloqueado';
}

interface ProgressStep {
  key: Step;
  label: string;
  icon: string;
  hasLine: boolean;
  dotBg: string;
  dotFg: string;
  dotBorder: string;
  labelColor: string;
}

const MAX_SEATS = 8;
const STEP_ORDER: Step[] = ['pelicula', 'funcion', 'seats', 'datos', 'payment'];
const STEP_LABELS: Record<Step, string> = {
  pelicula: 'Película',
  funcion: 'Función',
  seats: 'Butacas',
  datos: 'Tus datos',
  payment: 'Pago',
  confirmation: 'Confirmación',
};
@Component({
  selector: 'app-booking',
  imports: [FormsModule],
  templateUrl: './booking.component.html',
  styleUrl: './booking.component.css',
})
export class BookingComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly http = inject(HttpClient);
  private readonly bookingState = inject(BookingStateService);
  private readonly catalog = inject(CatalogService);

  readonly selection = this.bookingState.selection;

  // Peliculas en cartelera que tienen funciones por venir.
  readonly cargandoCatalogo = this.catalog.loading;
  readonly peliculas = computed(() =>
    this.catalog.movies().filter((m) => agruparPorDia(m.todayTimes).length > 0),
  );

  readonly paymentMethods: { key: PaymentMethodKey; label: string }[] = [
    { key: 'tarjeta', label: 'Tarjeta de crédito / débito' },
    { key: 'mercadopago', label: 'Mercado Pago' },
  ];

  readonly step = signal<Step>('pelicula');
  readonly asientos = signal<ApiAsiento[]>([]);
  readonly cargandoAsientos = signal(false);
  readonly selectedSeats = signal<number[]>([]);
  readonly paymentMethod = signal<PaymentMethodKey>('tarjeta');
  readonly procesando = signal(false);
  readonly error = signal('');
  readonly erroresDatos = signal<Record<string, string>>({});
  readonly codigo = signal<string | null>(null);
  readonly reservaId = signal<number | null>(null);
  readonly qrSrc = signal<string | null>(null);

  datos = { nombre: '', correo: '', telefono: '', acepta_marketing: false };

  // Funciones programadas de la pelicula (las que ya pasaron no se muestran), agrupadas por dia.
  readonly dias = computed((): Dia[] => agruparPorDia(this.selection()?.movie.todayTimes ?? []));

  readonly seatRows = computed(() => {
    const porFila = new Map<string, ApiAsiento[]>();
    this.asientos().forEach((a) => porFila.set(a.fila, [...(porFila.get(a.fila) ?? []), a]));
    return [...porFila.entries()].map(([row, seats]) => ({ row, seats }));
  });

  readonly selectedLabels = computed(() => {
    const ids = this.selectedSeats();
    return this.asientos()
      .filter((a) => ids.includes(a.id))
      .map((a) => a.fila + a.numero);
  });

  readonly price = computed(() => this.selection()?.showtime?.price ?? 0);
  readonly total = computed(() => this.price() * this.selectedSeats().length);

  readonly progressSteps = computed((): ProgressStep[] => {
    const step = this.step();
    const currentIdx = step === 'confirmation' ? STEP_ORDER.length : STEP_ORDER.indexOf(step);
    const all: Step[] = [...STEP_ORDER, 'confirmation'];
    return all.map((key, i) => {
      const done = i < currentIdx || step === 'confirmation';
      const current = key === step;
      const active = current || done;
      return {
        key,
        label: STEP_LABELS[key],
        icon: done ? '✓' : String(i + 1),
        hasLine: i < all.length - 1,
        dotBg: current ? 'var(--accent)' : done ? 'var(--accent-soft-12)' : 'transparent',
        dotFg: current ? 'var(--bg)' : done ? 'var(--accent)' : 'var(--text-muted)',
        dotBorder: active ? 'var(--accent)' : 'var(--border-strong)',
        labelColor: active ? 'var(--text-primary)' : 'var(--text-muted)',
      };
    });
  });

  ngOnInit(): void {
    // Siempre se arranca eligiendo la pelicula.
    this.bookingState.clear();
  }

  langLabel(f: Showtime): string {
    return this.catalog.langLabel(f.lang);
  }

  formatPrice(amount: number): string {
    return this.catalog.formatPrice(amount);
  }

  fechaLabel(f: Showtime): string {
    const dia = this.dias().find((d) => d.fecha === f.date);
    return dia?.label ?? f.date ?? '';
  }

  // --- Paso 1: elegir pelicula ---

  elegirPelicula(movie: Movie): void {
    this.bookingState.select(movie);
    this.step.set('funcion');
  }

  // --- Paso 2: elegir funcion ---

  elegirFuncion(f: Showtime): void {
    const sel = this.selection();
    if (!sel) return;
    this.bookingState.select(sel.movie, f);
    this.selectedSeats.set([]);
    this.error.set('');
    this.cargarAsientos(f);
    this.step.set('seats');
  }

  private cargarAsientos(f: Showtime): void {
    this.cargandoAsientos.set(true);
    this.asientos.set([]);
    this.http.get<ApiAsiento[]>(`${API_URL}/funciones/${f.funcionId}/asientos`).subscribe({
      next: (asientos) => {
        this.asientos.set(asientos);
        this.cargandoAsientos.set(false);
      },
      error: () => {
        this.error.set('No pudimos cargar las butacas. Intentá de nuevo.');
        this.cargandoAsientos.set(false);
      },
    });
  }

  // --- Paso 2: butacas ---

  isSelected(a: ApiAsiento): boolean {
    return this.selectedSeats().includes(a.id);
  }

  toggleSeat(a: ApiAsiento): void {
    if (a.estado !== 'disponible') return;
    const seats = this.selectedSeats();
    if (seats.includes(a.id)) {
      this.selectedSeats.set(seats.filter((id) => id !== a.id));
    } else if (seats.length < MAX_SEATS) {
      this.selectedSeats.set([...seats, a.id]);
    }
  }

  // --- Paso 3: datos del comprador ---

  private validarDatos(): boolean {
    const errores: Record<string, string> = {};
    if (!this.datos.nombre.trim()) errores['nombre'] = 'Ingresá tu nombre.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.datos.correo.trim())) errores['correo'] = 'Ingresá un correo válido.';
    const tel = this.datos.telefono.replace(/[\s-]/g, '');
    if (!/^\+?\d{8,15}$/.test(tel)) errores['telefono'] = 'Ingresá un teléfono válido (solo números).';
    this.erroresDatos.set(errores);
    return Object.keys(errores).length === 0;
  }

  // --- Navegacion ---

  continueDisabled(): boolean {
    return this.procesando() || (this.step() === 'seats' && this.selectedSeats().length === 0);
  }

  continueLabel(): string {
    if (this.procesando()) return 'Procesando…';
    return this.step() === 'payment' ? `Confirmar compra · ${this.formatPrice(this.total())}` : 'Continuar';
  }

  handleContinue(): void {
    this.error.set('');
    const step = this.step();
    if (step === 'seats') this.step.set('datos');
    else if (step === 'datos') {
      if (this.validarDatos()) this.step.set('payment');
    } else if (step === 'payment') this.confirmPurchase();
  }

  handleBack(): void {
    this.error.set('');
    const step = this.step();
    if (step === 'confirmation') return this.reset();
    const idx = STEP_ORDER.indexOf(step);
    if (idx <= 0) return this.reset();
    this.step.set(STEP_ORDER[idx - 1]);
  }

  goToStep(step: Step): void {
    this.step.set(step);
  }

  setPaymentMethod(method: PaymentMethodKey): void {
    this.paymentMethod.set(method);
  }

  // --- Paso 4: crear la reserva y registrar el pago ---

  confirmPurchase(): void {
    const sel = this.selection();
    if (!sel?.showtime?.funcionId) return;
    this.procesando.set(true);
    this.error.set('');

    const correo = this.datos.correo.trim();
    this.http
      .post<{ id: number }>(`${API_URL}/reservas`, {
        funcion_id: sel.showtime.funcionId,
        asientos: this.selectedSeats(),
        invitado_nombre: this.datos.nombre.trim(),
        invitado_correo: correo,
        invitado_telefono: this.datos.telefono.trim(),
        acepta_marketing: this.datos.acepta_marketing,
      })
      .subscribe({
        next: (reserva) => this.pagar(reserva.id, correo),
        error: (err) => {
          this.procesando.set(false);
          if (err.status === 409) {
            // Alguien se llevo una de las butacas: se recargan para elegir otras.
            this.selectedSeats.set([]);
            this.cargarAsientos(sel.showtime!);
            this.step.set('seats');
          }
          this.error.set(err?.error?.message ?? 'No pudimos crear la reserva. Intentá de nuevo.');
        },
      });
  }

  private pagar(reservaId: number, correo: string): void {
    this.http
      .post<{ reserva: { id: number; codigo_qr: string } }>(`${API_URL}/reservas/${reservaId}/pago`, {
        correo,
        metodo: this.paymentMethod(),
      })
      .subscribe({
        next: (pago) => {
          const codigo = pago.reserva.codigo_qr;
          const qr = qrcode(0, 'M');
          qr.addData(codigo);
          qr.make();
          this.reservaId.set(pago.reserva.id);
          this.codigo.set(codigo);
          this.qrSrc.set(qr.createDataURL(6, 8));
          this.procesando.set(false);
          this.step.set('confirmation');
        },
        error: (err) => {
          this.procesando.set(false);
          this.error.set(err?.error?.message ?? 'No pudimos registrar el pago. Intentá de nuevo.');
        },
      });
  }

  reset(): void {
    this.bookingState.clear();
    this.router.navigateByUrl('/');
  }
}
