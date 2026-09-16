export interface BusyPeriod {
  start: string;
  end: string;
  source: 'booking' | 'block' | 'import' | 'ical';
}

export interface Availability {
  slug: string;
  from: string;
  to: string;
  busy: BusyPeriod[];
}
