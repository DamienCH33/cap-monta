import { currentLangOption } from '../i18n/lang';
import { Accommodation, typeLabel } from './accommodation';
import { BusyPeriod } from './availability';
import { bedroomCount, peopleCount } from './owner-accommodation';
import { euros } from './stay-terms';

/** Au-delà, le post devient un tableau illisible dans un groupe Facebook. */
const MAX_PERIODS = 6;
const MAX_FREE_WEEKS = 8;

/** « 2027-07-03 » → date UTC, sans fuseau : les calculs se font jour par jour. */
function day(iso: string): Date {
  return new Date(`${iso}T00:00:00Z`);
}

function iso(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function shortDate(value: string): string {
  return new Intl.DateTimeFormat(currentLangOption().tag, {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
  }).format(day(value));
}

/** L'été qui vient : celui de cette année jusqu'en août, le suivant à partir de septembre. */
export function nextSummer(today: string): number {
  const date = day(today);

  return date.getUTCMonth() >= 8 ? date.getUTCFullYear() + 1 : date.getUTCFullYear();
}

/**
 * Les semaines du samedi au samedi entièrement libres, de juin à septembre de l'été qui vient,
 * à partir d'aujourd'hui. Bornes [) comme partout : le samedi de départ d'un séjour est libre.
 */
export function freeSummerWeeks(busy: BusyPeriod[], today: string): string[] {
  const year = nextSummer(today);
  const end = day(`${year}-10-01`);
  const start = day(`${year}-06-01`);
  // Premier samedi à partir du 1er juin (getUTCDay : 6 = samedi).
  start.setUTCDate(start.getUTCDate() + ((6 - start.getUTCDay() + 7) % 7));

  const weeks: string[] = [];
  for (let saturday = start; saturday < end; saturday = new Date(saturday.getTime() + 7 * 864e5)) {
    const arrival = iso(saturday);
    const departure = iso(new Date(saturday.getTime() + 7 * 864e5));
    const taken = busy.some((period) => period.start < departure && arrival < period.end);

    if (arrival >= today && !taken) {
      weeks.push(arrival);
    }
  }

  return weeks;
}

/**
 * Le texte qu'un propriétaire colle dans un groupe Facebook pour annoncer sa location : le
 * logement, ses tarifs à venir, les semaines libres de l'été, et le lien vers sa fiche (marqué
 * utm_source=facebook pour savoir ce que ces posts rapportent). Dans la langue de la page.
 */
export function facebookPost(
  accommodation: Accommodation,
  busy: BusyPeriod[],
  url: string,
  today: string,
): string {
  const resort = 'chm' === accommodation.resort ? 'CHM Montalivet' : 'Euronat';
  const place = accommodation.district ? `${accommodation.district}, ${resort}` : resort;
  const lines: string[] = [
    $localize`:@@owner.listings.fb-heading:${typeLabel(accommodation.type)}:type: à louer, ${place}:place:`,
    [
      peopleCount(accommodation.capacity),
      bedroomCount(accommodation.bedrooms),
      ...(accommodation.surface ? [`${accommodation.surface} m²`] : []),
    ].join(' · '),
    '',
  ];

  const periods = accommodation.pricePeriods
    .filter(
      (period) => period.endDate > today && null !== (period.weeklyPrice ?? period.nightlyPrice),
    )
    .slice(0, MAX_PERIODS);

  if (periods.length > 0) {
    lines.push($localize`:@@owner.listings.fb-rates:Tarifs :`);
    for (const period of periods) {
      const price =
        null !== period.weeklyPrice
          ? $localize`:@@owner.listings.fb-per-week:${euros(period.weeklyPrice)}:price: la semaine`
          : $localize`:@@owner.listings.fb-per-night:${euros(period.nightlyPrice ?? 0)}:price: la nuit`;
      lines.push(
        $localize`:@@owner.listings.fb-period:- du ${shortDate(period.startDate)}:from: au ${shortDate(period.endDate)}:to: : ${price}:price:`,
      );
    }
    lines.push('');
  }

  const weeks = freeSummerWeeks(busy, today);
  const summer = nextSummer(today);
  if (weeks.length > 0) {
    const shown = weeks.slice(0, MAX_FREE_WEEKS).map(shortDate).join(', ');
    const more = weeks.length > MAX_FREE_WEEKS ? '…' : '';
    lines.push(
      $localize`:@@owner.listings.fb-free-weeks:Semaines encore libres l'été ${summer}:year: (arrivée le samedi) : ${shown}:weeks:${more}:more:`,
      '',
    );
  } else {
    lines.push(
      $localize`:@@owner.listings.fb-summer-full:L'été ${summer}:year: est complet : les autres dates libres sont sur le lien.`,
      '',
    );
  }

  lines.push(
    $localize`:@@owner.listings.fb-link:Calendrier, tarifs et demande de réservation :`,
    `${url}?utm_source=facebook`,
  );

  return lines.join('\n');
}
