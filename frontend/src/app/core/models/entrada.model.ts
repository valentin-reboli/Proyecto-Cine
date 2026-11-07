import { AdminFuncion, Butaca } from './admin-cine.model';

// valida: se puede dejar pasar. usada: ya se escaneo antes.
// no_pagada / cancelada: no se puede usar.
export type EstadoAcceso = 'valida' | 'usada' | 'no_pagada' | 'cancelada';

export interface EntradaAsiento {
  id: number;
  precio_unitario: string;
  asiento: Butaca;
}

export interface EntradaCombo {
  id: number;
  nombre: string;
  cantidad: number;
  precio_unitario: string;
}

// Reserva que se lee del QR del comprobante (una reserva puede tener varias butacas).
export interface Entrada {
  id: number;
  codigo_qr: string;
  invitado_nombre: string;
  invitado_correo: string;
  total: string;
  estado: string;
  usada_en: string | null;
  funcion: AdminFuncion;
  reserva_asientos: EntradaAsiento[];
  combos: EntradaCombo[];
  estado_acceso?: EstadoAcceso;
}
