export type Lang = 'DOB' | 'SUB';

export interface Showtime {
  time: string;
  lang: Lang;
}

export interface Movie {
  id: string;
  title: string;
  poster: string;
  genre: string;
  genres: string[];
  duration: number;
  rating: string;
  formats: string[];
  sala: string;
  todayTimes: Showtime[];
}

export interface ComingSoonMovie {
  id: string;
  title: string;
  poster: string;
  genre: string;
  releaseDate: string;
}

export interface Promotion {
  tag: string;
  title: string;
  description: string;
}
