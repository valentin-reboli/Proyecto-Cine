import { Routes } from '@angular/router';
import { HomeComponent } from './features/home/home.component';
import { BookingComponent } from './features/booking/booking.component';
import { LoginComponent } from './features/login/login.component';
import { AdminDashboardComponent } from './features/admin-dashboard/admin-dashboard.component';
import { authGuard } from './core/services/auth.guard';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'reserva', component: BookingComponent },
  { path: 'login', component: LoginComponent },
  { path: 'admin', component: AdminDashboardComponent, canActivate: [authGuard] },
];
