/**
 * Les dates d'un séjour saisies dans un formulaire : "2027-07-03", départ exclu.
 * Une seule règle partout (recherche, demande) : le départ vient après l'arrivée.
 */

/** "2027-07-03" + n jours, en date locale (pas d'UTC : pas de décalage d'un jour). */
export function plusDays(iso: string, days: number): string {
  const [year, month, day] = iso.split('-').map(Number);
  const date = new Date(year, month - 1, day + days);

  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function today(): string {
  const now = new Date();

  return plusDays(`${now.getFullYear()}-${now.getMonth() + 1}-${now.getDate()}`, 0);
}

/**
 * Le départ qui va avec cette arrivée : celui choisi s'il vient après, sinon une semaine
 * plus tard (la durée la plus courante au CHM). Évite d'envoyer un séjour à l'envers.
 */
export function departureAfter(arrival: string, departure: string): string {
  if ('' === arrival) {
    return departure;
  }

  return '' !== departure && departure > arrival ? departure : plusDays(arrival, 7);
}

/** Deux dates saisies, le départ avant ou le jour même de l'arrivée. */
export function isReversed(arrival: string | undefined, departure: string | undefined): boolean {
  return !!arrival && !!departure && departure <= arrival;
}
