/** Le propriétaire connecté, tel que /api/login et /api/owner/me le renvoient. */
export interface Owner {
  email: string;
  displayName: string;
  phone: string | null;
  /** Faux tant que l'adresse email n'a pas été confirmée : il ne peut pas publier. */
  verified: boolean;
}
