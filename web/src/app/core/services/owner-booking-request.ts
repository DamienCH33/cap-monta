import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { OwnerBookingRequest } from '../models/booking-answer';

/**
 * La boîte de réception du propriétaire. Chaque réponse renvoie toute la boîte : accepter
 * une demande peut en refuser d'autres sur les mêmes dates, l'écran doit le montrer aussitôt.
 */
@Injectable({ providedIn: 'root' })
export class OwnerBookingRequestService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api/owner/booking-requests';

  list(): Observable<OwnerBookingRequest[]> {
    return this.http.get<OwnerBookingRequest[]>(this.api, { withCredentials: true });
  }

  /** @param price en centimes ; null pour garder le prix estimé. */
  accept(id: string, price: number | null, message: string): Observable<OwnerBookingRequest[]> {
    return this.answer(id, 'accept', { price, message: message.trim() || null });
  }

  decline(id: string, message: string): Observable<OwnerBookingRequest[]> {
    return this.answer(id, 'decline', { message: message.trim() || null });
  }

  cancel(id: string, message: string): Observable<OwnerBookingRequest[]> {
    return this.answer(id, 'cancel', { message: message.trim() || null });
  }

  private answer(id: string, action: string, body: object): Observable<OwnerBookingRequest[]> {
    return this.http.post<OwnerBookingRequest[]>(`${this.api}/${id}/${action}`, body, {
      withCredentials: true,
    });
  }
}
