import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { CatalogService } from '../../core/services/catalog.service';
import { BookingSelection, BookingStateService } from '../../core/services/booking-state.service';

type Step = 'seats' | 'tickets' | 'payment' | 'confirmation';
type ProgressKey = 'funcion' | Step;
type SeatType = 'general' | 'reducida';
type PaymentMethodKey = 'tarjeta' | 'mercadopago';

interface Seat {
  id: string;
  num: number;
  occupied: boolean;
  selected: boolean;
}

interface ProgressStep {
  key: ProgressKey;
  label: string;
  icon: string;
  hasLine: boolean;
  dotBg: string;
  dotFg: string;
  dotBorder: string;
  labelColor: string;
}

const ROWS = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
const SEATS_PER_ROW = 10;
const MAX_SEATS = 8;
const DATE_LABEL = 'HOY 14 AGO';
const STEP_ORDER: Step[] = ['seats', 'tickets', 'payment'];
const STEP_LABELS: Record<Step, string> = {
  seats: 'Butacas',
  tickets: 'Entradas',
  payment: 'Pago',
  confirmation: 'Confirmación',
};

function seededRandomOccupied(seed: string): string[] {
  let s = 0;
  for (let i = 0; i < seed.length; i++) s = (s * 31 + seed.charCodeAt(i)) >>> 0;
  const rand = () => {
    s = (s * 1664525 + 1013904223) >>> 0;
    return s / 4294967295;
  };
  const all: string[] = [];
  ROWS.forEach((r) => {
    for (let i = 1; i <= SEATS_PER_ROW; i++) all.push(r + i);
  });
  const occupied: string[] = [];
  while (occupied.length < 14) {
    const seat = all[Math.floor(rand() * all.length)];
    if (!occupied.includes(seat)) occupied.push(seat);
  }
  return occupied;
}

