// Usuarios que pueden entrar al panel de admin (GET/POST /api/usuarios).
export interface AdminUsuario {
  id: number;
  name: string;
  email: string;
  created_at: string;
  // Google Authenticator activado (el login le pide el codigo de 6 digitos)
  dos_pasos: boolean;
}

// Respuesta de POST /api/usuarios/{id}/dos-pasos: QR para escanear con la app
export interface DosPasosQr {
  secreto: string;
  otpauth_url: string;
  qr: string;
}

export interface AdminUsuarioForm {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export type CambioPasswordForm = Pick<AdminUsuarioForm, 'password' | 'password_confirmation'>;
