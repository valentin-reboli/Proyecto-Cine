import { Routes } from '@angular/router';
import { HomeComponent } from './features/home/home.component';
import { BookingComponent } from './features/booking/booking.component';
import { LoginComponent } from './features/login/login.component';

export const routes: Routes = [
  { path: '', component: HomeComponent },
  { path: 'reserva', component: BookingComponent },
  { path: 'login', component: LoginComponent },
];