@Component({
  selector: 'app-booking',
  templateUrl: './booking.component.html',
  styleUrl: './booking.component.css',
})
export class BookingComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly bookingState = inject(BookingStateService);
  private readonly catalog = inject(CatalogService);

  readonly selection = this.bookingState.selection;

  readonly dateLabel = DATE_LABEL;
  readonly priceGeneral = this.catalog.priceGeneral;
  readonly priceReducida = this.catalog.priceReducida;

  readonly paymentMethods: { key: PaymentMethodKey; label: string }[] = [
    { key: 'tarjeta', label: 'Tarjeta de crédito / débito' },
    { key: 'mercadopago', label: 'Mercado Pago' },
  ];

  readonly step = signal<Step>('seats');
  readonly selectedSeats = signal<string[]>([]);
  readonly seatTypes = signal<Record<string, SeatType>>({});
  readonly paymentMethod = signal<PaymentMethodKey>('tarjeta');
  readonly orderNumber = signal<string | null>(null);

  readonly occupiedSeats = computed(() => {
    const sel = this.selection();
    if (!sel) return [];
    return seededRandomOccupied(sel.movie.id + this.dateLabel + this.timeLabel(sel));
  });

  readonly seatRows = computed(() => {
    const occupied = this.occupiedSeats();
    const selected = this.selectedSeats();
    return ROWS.map((row) => ({
      row,
      seats: Array.from({ length: SEATS_PER_ROW }, (_, i): Seat => {
        const id = row + (i + 1);
        return {
          id,
          num: i + 1,
          occupied: occupied.includes(id),
          selected: selected.includes(id),
        };
      }),
    }));
  });

  readonly sortedSelectedSeats = computed(() => [...this.selectedSeats()].sort());

  readonly generalCount = computed(
    () => this.selectedSeats().filter((id) => (this.seatTypes()[id] ?? 'general') === 'general').length,
  );
  readonly reducidaCount = computed(
    () => this.selectedSeats().filter((id) => this.seatTypes()[id] === 'reducida').length,
  );

  readonly total = computed(() =>
    this.selectedSeats().reduce(
      (sum, id) => sum + (this.seatTypes()[id] === 'reducida' ? this.priceReducida : this.priceGeneral),
      0,
    ),
  );

  readonly progressSteps = computed((): ProgressStep[] => {
    const step = this.step();
    const currentIdx = STEP_ORDER.indexOf(step);
    const entries: { key: ProgressKey; label: string }[] = [
      { key: 'funcion', label: 'Función' },
      ...STEP_ORDER.map((k) => ({ key: k as ProgressKey, label: STEP_LABELS[k] })),
      { key: 'confirmation', label: STEP_LABELS.confirmation },
    ];

    return entries.map((entry, i) => {
      let done: boolean;
      let icon: string;
      if (entry.key === 'funcion') {
        done = true;
        icon = '✓';
      } else if (entry.key === 'confirmation') {
        done = step === 'confirmation';
        icon = done ? '✓' : String(STEP_ORDER.length + 1);
      } else {
        const idx = STEP_ORDER.indexOf(entry.key);
        done = idx < currentIdx;
        icon = done ? '✓' : String(idx + 1);
      }
      const current = entry.key === step;

      let dotBg = 'transparent';
      let dotFg = 'var(--text-muted)';
      let dotBorder = 'var(--border-strong)';
      let labelColor = 'var(--text-muted)';
      if (current) {
        dotBg = 'var(--accent)';
        dotFg = 'var(--bg)';
        dotBorder = 'var(--accent)';
        labelColor = 'var(--text-primary)';
      } else if (done) {
        dotBg = 'var(--accent-soft-12)';
        dotFg = 'var(--accent)';
        dotBorder = 'var(--accent)';
        labelColor = 'var(--text-primary)';
      }

      return {
        key: entry.key,
        label: entry.label,
        icon,
        hasLine: i < entries.length - 1,
        dotBg,
        dotFg,
        dotBorder,
        labelColor,
      };
    });
  });

  ngOnInit(): void {
    if (!this.selection()) {
      this.router.navigateByUrl('/');
    }
  }

  timeLabel(sel: BookingSelection): string {
    return sel.showtime.time + ' · ' + this.catalog.langLabel(sel.showtime.lang);
  }

  formatPrice(amount: number): string {
    return this.catalog.formatPrice(amount);
  }

  seatTypeOf(id: string): SeatType {
    return this.seatTypes()[id] ?? 'general';
  }

  toggleSeat(id: string): void {
    if (this.occupiedSeats().includes(id)) return;
    const seats = this.selectedSeats();
    const idx = seats.indexOf(id);
    if (idx > -1) {
      this.selectedSeats.set(seats.filter((s) => s !== id));
      const types = { ...this.seatTypes() };
      delete types[id];
      this.seatTypes.set(types);
    } else {
      if (seats.length >= MAX_SEATS) return;
      this.selectedSeats.set([...seats, id]);
      this.seatTypes.set({ ...this.seatTypes(), [id]: 'general' });
    }
  }

  setSeatType(id: string, type: SeatType): void {
    this.seatTypes.set({ ...this.seatTypes(), [id]: type });
  }

  setPaymentMethod(method: PaymentMethodKey): void {
    this.paymentMethod.set(method);
  }

  goToStep(step: Step): void {
    this.step.set(step);
  }

  continueDisabled(): boolean {
    return this.step() === 'seats' && this.selectedSeats().length === 0;
  }

  continueLabel(): string {
    return this.step() === 'payment' ? 'Confirmar compra' : 'Continuar';
  }

  handleContinue(): void {
    const step = this.step();
    if (step === 'seats') this.step.set('tickets');
    else if (step === 'tickets') this.step.set('payment');
    else if (step === 'payment') this.confirmPurchase();
  }

  handleBack(): void {
    if (this.step() === 'confirmation') {
      this.reset();
      return;
    }
    const idx = STEP_ORDER.indexOf(this.step());
    if (idx <= 0) {
      this.reset();
      return;
    }
    this.step.set(STEP_ORDER[idx - 1]);
  }

  confirmPurchase(): void {
    this.orderNumber.set('CC-' + Math.floor(100000 + Math.random() * 899999));
    this.step.set('confirmation');
  }

  reset(): void {
    this.bookingState.clear();
    this.router.navigateByUrl('/');
  }
}
