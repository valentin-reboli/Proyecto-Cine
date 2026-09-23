import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';

@Component({
  selector: 'app-login',
  imports: [RouterLink, FormsModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.css',
})
export class LoginComponent {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  email = '';
  password = '';
  verPassword = false;

  readonly enviando = signal(false);
  readonly error = signal('');

  toggleVerPassword(): void {
    this.verPassword = !this.verPassword;
  }

  ingresar(): void {
    this.enviando.set(true);
    this.error.set('');

    this.auth.login(this.email, this.password).subscribe({
      next: () => {
        this.enviando.set(false);
        this.router.navigateByUrl('/admin');
      },
      error: () => {
        this.enviando.set(false);
        this.error.set('Email o contraseña incorrectos.');
      },
    });
  }
}
