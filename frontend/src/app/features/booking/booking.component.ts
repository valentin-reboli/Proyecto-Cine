import { Component, inject, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { BookingStateService } from '../../core/services/booking-state.service';

@Component({
  selector: 'app-booking',
  templateUrl: './booking.component.html',
  styleUrl: './booking.component.css',
})
export class BookingComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly bookingState = inject(BookingStateService);

  readonly selection = this.bookingState.selection;

  ngOnInit(): void {
    if (!this.selection()) {
      this.router.navigateByUrl('/');
    }
  }

  goBack(): void {
    this.bookingState.clear();
    this.router.navigateByUrl('/');
  }
}
