import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable, switchMap, takeWhile, timer } from 'rxjs';

import { environment } from '../../../environments/environment';
import { AssistantStatus, ListingImportView } from '../models/listing-import';

/** Ce que l'écran renvoie quand le propriétaire valide : voir ImportApplier côté API. */
export interface ApplyPayload {
  /** Un logement existant ; absent : un nouveau brouillon est créé avec « accommodation ». */
  slug?: string;
  accommodation?: Record<string, unknown>;
  periods: unknown[];
  unavailable: unknown[];
}

/**
 * « Importer depuis mon annonce » (lot 4c). La lecture tourne à part côté API (ADR 027) : on
 * envoie le texte, puis on redemande où elle en est toutes les quelques secondes.
 */
@Injectable({ providedIn: 'root' })
export class ListingImportService {
  /** Assez souvent pour paraître réactif, assez peu pour ne rien surcharger. */
  static readonly POLL_MS = 2500;

  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl + '/api/owner/listing-imports';

  assistant(): Observable<AssistantStatus> {
    return this.http.get<AssistantStatus>(`${this.api}/assistant`, { withCredentials: true });
  }

  create(text: string): Observable<ListingImportView> {
    return this.http.post<ListingImportView>(this.api, { text }, { withCredentials: true });
  }

  get(id: string): Observable<ListingImportView> {
    return this.http.get<ListingImportView>(`${this.api}/${id}`, { withCredentials: true });
  }

  /** Redemande jusqu'à ce que la lecture soit terminée ou en échec ; la dernière réponse passe. */
  watch(id: string): Observable<ListingImportView> {
    return timer(0, ListingImportService.POLL_MS).pipe(
      switchMap(() => this.get(id)),
      takeWhile((view) => 'pending' === view.status, true),
    );
  }

  apply(id: string, payload: ApplyPayload): Observable<{ slug: string; created: boolean }> {
    return this.http.post<{ slug: string; created: boolean }>(`${this.api}/${id}/apply`, payload, {
      withCredentials: true,
    });
  }
}
