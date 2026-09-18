import { ComingSoonMovie, Lang, Movie, Promotion } from '../models/movie.model';

export const LANG_LABELS: Record<Lang, string> = { DOB: 'Doblada', SUB: 'Subtitulada' };

export const MOVIES: Movie[] = [
  {
    id: 'spiderman',
    title: 'Spider-Man: Un Nuevo Día',
    poster: '/img/spidermanposter.jpg',
    genre: 'Acción',
    genres: ['Acción', 'Aventura'],
    duration: 130,
    rating: 'ATP',
    formats: ['2D', '3D', 'ATMOS'],
    sala: 'Sala 1',
    todayTimes: [
      { time: '13:30', lang: 'DOB' },
      { time: '16:15', lang: 'DOB' },
      { time: '19:00', lang: 'SUB' },
      { time: '21:45', lang: 'SUB' },
    ],
  },
  {
    id: 'odisea',
    title: 'La Odisea',
    poster: '/img/laodisea.jpg',
    genre: 'Aventura',
    genres: ['Aventura', 'Drama'],
    duration: 122,
    rating: 'ATP',
    formats: ['2D'],
    sala: 'Sala 2',
    todayTimes: [
      { time: '14:00', lang: 'SUB' },
      { time: '17:30', lang: 'SUB' },
      { time: '20:45', lang: 'SUB' },
    ],
  },
  {
    id: 'calleoak',
    title: 'El Final de la Calle Oak',
    poster: '/img/finaldelacalleoak.jpg',
    genre: 'Ciencia ficción',
    genres: ['Ciencia ficción'],
    duration: 100,
    rating: '+13',
    formats: ['2D'],
    sala: 'Sala 3',
    todayTimes: [
      { time: '15:00', lang: 'SUB' },
      { time: '18:20', lang: 'DOB' },
      { time: '22:00', lang: 'SUB' },
    ],
  },
  {
    id: 'insaciable',
    title: 'Insaciable',
    poster: '/img/insaciable.jpg',
    genre: 'Terror',
    genres: ['Terror'],
    duration: 95,
    rating: '+16',
    formats: ['2D'],
    sala: 'Sala 2',
    todayTimes: [
      { time: '16:40', lang: 'SUB' },
      { time: '19:15', lang: 'SUB' },
      { time: '23:00', lang: 'SUB' },
    ],
  },
  {
    id: 'pawpatrol',
    title: 'Paw Patrol: La Dino Película',
    poster: '/img/pawpatrol.jpg',
    genre: 'Animación',
    genres: ['Animación', 'Infantil'],
    duration: 107,
    rating: 'ATP',
    formats: ['2D', '3D'],
    sala: 'Sala 1',
    todayTimes: [
      { time: '12:30', lang: 'DOB' },
      { time: '14:45', lang: 'DOB' },
      { time: '17:00', lang: 'DOB' },
    ],
  },
  {
    id: 'toystory5',
    title: 'Toy Story 5',
    poster: '/img/toystory5.jpg',
    genre: 'Animación',
    genres: ['Animación', 'Infantil'],
    duration: 100,
    rating: 'ATP',
    formats: ['2D', '3D'],
    sala: 'Sala 3',
    todayTimes: [
      { time: '13:00', lang: 'DOB' },
      { time: '15:20', lang: 'DOB' },
      { time: '18:00', lang: 'DOB' },
      { time: '20:30', lang: 'SUB' },
    ],
  },
  {
    id: 'moana',
    title: 'Moana',
    poster: '/img/moana.jpg',
    genre: 'Animación',
    genres: ['Animación', 'Infantil'],
    duration: 107,
    rating: 'ATP',
    formats: ['2D'],
    sala: 'Sala 2',
    todayTimes: [
      { time: '12:00', lang: 'DOB' },
      { time: '14:30', lang: 'DOB' },
    ],
  },
  {
    id: 'minions',
    title: 'Minions & Monstruos',
    poster: '/img/minions.jpg',
    genre: 'Animación',
    genres: ['Animación', 'Infantil'],
    duration: 92,
    rating: 'ATP',
    formats: ['2D'],
    sala: 'Sala 1',
    todayTimes: [
      { time: '13:45', lang: 'DOB' },
      { time: '16:00', lang: 'DOB' },
      { time: '18:15', lang: 'SUB' },
    ],
  },
];

export const COMING_SOON: ComingSoonMovie[] = [
  {
    id: 'demonio',
    title: 'La Noche del Demonio: Están Entre Nosotros',
    poster: '/img/nochedeldemonio.jpg',
    genre: 'Terror',
    releaseDate: '20 AGO',
  },
  {
    id: 'alas',
    title: 'Esa Cosa Con Alas',
    poster: '/img/esacosaconalas.jpg',
    genre: 'Drama',
    releaseDate: '20 AGO',
  },
  {
    id: 'unanoche',
    title: 'Sólo Por Una Noche',
    poster: '/img/unanoche.jpg',
    genre: 'Romance',
    releaseDate: '27 AGO',
  },
];

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

export const PRICE_GENERAL = 7200;
export const PRICE_REDUCIDA = 5400;
