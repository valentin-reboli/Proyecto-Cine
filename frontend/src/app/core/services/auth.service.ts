import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { API_URL } from './api.config';

const TOKEN_KEY = 'cc_admin_token';

export interface AdminUser {
  id: number;
  name: string;
  email: string;
}

// Si el usuario tiene Google Authenticator, la primera respuesta trae solo requiere_codigo
// y hay que repetir el login mandando el codigo de 6 digitos.
export interface LoginResponse {
  user?: AdminUser;
  token?: string;
  requiere_codigo?: boolean;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  readonly isLoggedIn = signal(this.hasToken());

  private hasToken(): boolean {
    try {
      return !!localStorage.getItem(TOKEN_KEY);
    } catch {
      return false;
    }
  }

  login(email: string, password: string, codigo?: string): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${API_URL}/login`, { email, password, codigo })
      .pipe(tap((res) => this.guardarSesion(res)));
  }

  loginWithGoogle(credential: string, codigo?: string): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${API_URL}/login/google`, { credential, codigo })
      .pipe(tap((res) => this.guardarSesion(res)));
  }

  private guardarSesion(res: LoginResponse): void {
    if (res.token) {
      localStorage.setItem(TOKEN_KEY, res.token);
      this.isLoggedIn.set(true);
    }
  }

  // Usuario dueño del token actual (GET /api/user).
  me(): Observable<AdminUser> {
    return this.http.get<AdminUser>(`${API_URL}/user`);
  }

  logout(): void {
    this.http.post(`${API_URL}/logout`, {}).subscribe({
      complete: () => this.clearSession(),
      error: () => this.clearSession(),
    });
  }

  private clearSession(): void {
    localStorage.removeItem(TOKEN_KEY);
    this.isLoggedIn.set(false);
  }

  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }
}
