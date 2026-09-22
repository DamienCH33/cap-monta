/**
 * Une demande de réservation telle que l'API la montre au propriétaire (boîte de réception)
 * ou au voyageur (lien de suivi). Prix en centimes, dates "2027-07-17" en bornes [).
 */
export type BookingStatus = 'pending' | 'accepted' | 'declined' | 'cancelled' | 'expired';

interface BookingCommon {
  status: BookingStatus;
  accommodation: { slug: string; title: string };
  start: string;
  end: string;
  nights: number;
  adults: number;
  children: number;
  infants: number;
  pets: number;
  estimatedPrice: number | null;
  agreedPrice: number | null;
  ownerMessage: string | null;
  createdAt: string;
  expiresAt: string;
  respondedAt: string | null;
  /** Le séjour a commencé : plus rien ne s'accepte ni ne s'annule. */
  started: boolean;
}

export interface OwnerBookingRequest extends BookingCommon {
  id: string;
  guestName: string;
  message: string | null;
  /** Arrivée hors de la préférence du samedi. */
  outsideRules: boolean;
  /** Dates prises dans le calendrier depuis l'arrivée de la demande. */
  conflict: boolean;
  /** Email et téléphone du voyageur : seulement une fois la demande acceptée. */
  contact: { email: string; phone: string | null } | null;
}

export interface TrackedBookingRequest extends BookingCommon {
  guestName: string;
  cancellable: boolean;
  ownerContact: { name: string; email: string; phone: string | null } | null;
}

export type InboxTab = 'pending' | 'accepted' | 'history';

const STATUS_LABELS: Record<BookingStatus, string> = {
  pending: 'En attente',
  accepted: 'Acceptée',
  declined: 'Refusée',
  cancelled: 'Annulée',
  expired: 'Expirée',
};

export function bookingStatusLabel(status: BookingStatus): string {
  return STATUS_LABELS[status];
}

/** L'onglet d'une demande : une réservation acceptée dont le séjour est passé va à l'historique. */
export function inboxTab(request: OwnerBookingRequest, today: string): InboxTab {
  if ('pending' === request.status) {
    return 'pending';
  }

  return 'accepted' === request.status && request.end > today ? 'accepted' : 'history';
}

/** « 2 adultes, 2 enfants, 1 bébé, 1 animal ». */
export function travellers(request: BookingCommon): string {
  const parts = [plural(request.adults, 'adulte', 'adultes')];

  if (request.children) {
    parts.push(plural(request.children, 'enfant', 'enfants'));
  }

  if (request.infants) {
    parts.push(plural(request.infants, 'bébé', 'bébés'));
  }

  if (request.pets) {
    parts.push(plural(request.pets, 'animal', 'animaux'));
  }

  return parts.join(', ');
}

/** Le prix qui compte : convenu à l'acceptation, sinon estimé ; null si « à convenir ». */
export function bookingPrice(request: BookingCommon): number | null {
  return request.agreedPrice ?? request.estimatedPrice;
}

/** Heures restantes avant l'expiration, arrondies à l'inférieur ; 0 si le délai est passé. */
export function hoursLeft(expiresAt: string, now = new Date()): number {
  return Math.max(0, Math.floor((new Date(expiresAt).getTime() - now.getTime()) / 3_600_000));
}

/** Les demandes en attente sur des dates qui chevauchent celles-ci, elle exceptée. */
export function competing(
  request: OwnerBookingRequest,
  all: OwnerBookingRequest[],
): OwnerBookingRequest[] {
  return all.filter(
    (other) =>
      other.id !== request.id &&
      'pending' === other.status &&
      other.accommodation.slug === request.accommodation.slug &&
      other.start < request.end &&
      other.end > request.start,
  );
}

function plural(count: number, one: string, many: string): string {
  return `${count} ${count > 1 ? many : one}`;
}
