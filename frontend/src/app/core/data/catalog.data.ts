import { Lang, Promotion } from '../models/movie.model';

export const LANG_LABELS: Record<Lang, string> = { DOB: 'Doblada', SUB: 'Subtitulada' };

export const PROMOTIONS: Promotion[] = [
  {
    tag: 'Todos los miércoles',
    title: 'Miércoles 2x1',
    description:
      '2x1 en entradas para todas las funciones 2D, comprando con anticipación online.',
  },
  {
    tag: 'Con carnet estudiantil',
    title: 'Combo Estudiante',
    description:
      'Entrada + combo pochoclo mediano y bebida a precio especial mostrando tu carnet.',
  },
  {
    tag: 'Eventos',
    title: 'Cumpleaños',
    description:
      'Festejá tu cumpleaños con una función privada y combos para todo el grupo.',
  },
  {
    tag: 'Eventos',
    title: 'Salida Escolar',
    description: 'Función exclusiva para tu curso, con bebida y comida incluida.',
  },
];
