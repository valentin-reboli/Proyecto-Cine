import { Routes } from '@angular/router';
import { HomeComponent } from './features/home/home.component';
import { BookingComponent } from './features/booking/booking.component';
import { LoginComponent } from './features/login/login.component';
import { AdminDashboardComponent } from './features/admin-dashboard/admin-dashboard.component';
import { AdminFuncionesComponent } from './features/admin-funciones/admin-funciones.component';
import { AdminCombosComponent } from './features/admin-combos/admin-combos.component';
import { authGuard } from './core/services/auth.guard';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'reserva', component: BookingComponent },
  { path: 'login', component: LoginComponent },
  { path: 'admin', component: AdminDashboardComponent, canActivate: [authGuard] },
  { path: 'admin/funciones', component: AdminFuncionesComponent, canActivate: [authGuard] },
  { path: 'admin/combos', component: AdminCombosComponent, canActivate: [authGuard] },
  {
    // Se carga aparte para no sumar el lector de QR a la pagina publica.
    path: 'admin/escanear',
    loadComponent: () => import('./features/admin-escaner/admin-escaner.component').then((m) => m.AdminEscanerComponent),
    canActivate: [authGuard],
  },
  {
    path: 'admin/configuracion',
    component: AdminDashboardComponent,
    canActivate: [authGuard],
    data: { vista: 'configuracion' },
  },
];
