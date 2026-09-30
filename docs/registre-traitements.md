# Registre des activités de traitement — Cap Monta

Article 30 du RGPD, d'après le modèle de registre simplifié de la CNIL. À tenir à jour à chaque
nouvelle donnée collectée, nouveau prestataire ou nouvelle durée. La politique de confidentialité
publique (`/confidentialite`) doit dire la même chose que ce registre.

| | |
|---|---|
| Responsable du traitement | Damien Chauveau, éditeur du site, à titre non professionnel |
| Contact | contact@damienchauveau-dev.fr |
| Délégué à la protection des données | aucun (non obligatoire) |
| Dernière mise à jour | 30 septembre 2026 |

## Prestataires (sous-traitants)

| Prestataire | Rôle | Lieu des données | Encadrement |
|---|---|---|---|
| Railway Corporation (États-Unis) | hébergement du site, de la base et des photos | UE, Amsterdam (région EU West) | clauses contractuelles types de la Commission européenne (conditions Railway) |
| Brevo — Sendinblue SAS (France) | envoi des courriels | UE | DPA de Brevo, accepté avec les conditions du service ; journaux d'envoi un mois |
| Mistral AI (France) | assistant d'import d'annonce | UE | usage des données pour l'entraînement désactivé ; téléphones et emails masqués avant l'envoi |
| Umami Software (États-Unis) | mesure d'audience sans cookie | UE (Umami Cloud EU) | aucune donnée identifiante transmise, IP non conservée |

## 1. Demandes de séjour

| | |
|---|---|
| Finalité | Transmettre la demande d'un voyageur au propriétaire, lui permettre de répondre, suivre la demande |
| Base légale | Exécution du service demandé (art. 6.1.b) |
| Personnes concernées | Voyageurs |
| Données | Nom, email, téléphone, message, dates, nombre de voyageurs (adultes, enfants, bébés, animaux : de simples nombres), prix estimé |
| Destinataires | Le propriétaire du logement (coordonnées seulement après acceptation) ; Railway ; Brevo (courriels) |
| Durée | 1 an après la fin du séjour ; 6 mois après l'envoi si refusée, expirée ou retirée. Ensuite anonymisée : nom, email, téléphone et message effacés, dates et montants gardés sans lien avec une personne (`app:privacy:purge`, toutes les heures) |
| Sécurité | Lien de suivi par jeton aléatoire distinct de l'identifiant ; limiteur de débit ; une demande identique ne peut être envoyée deux fois |

## 2. Comptes propriétaires et annonces

| | |
|---|---|
| Finalité | Publier et gérer des logements, recevoir et traiter les demandes |
| Base légale | Exécution du service (art. 6.1.b) |
| Personnes concernées | Propriétaires |
| Données | Email, mot de passe (haché), nom affiché, téléphone (facultatif), logements (type, quartier — jamais l'adresse postale —, description, photos, tarifs, calendrier) |
| Destinataires | Public pour l'annonce (sans email ni téléphone) ; voyageur accepté pour le téléphone ; Railway ; Brevo |
| Durée | Tant que le compte existe ; suppression immédiate et complète depuis « Mon profil » ; compte jamais confirmé supprimé au bout de 7 jours |
| Sécurité | Mots de passe hachés ; connexion limitée en débit, temps de réponse identique que le compte existe ou non ; photos réencodées en WebP sous un nom aléatoire, sans métadonnées (GPS compris) ; téléphones et emails refusés dans les textes publics |

## 3. Assistant d'import d'annonce

| | |
|---|---|
| Finalité | Extraire logement, tarifs et dates d'un texte d'annonce collé par le propriétaire |
| Base légale | Exécution du service (art. 6.1.b) |
| Personnes concernées | Propriétaires (et toute personne citée dans le texte collé) |
| Données | Texte de l'annonce, téléphones et emails remplacés avant l'envoi à Mistral |
| Destinataires | Mistral AI ; Railway |
| Durée | 30 jours |
| Sécurité | Relecture humaine obligatoire avant tout enregistrement ; plafonds (10 par propriétaire et par jour, 300 pour le site) |

## 4. Signalements d'annonces

| | |
|---|---|
| Finalité | Traiter les signalements de contenus illicites ou trompeurs |
| Base légale | Obligation légale de l'hébergeur (art. 6.1.c ; loi pour la confiance dans l'économie numérique) |
| Personnes concernées | Visiteurs qui signalent |
| Données | Motif, message, email facultatif |
| Destinataires | L'éditeur seul ; le propriétaire ne reçoit que le motif du retrait |
| Durée | 1 an |

## 5. Sécurité du service et journaux techniques

| | |
|---|---|
| Finalité | Protéger le site : limiter les abus, détecter les attaques, diagnostiquer les pannes |
| Base légale | Intérêt légitime de l'éditeur (art. 6.1.f) |
| Personnes concernées | Tous les visiteurs |
| Données | Adresse IP, date, page demandée ; compteurs de limitation par IP dans Redis |
| Destinataires | Railway |
| Durée | Journaux : 12 mois au plus ; compteurs : de quelques minutes à une journée |

## 6. Mesure d'audience

| | |
|---|---|
| Finalité | Compter les visites et quelques actions (recherche, demande envoyée, publication) |
| Base légale | Intérêt légitime ; outil sans cookie, exempté de consentement |
| Données | Pages vues, site de provenance, type d'appareil, pays ; aucun nom, email, date de séjour ni lien de suivi |
| Destinataires | Umami |
| Durée | Selon Umami Cloud ; aucune donnée identifiante |

## 7. Sauvegardes de la base

| | |
|---|---|
| Finalité | Pouvoir restaurer le service après une panne ou une erreur |
| Base légale | Intérêt légitime (art. 6.1.f) |
| Données | Copie complète de la base (toutes les données ci-dessus) |
| Lieu | Ordinateur de l'éditeur, dossier `backups/` du dépôt (exclu de Git) |
| Durée | Les 12 dernières sauvegardes hebdomadaires (environ 3 mois), les plus anciennes supprimées automatiquement par `make backup-prod` |
| Sécurité | Transfert par `railway ssh`, jamais par Internet en clair ; **disque de l'ordinateur à chiffrer** ; ne jamais copier une sauvegarde ailleurs (clé USB, cloud) |

## Droits des personnes

Demande par email à contact@damienchauveau-dev.fr, réponse sous un mois. Pour une demande de
séjour : l'email utilisé et les dates suffisent à la retrouver. Suppression d'un compte : bouton
dans « Mon profil ». Les sauvegardes ne sont pas modifiées : une donnée effacée disparaît des
sauvegardes quand elles tournent (3 mois au plus).

## Violation de données

En cas de fuite (base, sauvegarde, compte Railway compromis) : noter la date, ce qui est touché
et les mesures prises dans ce fichier ; notifier la CNIL sous 72 heures si un risque existe pour
les personnes (notifications.cnil.fr) ; prévenir les personnes si le risque est élevé ; changer
tous les secrets (`APP_SECRET`, clés Brevo et Mistral, mot de passe de la base).
