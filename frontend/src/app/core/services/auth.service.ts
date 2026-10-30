import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';
import { API_URL } from './api.config';

const TOKEN_KEY = 'cc_admin_token';
const ACTIVIDAD_KEY = 'cc_admin_ultima_actividad';

// Igual que en el backend (config/cine.php): 4 horas sin usar el panel cierran la sesion.
export const INACTIVIDAD_MS = 4 * 60 * 60 * 1000;
const EVENTOS_DE_ACTIVIDAD = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'];

export interface AdminUser {
  id: number;
  name: string;
  email: string;
}

export interface LoginResponse {
  user: AdminUser;
  token: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  readonly isLoggedIn = signal(this.hasToken());

  constructor() {
    // Si se vuelve a abrir el panel despues de 4 horas sin uso, la sesion ya no vale.
    if (this.isLoggedIn() && this.inactivo()) this.borrarSesion();
    this.vigilarInactividad();
  }

  private hasToken(): boolean {
    try {
      return !!localStorage.getItem(TOKEN_KEY);
    } catch {
      return false;
    }
  }

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${API_URL}/login`, { email, password })
      .pipe(tap((res) => this.guardarSesion(res)));
  }

  loginWithGoogle(credential: string): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${API_URL}/login/google`, { credential })
      .pipe(tap((res) => this.guardarSesion(res)));
  }

  private guardarSesion(res: LoginResponse): void {
    if (res.token) {
      localStorage.setItem(TOKEN_KEY, res.token);
      localStorage.setItem(ACTIVIDAD_KEY, String(Date.now()));
      this.isLoggedIn.set(true);
    }
  }

  // Usuario dueño del token actual (GET /api/user).
  me(): Observable<AdminUser> {
    return this.http.get<AdminUser>(`${API_URL}/user`);
  }

  private sesionVerificada = false;

  // Al entrar al panel se confirma una vez que el backend sigue aceptando el token
  // (pudo vencer por inactividad). Si no, el interceptor recibe el 401 y lleva al login.
  verificarSesion(): void {
    if (this.sesionVerificada) return;
    this.sesionVerificada = true;
    this.me().subscribe({ error: () => (this.sesionVerificada = false) });
  }

  logout(): void {
    this.http.post(`${API_URL}/logout`, {}).subscribe({
      complete: () => this.clearSession(),
      error: () => this.clearSession(),
    });
  }

  // Ademas de borrar el token, saca al usuario del panel (si no, se quedaba en la misma pantalla).
  private clearSession(): void {
    this.borrarSesion();
    this.router.navigateByUrl('/login');
  }

  // Sesion vencida (4 horas sin uso, o el backend ya no acepta el token): al login con el aviso.
  cerrarPorInactividad(): void {
    if (!this.isLoggedIn()) return;
    this.borrarSesion();
    this.router.navigate(['/login'], { queryParams: { motivo: 'inactividad' } });
  }

  private borrarSesion(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(ACTIVIDAD_KEY);
    this.isLoggedIn.set(false);
  }

  // --- Cierre automatico por inactividad ---

  private vigilarInactividad(): void {
    let ultimoRegistro = 0;
    let ultimoAvisoAlBackend = Date.now();
    // Se guarda como mucho una vez cada 30 segundos (mousemove dispara muchisimos eventos).
    const registrar = () => {
      if (!this.isLoggedIn() || Date.now() - ultimoRegistro < 30_000) return;
      ultimoRegistro = Date.now();
      localStorage.setItem(ACTIVIDAD_KEY, String(ultimoRegistro));
      // El backend solo cuenta requests: si se esta usando la pantalla sin guardar nada
      // (por ej. llenando un formulario largo), cada 10 minutos se le avisa para que no venza el token.
      if (ultimoRegistro - ultimoAvisoAlBackend > 10 * 60_000) {
        ultimoAvisoAlBackend = ultimoRegistro;
        this.me().subscribe({ error: () => {} });
      }
    };
    for (const evento of EVENTOS_DE_ACTIVIDAD) {
      document.addEventListener(evento, registrar, { passive: true });
    }
    // La ultima actividad se guarda en localStorage, asi cuenta tambien lo que se haga en otras pestañas.
    setInterval(() => {
      if (this.isLoggedIn() && this.inactivo()) this.cerrarPorInactividad();
    }, 60_000);
  }

  private inactivo(): boolean {
    const ultima = Number(localStorage.getItem(ACTIVIDAD_KEY));
    // Sesiones de antes de este cambio no tienen registro: se toma como actividad de ahora.
    if (!ultima) {
      localStorage.setItem(ACTIVIDAD_KEY, String(Date.now()));
      return false;
    }
    return Date.now() - ultima > INACTIVIDAD_MS;
  }
}
