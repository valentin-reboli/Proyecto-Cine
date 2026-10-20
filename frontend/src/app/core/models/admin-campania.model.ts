export type CanalCampania = 'email' | 'whatsapp' | 'telegram';
export type EstadoCampania = 'borrador' | 'programada' | 'enviada' | 'error';

export interface Campania {
  id: number;
  nombre: string;
  canal: CanalCampania;
  asunto: string | null;
  mensaje: string;
  programada_para: string | null;
  estado: EstadoCampania;
  destinatarios: number;
  enviada_en: string | null;
  error: string | null;
}

export interface CampaniaPayload {
  nombre: string;
  canal: CanalCampania;
  asunto: string | null;
  mensaje: string;
  programada_para: string | null;
}

export interface Cliente {
  id: number;
  nombre: string;
  correo: string;
  telefono: string | null;
  acepta_marketing: boolean;
  created_at: string;
}
