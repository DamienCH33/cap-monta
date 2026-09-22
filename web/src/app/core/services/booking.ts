import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { TrackedBookingRequest } from '../models/booking-answer';
import { BookingRequest, NewBookingRequest } from '../models/booking-request';
import { Quote } from '../models/quote';

@Injectable({ providedIn: 'root' })
export class BookingService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/api`;

  quote(
    slug: string,
    arrival: string,
    departure: string,
    guests: number,
    pets = 0,
  ): Observable<Quote> {
    let params = new HttpParams()
      .set('arrival', arrival)
      .set('departure', departure)
      .set('guests', guests);

    if (pets > 0) {
      params = params.set('pets', pets);
    }

    return this.http.get<Quote>(`${this.baseUrl}/accommodations/${slug}/quote`, {
      params,
      headers: { Accept: 'application/ld+json' },
    });
  }

  /** La demande vue par le voyageur, grâce à la clé privée reçue par email. */
  track(token: string): Observable<TrackedBookingRequest> {
    return this.http.get<TrackedBookingRequest>(`${this.baseUrl}/booking-requests/track/${token}`);
  }

  cancelTracked(token: string): Observable<TrackedBookingRequest> {
    return this.http.post<TrackedBookingRequest>(
      `${this.baseUrl}/booking-requests/track/${token}/cancel`,
      null,
    );
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
