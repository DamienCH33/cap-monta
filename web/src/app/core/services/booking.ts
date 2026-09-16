import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { BookingRequest, NewBookingRequest } from '../models/booking-request';
import { Quote } from '../models/quote';

@Injectable({ providedIn: 'root' })
export class BookingService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/api`;

  quote(slug: string, arrival: string, departure: string, guests: number): Observable<Quote> {
    const params = new HttpParams()
      .set('arrival', arrival)
      .set('departure', departure)
      .set('guests', guests);

    return this.http.get<Quote>(`${this.baseUrl}/accommodations/${slug}/quote`, {
      params,
      headers: { Accept: 'application/ld+json' },
    });
  }

  send(request: NewBookingRequest): Observable<BookingRequest> {
    return this.http.post<BookingRequest>(`${this.baseUrl}/booking-requests`, request, {
      headers: {
        'Content-Type': 'application/ld+json',
        Accept: 'application/ld+json',
      },
    });
  }
}
