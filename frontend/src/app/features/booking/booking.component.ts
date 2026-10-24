import { Component, OnDestroy, OnInit, computed, effect, inject, signal, untracked } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import qrcode from 'qrcode-generator';
import { API_URL } from '../../core/services/api.config';
import { ComboService } from '../../core/services/combo.service';
import { Combo } from '../../core/models/combo.model';
import { CatalogService } from '../../core/services/catalog.service';
import { BookingStateService } from '../../core/services/booking-state.service';
import { Movie, Showtime } from '../../core/models/movie.model';
import { Dia, agruparPorDia } from '../../core/utils/funciones';

type Step = 'pelicula' | 'funcion' | 'seats' | 'combos' | 'datos' | 'payment' | 'confirmation';
type PaymentMethodKey = 'tarjeta' | 'mercadopago';

interface ApiAsiento {
  id: number;
  fila: string;
  numero: number;
  estado: 'disponible' | 'temporal' | 'ocupado' | 'bloqueado';
}

// Butacas bloqueadas para este cliente mientras completa la compra.
interface Bloqueo {
  id: number;
  token: string;
  asientos: number[];
  segundos_restantes: number;
}

interface DescuentoRegalo {
  codigo: string;
  porcentaje: number;
  vence_en: string;
}

interface ReservaVerificada {
  id: number;
  estado: string;
  codigo_qr: string | null;
  comprobante_enviado_en: string | null;
  descuento_generado?: DescuentoRegalo | null;
  pagos?: { estado: string }[];
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
const MINUTOS_BLOQUEO = 8;
const STEP_ORDER: Step[] = ['pelicula', 'funcion', 'seats', 'combos', 'datos', 'payment'];
const STEP_LABELS: Record<Step, string> = {
  pelicula: 'Película',
  funcion: 'Función',
  seats: 'Butacas',
  combos: 'Combos',
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
export class BookingComponent implements OnInit, OnDestroy {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly http = inject(HttpClient);
  private readonly bookingState = inject(BookingStateService);
  private readonly catalog = inject(CatalogService);
  private readonly comboService = inject(ComboService);

  readonly selection = this.bookingState.selection;

  // Peliculas en cartelera que tienen funciones por venir.
  readonly cargandoCatalogo = this.catalog.loading;
  readonly peliculas = computed(() =>
    this.catalog.movies().filter((m) => agruparPorDia(m.todayTimes).length > 0),
  );

  readonly paymentMethods: { key: PaymentMethodKey; label: string; icon: string; detalle: string }[] = [
    { key: 'tarjeta', label: 'Tarjeta de crédito / débito', icon: '💳', detalle: 'Visa, Mastercard, Amex' },
    { key: 'mercadopago', label: 'Mercado Pago', icon: '📱', detalle: 'Dinero en cuenta o tarjetas guardadas' },
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
  readonly comprobanteEnviado = signal(false);

  // Bloqueo de butacas: al elegirlas quedan reservadas MINUTOS_BLOQUEO minutos.
  readonly minutosBloqueo = MINUTOS_BLOQUEO;
  readonly bloqueo = signal<Bloqueo | null>(null);
  readonly segundosRestantes = signal(0);
  readonly tiempoRestante = computed(() => {
    const s = this.segundosRestantes();
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
  });
  private vence = 0;
  private reloj: ReturnType<typeof setInterval> | null = null;

  // Codigo de descuento (el que se regala con la compra anterior)
  readonly descuento = signal<{ codigo: string; porcentaje: number } | null>(null);
  readonly validandoCodigo = signal(false);
  readonly errorCodigo = signal('');
  readonly regalo = signal<DescuentoRegalo | null>(null);
  codigoInput = '';

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
  // Combos / snacks
  readonly combosDisponibles = signal<Combo[]>([]);
  readonly cantidades = signal<Record<number, number>>({});
  readonly combosElegidos = computed(() =>
    this.combosDisponibles()
      .map((c) => ({ combo: c, cantidad: this.cantidades()[c.id] ?? 0 }))
      .filter((x) => x.cantidad > 0),
  );
  readonly combosTotal = computed(() =>
    this.combosElegidos().reduce((sum, x) => sum + Number(x.combo.precio) * x.cantidad, 0),
  );
  readonly combosCantidad = computed(() => this.combosElegidos().reduce((sum, x) => sum + x.cantidad, 0));

  // Entradas (con el descuento, que aplica solo a las entradas)
  readonly subtotal = computed(() => this.price() * this.selectedSeats().length);
  readonly descuentoMonto = computed(() => {
    const d = this.descuento();
    return d ? Math.round((this.subtotal() * d.porcentaje) / 100) : 0;
  });
  readonly entradasTotal = computed(() => this.subtotal() - this.descuentoMonto());
  readonly total = computed(() => this.entradasTotal() + this.combosTotal());

  // Si no hay combos activos, el paso de combos no aparece.
  readonly pasos = computed((): Step[] =>
    this.combosDisponibles().length ? STEP_ORDER : STEP_ORDER.filter((s) => s !== 'combos'),
  );

  readonly progressSteps = computed((): ProgressStep[] => {
    const step = this.step();
    const pasos = this.pasos();
    const currentIdx = step === 'confirmation' ? pasos.length : pasos.indexOf(step);
    const all: Step[] = [...pasos, 'confirmation'];
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

  ngOnDestroy(): void {
    this.liberarBloqueo();
  }

  // Pelicula/funcion pedidas por URL (?pelicula=ID&funcion=ID) desde el detalle de la cartelera.
  private readonly pedidaPorUrl = signal<{ pelicula: string; funcion: number | null } | null>(null);

  // El catalogo carga asincrono: cuando aparece la pelicula pedida, se salta directo a sus funciones.
  private readonly abrirPedidaPorUrl = effect(() => {
    const pedida = this.pedidaPorUrl();
    if (!pedida) return;
    const movie = this.peliculas().find((m) => m.id === pedida.pelicula);
    if (!movie) return;
    untracked(() => {
      this.pedidaPorUrl.set(null);
      this.elegirPelicula(movie);
      const f = movie.todayTimes.find((t) => t.funcionId === pedida.funcion);
      if (f) this.elegirFuncion(f);
    });
  });

  ngOnInit(): void {
    // Sin parametros se arranca eligiendo la pelicula.
    this.bookingState.clear();
    const params = this.route.snapshot.queryParamMap;
    const pelicula = params.get('pelicula');
    if (pelicula) {
      const funcion = Number(params.get('funcion'));
      this.pedidaPorUrl.set({ pelicula, funcion: Number.isFinite(funcion) && funcion > 0 ? funcion : null });
    }
    this.comboService.listActivos().subscribe({ next: (c) => this.combosDisponibles.set(c), error: () => {} });
  }

  langLabel(f: Showtime): string {
    return this.catalog.langLabel(f.lang);
  }

  formatPrice(amount: number): string {
    return this.catalog.formatPrice(amount);
  }

  fechaCorta(iso: string): string {
    const [y, m, d] = iso.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
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
    this.liberarBloqueo();
    this.bookingState.select(sel.movie, f);
    this.selectedSeats.set([]);
    this.cantidades.set({});
    this.error.set('');
    this.cargarAsientos(f);
    this.step.set('seats');
  }

  private cargarAsientos(f: Showtime, depurarSeleccion = false): void {
    this.cargandoAsientos.set(true);
    this.asientos.set([]);
    this.http.get<ApiAsiento[]>(`${API_URL}/funciones/${f.funcionId}/asientos`).subscribe({
      next: (asientos) => {
        this.asientos.set(asientos);
        if (depurarSeleccion) {
          const libres = asientos.filter((a) => this.seleccionable(a)).map((a) => a.id);
          this.selectedSeats.set(this.selectedSeats().filter((id) => libres.includes(id)));
        }
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

  // Las butacas que bloqueo este mismo cliente figuran como temporales, pero las puede seguir tocando.
  seleccionable(a: ApiAsiento): boolean {
    return a.estado === 'disponible' || !!this.bloqueo()?.asientos.includes(a.id);
  }

  toggleSeat(a: ApiAsiento): void {
    if (!this.seleccionable(a)) return;
    const seats = this.selectedSeats();
    if (seats.includes(a.id)) {
      this.selectedSeats.set(seats.filter((id) => id !== a.id));
    } else if (seats.length < MAX_SEATS) {
      this.selectedSeats.set([...seats, a.id]);
    }
  }

  // --- Paso 3: combos ---

  cantidadDe(c: Combo): number {
    return this.cantidades()[c.id] ?? 0;
  }

  cambiarCantidad(c: Combo, delta: number): void {
    const nueva = Math.max(0, Math.min(20, this.cantidadDe(c) + delta));
    this.cantidades.set({ ...this.cantidades(), [c.id]: nueva });
  }

  private siguienteDe(step: Step): Step {
    const pasos = this.pasos();
    return pasos[pasos.indexOf(step) + 1];
  }

  // --- Paso 4: datos del comprador ---

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
    if (this.procesando()) return this.step() === 'seats' ? 'Reservando tus butacas…' : 'Procesando y verificando el pago…';
    return this.step() === 'payment' ? `Confirmar compra · ${this.formatPrice(this.total())}` : 'Continuar';
  }

  handleContinue(): void {
    this.error.set('');
    const step = this.step();
    if (step === 'seats') this.bloquearButacas();
    else if (step === 'combos') this.step.set(this.siguienteDe(step));
    else if (step === 'datos') {
      if (this.validarDatos()) this.step.set('payment');
    } else if (step === 'payment') this.confirmPurchase();
  }

  handleBack(): void {
    this.error.set('');
    const step = this.step();
    if (step === 'confirmation') return this.reset();
    const pasos = this.pasos();
    const idx = pasos.indexOf(step);
    if (idx <= 0) return this.reset();
    const anterior = pasos[idx - 1];
    // Si vuelve a elegir funcion o pelicula, las butacas dejan de estar bloqueadas.
    if (anterior === 'funcion' || anterior === 'pelicula') this.liberarBloqueo();
    this.step.set(anterior);
  }

  goToStep(step: Step): void {
    this.step.set(step);
  }

  // --- Bloqueo de butacas ---

  private mismasButacas(a: number[], b: number[]): boolean {
    return a.length === b.length && a.every((id) => b.includes(id));
  }

  // Al continuar desde el mapa, las butacas quedan bloqueadas para los demas clientes.
  // Si ya habia un bloqueo y se cambiaron las butacas, se reemplaza (el tiempo sigue corriendo).
  private bloquearButacas(): void {
    const sel = this.selection();
    const seats = this.selectedSeats();
    if (!sel?.showtime?.funcionId || seats.length === 0) return;

    const actual = this.bloqueo();
    if (actual && this.mismasButacas(actual.asientos, seats)) {
      this.step.set(this.siguienteDe('seats'));
      return;
    }

    this.procesando.set(true);
    this.http
      .post<Bloqueo>(`${API_URL}/reservas/bloquear`, {
        funcion_id: sel.showtime.funcionId,
        asientos: seats,
        bloqueo_id: actual?.id ?? null,
        bloqueo_token: actual?.token ?? null,
      })
      .subscribe({
        next: (bloqueo) => {
          this.procesando.set(false);
          this.bloqueo.set(bloqueo);
          this.iniciarReloj(bloqueo.segundos_restantes);
          this.step.set(this.siguienteDe('seats'));
        },
        error: (err) => {
          this.procesando.set(false);
          if (err.status === 410 || err.status === 403) return this.tiempoAgotado();
          if (err.status === 409) {
            // Otro cliente se llevo alguna: se recarga el mapa y se sacan las que ya no estan libres.
            this.cargarAsientos(sel.showtime!, true);
          }
          this.error.set(err?.error?.message ?? 'No pudimos reservar las butacas. Intentá de nuevo.');
        },
      });
  }

  private iniciarReloj(segundos: number): void {
    this.detenerReloj();
    this.vence = Date.now() + segundos * 1000;
    this.actualizarReloj();
    this.reloj = setInterval(() => this.actualizarReloj(), 1000);
  }

  private actualizarReloj(): void {
    const quedan = Math.max(0, Math.ceil((this.vence - Date.now()) / 1000));
    this.segundosRestantes.set(quedan);
    // Si justo se esta pagando, se deja que el servidor decida.
    if (quedan === 0 && !this.procesando()) this.tiempoAgotado();
  }

  private detenerReloj(): void {
    if (this.reloj) clearInterval(this.reloj);
    this.reloj = null;
  }

  // Se termino el tiempo sin comprar: se liberan las butacas y vuelve al mapa.
  private tiempoAgotado(): void {
    this.liberarBloqueo();
    this.selectedSeats.set([]);
    const f = this.selection()?.showtime;
    if (f) {
      this.cargarAsientos(f);
      this.step.set('seats');
    }
    this.error.set(
      `Se terminó el tiempo de ${MINUTOS_BLOQUEO} minutos para completar la compra y las butacas se liberaron. Elegilas de nuevo.`,
    );
  }

  private liberarBloqueo(): void {
    this.detenerReloj();
    const bloqueo = this.bloqueo();
    if (!bloqueo) return;
    this.bloqueo.set(null);
    this.http.post(`${API_URL}/reservas/${bloqueo.id}/liberar`, { token: bloqueo.token }).subscribe({ error: () => {} });
  }

  aplicarCodigo(): void {
    const codigo = this.codigoInput.trim();
    if (!codigo) return;
    this.validandoCodigo.set(true);
    this.errorCodigo.set('');
    this.http
      .post<{ codigo: string; porcentaje: number }>(`${API_URL}/descuentos/validar`, {
        codigo,
        correo: this.datos.correo.trim(),
      })
      .subscribe({
        next: (d) => {
          this.descuento.set(d);
          this.validandoCodigo.set(false);
        },
        error: (err) => {
          this.errorCodigo.set(err?.error?.message ?? 'No pudimos validar el código.');
          this.validandoCodigo.set(false);
        },
      });
  }

  quitarCodigo(): void {
    this.descuento.set(null);
    this.codigoInput = '';
    this.errorCodigo.set('');
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
    const bloqueo = this.bloqueo();
    this.http
      .post<{ id: number }>(`${API_URL}/reservas`, {
        funcion_id: sel.showtime.funcionId,
        asientos: this.selectedSeats(),
        invitado_nombre: this.datos.nombre.trim(),
        invitado_correo: correo,
        invitado_telefono: this.datos.telefono.trim(),
        acepta_marketing: this.datos.acepta_marketing,
        codigo_descuento: this.descuento()?.codigo ?? null,
        combos: this.combosElegidos().map((x) => ({ combo_id: x.combo.id, cantidad: x.cantidad })),
        bloqueo_id: bloqueo?.id ?? null,
        bloqueo_token: bloqueo?.token ?? null,
      })
      .subscribe({
        next: (reserva) => this.pagar(reserva.id, correo),
        error: (err) => {
          this.procesando.set(false);
          if (err.status === 410) return this.tiempoAgotado();
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
      .post(`${API_URL}/reservas/${reservaId}/pago`, { correo, metodo: this.paymentMethod() })
      .subscribe({
        next: () => {
          // Comprada: las butacas quedan ocupadas, ya no hay que liberarlas.
          this.detenerReloj();
          this.bloqueo.set(null);
          this.verificarPago(reservaId, correo);
        },
        error: (err) => {
          this.procesando.set(false);
          if (err.status === 410) return this.tiempoAgotado();
          this.error.set(err?.error?.message ?? 'No pudimos registrar el pago. Intentá de nuevo.');
        },
      });
  }

  // Antes de confirmar la compra se vuelve a consultar la reserva: tiene que figurar
  // pagada, con un pago aprobado y su codigo QR emitido.
  private verificarPago(reservaId: number, correo: string): void {
    this.http
      .get<ReservaVerificada>(`${API_URL}/reservas/${reservaId}`, { params: { correo } })
      .subscribe({
        next: (reserva) => {
          const aprobado = reserva.pagos?.some((p) => p.estado === 'aprobado');
          if (reserva.estado !== 'pagada' || !aprobado || !reserva.codigo_qr) {
            this.procesando.set(false);
            this.error.set(
              `No pudimos confirmar el pago de la reserva Nº ${reservaId}. Si te lo descontaron, escribinos con ese número.`,
            );
            return;
          }
          const qr = qrcode(0, 'M');
          qr.addData(reserva.codigo_qr);
          qr.make();
          this.reservaId.set(reserva.id);
          this.codigo.set(reserva.codigo_qr);
          this.qrSrc.set(qr.createDataURL(6, 8));
          this.comprobanteEnviado.set(!!reserva.comprobante_enviado_en);
          this.regalo.set(reserva.descuento_generado ?? null);
          this.procesando.set(false);
          this.step.set('confirmation');
        },
        error: () => {
          this.procesando.set(false);
          this.error.set(
            `No pudimos verificar el pago de la reserva Nº ${reservaId}. No lo repitas: escribinos con ese número.`,
          );
        },
      });
  }

  reset(): void {
    this.liberarBloqueo();
    this.bookingState.clear();
    this.router.navigateByUrl('/');
  }
}
