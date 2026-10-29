import { AfterViewInit, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
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
  // Llega con ?motivo=inactividad cuando la sesion se cerro sola.
  readonly aviso = signal(
    inject(ActivatedRoute).snapshot.queryParamMap.get('motivo') === 'inactividad'
      ? 'Tu sesión se cerró por inactividad. Volvé a ingresar.'
      : '',
  );

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
    this.aviso.set('');

    this.auth.login(this.email, this.password).subscribe({
      next: () => this.entrarAlPanel(),
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
    this.aviso.set('');

    this.auth.loginWithGoogle(credential).subscribe({
      next: () => this.entrarAlPanel(),
      error: (err) => {
        this.enviando.set(false);
        // El backend explica el motivo (token invalido, otra app, email sin cuenta de admin).
        this.error.set(err?.error?.message || 'No se pudo iniciar sesion con esa cuenta de Google.');
      },
    });
  }

  private entrarAlPanel(): void {
    this.enviando.set(false);
    this.router.navigateByUrl('/admin');
  }
}
