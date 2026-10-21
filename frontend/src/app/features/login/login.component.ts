import { AfterViewInit, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { AuthService, LoginResponse } from '../../core/services/auth.service';
import { environment } from '../../../environments/environment';

declare const google: any;

@Component({
  selector: 'app-login',
  imports: [RouterLink, FormsModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.css',
})
export class LoginComponent implements AfterViewInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  email = '';
  password = '';
  verPassword = false;

  readonly enviando = signal(false);
  readonly error = signal('');

  // Segundo paso (Google Authenticator): se repite el mismo login sumando el codigo.
  readonly pideCodigo = signal(false);
  codigo = '';
  private reintentar: ((codigo: string) => Observable<LoginResponse>) | null = null;

  ngAfterViewInit(): void {
    this.loadGoogleScript(() => this.setupGoogleButton());
  }

  private loadGoogleScript(onReady: () => void): void {
    if (typeof google !== 'undefined') {
      onReady();
      return;
    }

    const existing = document.getElementById('google-identity-script');
    if (existing) {
      existing.addEventListener('load', onReady);
      return;
    }

    const script = document.createElement('script');
    script.id = 'google-identity-script';
    script.src = 'https://accounts.google.com/gsi/client';
    script.async = true;
    script.defer = true;
    script.onload = onReady;
    document.head.appendChild(script);
  }

  private setupGoogleButton(): void {
    google.accounts.id.initialize({
      client_id: environment.googleClientId,
      callback: (response: { credential: string }) => this.ingresarConGoogle(response.credential),
    });

    const container = document.getElementById('google-btn');
    if (container) {
      google.accounts.id.renderButton(container, {
        theme: 'outline',
        size: 'large',
        width: 320,
        text: 'signin_with',
      });
    }
  }

  toggleVerPassword(): void {
    this.verPassword = !this.verPassword;
  }

  ingresar(): void {
    this.enviando.set(true);
    this.error.set('');

    const email = this.email;
    const password = this.password;

    this.auth.login(email, password).subscribe({
      next: (res) => this.despuesDelLogin(res, (codigo) => this.auth.login(email, password, codigo)),
      error: (err) => {
        this.enviando.set(false);
        // 422 = credenciales mal; cualquier otro error es del servidor o de la conexion
        // (el codigo ayuda a diagnosticar; el detalle esta en /api/estado).
        this.error.set(
          err?.status === 422
            ? 'Email o contraseña incorrectos.'
            : `No se pudo conectar con el servidor (error ${err?.status || 'de red'}). Probá de nuevo en un rato.`,
        );
      },
    });
  }

  ingresarConGoogle(credential: string): void {
    this.enviando.set(true);
    this.error.set('');

    this.auth.loginWithGoogle(credential).subscribe({
      next: (res) => this.despuesDelLogin(res, (codigo) => this.auth.loginWithGoogle(credential, codigo)),
      error: (err) => {
        this.enviando.set(false);
        // El backend explica el motivo (token invalido, otra app, email sin cuenta de admin).
        this.error.set(err?.error?.message || 'No se pudo iniciar sesion con esa cuenta de Google.');
      },
    });
  }

  private despuesDelLogin(res: LoginResponse, reintentar: (codigo: string) => Observable<LoginResponse>): void {
    this.enviando.set(false);

    if (res.requiere_codigo) {
      this.reintentar = reintentar;
      this.codigo = '';
      this.pideCodigo.set(true);
      return;
    }

    this.router.navigateByUrl('/admin');
  }

  verificarCodigo(): void {
    const codigo = this.codigo.replace(/\s+/g, '');

    if (!this.reintentar || !/^\d{6}$/.test(codigo)) {
      this.error.set('Ingresá los 6 números que muestra Google Authenticator.');
      return;
    }

    this.enviando.set(true);
    this.error.set('');

    this.reintentar(codigo).subscribe({
      next: () => {
        this.enviando.set(false);
        this.router.navigateByUrl('/admin');
      },
      error: (err) => {
        this.enviando.set(false);
        this.error.set(
          err?.error?.errors?.codigo?.[0] ??
            (err?.status === 429
              ? 'Demasiados intentos. Esperá un minuto y probá de nuevo.'
              : 'No se pudo verificar el código. Probá de nuevo.'),
        );
      },
    });
  }

  volverAlLogin(): void {
    this.pideCodigo.set(false);
    this.reintentar = null;
    this.codigo = '';
    this.error.set('');
  }
}
