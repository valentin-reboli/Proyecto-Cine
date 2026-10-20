export type CategoriaCombo = 'combo' | 'pochoclo' | 'bebida' | 'golosina';

export interface Combo {
  id: number;
  nombre: string;
  categoria: CategoriaCombo;
  descripcion: string | null;
  precio: string | number;
  imagen_url: string | null;
  activo: boolean;
}

export type ComboPayload = Omit<Combo, 'id'>;

export const CATEGORIAS_COMBO: { value: CategoriaCombo; label: string }[] = [
  { value: 'combo', label: 'Combo' },
  { value: 'pochoclo', label: 'Pochoclos' },
  { value: 'bebida', label: 'Bebida' },
  { value: 'golosina', label: 'Golosina' },
];
