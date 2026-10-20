import { Showtime } from '../models/movie.model';

export interface Dia {
  fecha: string;
  label: string;
  funciones: Showtime[];
}

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

function ahoraLocal(): string {
  const d = new Date();
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

// Funciones que todavia no empezaron, ordenadas y agrupadas por dia.
export function agruparPorDia(funciones: Showtime[]): Dia[] {
  const ahora = ahoraLocal();
  const porDia = new Map<string, Showtime[]>();
  [...funciones]
    .filter((f) => f.funcionId && `${f.date} ${f.time}` >= ahora)
    .sort((a, b) => `${a.date} ${a.time}`.localeCompare(`${b.date} ${b.time}`))
    .forEach((f) => porDia.set(f.date!, [...(porDia.get(f.date!) ?? []), f]));
  return [...porDia.entries()].map(([fecha, items]) => {
    const [y, m, d] = fecha.split('-').map(Number);
    const dia = DIAS[new Date(y, m - 1, d).getDay()];
    return { fecha, label: `${dia} ${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}`, funciones: items };
  });
}
