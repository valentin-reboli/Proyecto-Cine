// Usuarios que pueden entrar al panel de admin (GET/POST /api/usuarios).
export interface AdminUsuario {
  id: number;
  name: string;
  email: string;
  created_at: string;
}

export interface AdminUsuarioForm {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}
