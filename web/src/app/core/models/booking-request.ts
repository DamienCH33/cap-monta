export interface NewBookingRequest {
  accommodationSlug: string;
  arrival: string;
  departure: string;
  adults: number;
  children: number;
  guestName: string;
  guestEmail: string;
  guestPhone: string | null;
  message: string | null;
}

export interface BookingRequest extends NewBookingRequest {
  id: string;
  status: string;
  estimatedPrice: number | null;
  expiresAt: string;
}
