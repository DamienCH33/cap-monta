/** Une période prise. Le public ne sait pas pourquoi : réservation ou blocage du propriétaire. */
export interface BusyPeriod {
  start: string;
  end: string;
}

export interface Availability {
  slug: string;
  from: string;
  to: string;
  busy: BusyPeriod[];
}
