<?php

declare(strict_types=1);

/*
 * The API's messages in English: errors, violations, refusals, the import's questions. Keys:
 * the French texts exactly as written in the code (sprintf formats included). A validator's
 * {{ limit }}, {{ min }}, {{ max }}, {{ pixels }} become %d here, {{ value }} and {{ found }}
 * become %s: the key matches the message once composed (Translator::translateMessage()).
 * tests/Unit/I18n/MessageCatalogueTest.php checks the keys against the code.
 */
return [
    // Requests, flood control, database conflicts (ApiRequestGuard, FloodGuard, DatabaseConflictListener)
    'Requête refusée' => 'Request refused',
    'Requête refusée : elle ne vient pas du site.' => 'Request refused: it does not come from the site.',
    'Envoyez les données en JSON.' => 'Send the data as JSON.',
    'Trop de requêtes depuis cette adresse. Réessayez dans quelques minutes.' => 'Too many requests from this address. Please try again in a few minutes.',
    'Conflit' => 'Conflict',
    'Ces dates viennent d’être prises. Actualisez la page et choisissez-en d’autres.' => 'These dates have just been taken. Refresh the page and choose other dates.',
    'Cette action vient déjà d’être enregistrée. Actualisez la page.' => 'This action has already been saved. Refresh the page.',
    'Requête illisible.' => 'The request could not be read.',

    // Account: sign-up, password, profile, closing (Dto, OwnerProfileController, ResetPasswordController, AccountDeleter)
    'Indiquez votre adresse email.' => 'Enter your email address.',
    'Cette adresse email ne semble pas valide.' => 'This email address does not look valid.',
    'Indiquez le nom à afficher sur votre annonce.' => 'Enter the name to show on your listing.',
    'Ce nom est trop court.' => 'This name is too short.',
    'Ce nom ne peut pas dépasser %d caractères.' => 'This name cannot be longer than %d characters.',
    'Indiquez seulement un nom, sans lien ni retour à la ligne.' => 'Enter a name only, without links or line breaks.',
    'Choisissez un mot de passe.' => 'Choose a password.',
    'Choisissez un nouveau mot de passe.' => 'Choose a new password.',
    'Votre mot de passe doit faire au moins %d caractères.' => 'Your password must be at least %d characters long.',
    'Ce mot de passe apparaît dans des fuites de données connues. Choisissez-en un autre.' => 'This password appears in known data breaches. Please choose another one.',
    'Indiquez votre mot de passe actuel.' => 'Enter your current password.',
    'Mot de passe actuel incorrect.' => 'Current password is incorrect.',
    'Indiquez votre mot de passe pour confirmer.' => 'Enter your password to confirm.',
    'Mot de passe incorrect.' => 'Incorrect password.',
    'Ce lien n\'est plus valable. Demandez-en un nouveau.' => 'This link is no longer valid. Please request a new one.',
    'Numéro attendu : 06 12 34 56 78, ou au format international (+32 470 12 34 56).' => 'Expected format: 06 12 34 56 78, or international format (+32 470 12 34 56).',
    '%d séjour(s) accepté(s) sont encore à venir. Annulez-les depuis « Demandes » (le voyageur sera prévenu), puis revenez ici.' => '%d accepted stay(s) still to come. Cancel them from “Requests” (the guest will be notified), then come back here.',
    'Le propriétaire a fermé son compte Cap Monta : ce logement n’est plus proposé.' => 'The owner has closed their Cap Monta account: this home is no longer available.',

    // Booking request from a guest (BookingRequestResource, BookingRefusedException, DuplicateBookingRequestException, BookingDesk)
    'Choisissez une arrivée à partir de demain : le propriétaire doit avoir le temps de répondre.' => 'Choose an arrival from tomorrow onwards: the owner needs time to reply.',
    'Les calendriers ne vont pas au-delà de deux ans.' => 'Calendars do not go beyond two years.',
    'The departure must come after the arrival.' => 'The departure must come after the arrival.',
    'Un séjour dure %d nuits au plus.' => 'A stay lasts %d nights at most.',
    'Indiquez seulement votre nom, sans lien ni retour à la ligne.' => 'Enter your name only, without links or line breaks.',
    'Numéro attendu : 06 12 34 56 78, ou au format international (+31 6 12 34 56 78).' => 'Expected format: 06 12 34 56 78, or international format (+31 6 12 34 56 78).',
    'Ces dates viennent d’être prises. Choisissez-en d’autres.' => 'These dates have just been taken. Please choose other dates.',
    'Ce logement accueille %d personnes au maximum (%d demandées).' => 'This home sleeps %d people at most (%d requested).',
    'Le séjour dure %d nuits : le propriétaire en demande au moins %d sur cette période.' => 'This stay is %d nights: the owner asks for at least %d in this period.',
    'Le propriétaire n’accepte pas les animaux dans ce logement.' => 'The owner does not accept pets in this home.',
    'Vous avez déjà envoyé une demande pour ces dates : elle attend la réponse du propriétaire. Retrouvez-la dans l’email reçu, ou sur « Mes demandes ».' => 'You have already sent a request for these dates: it is waiting for the owner\'s reply. Find it in the email you received, or under “My requests”.',
    'Ces dates viennent d’être réservées par un autre voyageur.' => 'These dates have just been booked by another guest.',

    // The owner answers a request (BookingAnswerRefused)
    'Cette demande n’attend plus de réponse : rechargez la page.' => 'This request no longer needs a reply: reload the page.',
    'Le délai de réponse est dépassé : la demande a expiré et le voyageur en est prévenu.' => 'The reply deadline has passed: the request has expired and the guest has been notified.',
    'Cette demande vient de changer (réponse, annulation ou expiration) : rechargez la page.' => 'This request has just changed (reply, cancellation or expiry): reload the page.',
    'Ces dates ne sont plus libres dans votre calendrier : refusez la demande ou libérez les dates.' => 'These dates are no longer free in your calendar: decline the request or free up the dates.',
    'Indiquez le prix du séjour : aucun tarif ne couvre ces dates.' => 'Enter the price of the stay: no rate covers these dates.',
    'Le prix vient de votre grille tarifaire : pour le changer, modifiez vos tarifs.' => 'The price comes from your rates: to change it, edit your rates.',
    'Le prix doit être compris entre 1 € et 100 000 €.' => 'The price must be between €1 and €100,000.',
    'Le message fait %d caractères au plus.' => 'The message can be %d characters at most.',
    'Le séjour a commencé : il ne peut plus être modifié ici.' => 'The stay has started: it can no longer be changed here.',
    'Seule une réservation acceptée peut être annulée : une demande en attente se refuse.' => 'Only an accepted booking can be cancelled: a pending request is declined instead.',

    // Listing report (ListingReportRequest)
    'Choisissez la raison du signalement.' => 'Choose the reason for your report.',

    // The accommodation: creation, edits, publication (Dto, NoContactDetails, processors)
    'Choisissez le domaine.' => 'Choose the resort.',
    'Domaine inconnu.' => 'Unknown resort.',
    'Choisissez le type de logement.' => 'Choose the type of home.',
    'Type de logement inconnu.' => 'Unknown type of home.',
    'Indiquez la capacité.' => 'Enter the capacity.',
    'La capacité doit être comprise entre %d et %d personnes.' => 'The capacity must be between %d and %d people.',
    'Indiquez le nombre de chambres.' => 'Enter the number of bedrooms.',
    'Le nombre de chambres doit être compris entre %d et %d.' => 'The number of bedrooms must be between %d and %d.',
    'La description ne peut pas dépasser %d caractères.' => 'The description cannot be longer than %d characters.',
    'La surface doit être comprise entre %d et %d m².' => 'The floor area must be between %d and %d m².',
    'Pas plus de %d équipements.' => 'No more than %d amenities.',
    'Un équipement ne peut pas être vide.' => 'An amenity cannot be empty.',
    'Équipement inconnu : %s.' => 'Unknown amenity: %s.',
    'Réponse inconnue pour les animaux.' => 'Unknown answer for pets.',
    'Retirez %s de ce texte : il est public. Votre téléphone, renseigné dans Mon profil, n\'est donné qu\'au voyageur dont vous acceptez la demande, et les échanges passent par les demandes.' => 'Remove %s from this text: it is public. Your phone number, entered in My profile, is only given to the guest whose request you accept, and all messages go through requests.',
    'Quartier inconnu pour ce domaine.' => 'Unknown district for this resort.',
    'Confirmez d\'abord votre adresse email : le lien vous a été envoyé à l\'inscription.' => 'Please confirm your email address first: the link was sent to you when you signed up.',
    'Ajoutez une description d\'au moins %d caractères.' => 'Add a description of at least %d characters.',
    'Choisissez le quartier du logement.' => 'Choose the home\'s district.',
    'Ajoutez au moins une photo.' => 'Add at least one photo.',
    'Un brouillon n\'a jamais été en ligne : il n\'y a rien à retirer du site.' => 'A draft has never been online: there is nothing to remove from the site.',
    'Un logement publié doit garder une description d\'au moins %d caractères.' => 'A published home must keep a description of at least %d characters.',
    'Un logement publié au CHM doit garder son quartier.' => 'A home published at the CHM must keep its district.',
    'Un logement publié doit garder au moins une photo.' => 'A published home must keep at least one photo.',

    // Stay terms (StayTermsInput)
    'Heure d\'arrivée au format 16:00.' => 'Arrival time in the format 16:00.',
    'Heure de départ au format 10:00.' => 'Departure time in the format 10:00.',
    'L\'acompte doit être compris entre %d et %d %.' => 'The deposit must be between %d and %d %.',
    'La caution doit être comprise entre 0 et 10 000 €.' => 'The security deposit must be between €0 and €10,000.',
    'Les conditions d\'annulation ne peuvent pas dépasser %d caractères.' => 'The cancellation terms cannot be longer than %d characters.',
    'Le ménage doit être compris entre 0 et 500 €.' => 'Cleaning must be between €0 and €500.',
    'Le linge doit être compris entre 0 et 100 € par personne.' => 'Linen must be between €0 and €100 per person.',
    'La taxe de séjour doit être comprise entre 0 et 10 € par nuit.' => 'The tourist tax must be between €0 and €10 per night.',
    'La redevance doit être comprise entre 0 et 50 € par nuit.' => 'The resort fee must be between €0 and €50 per night.',

    // Photos (OwnerPhotoController, PhotoResizer)
    'Confirmez d\'abord votre adresse email (lien reçu à l\'inscription) pour ajouter des photos.' => 'Please confirm your email address first (link received when you signed up) to add photos.',
    'Confirmez qu\'aucune personne n\'apparaît sur cette photo.' => 'Confirm that no person appears in this photo.',
    'Choisissez une photo à envoyer.' => 'Choose a photo to upload.',
    'Cette photo est trop grande (%d pixels) : envoyez-la en qualité standard ou réduisez-la.' => 'This photo is too large (%d pixels): upload it in standard quality or make it smaller.',
    'Cette photo dépasse 10 Mo : réduisez-la ou choisissez-en une autre.' => 'This photo is over 10 MB: make it smaller or choose another one.',
    'Choisissez une photo au format JPEG, PNG ou WebP.' => 'Choose a photo in JPEG, PNG or WebP format.',
    '%d photos maximum par logement : supprimez-en une pour en ajouter une autre.' => 'Up to %d photos per home: delete one to add another.',
    'Une annonce en ligne doit garder au moins une photo : ajoutez-en une autre avant de supprimer celle-ci.' => 'A published listing must keep at least one photo: add another one before deleting this one.',
    'L\'ordre des photos n\'a pas pu être lu. Rechargez la page.' => 'The order of the photos could not be read. Reload the page.',
    'Les photos ont changé entre-temps. Rechargez la page.' => 'The photos have changed in the meantime. Reload the page.',
    'Cette photo n’a pas pu être tournée.' => 'This photo could not be rotated.',
    'Ce fichier n\'est pas une photo lisible.' => 'This file is not a readable photo.',
    'Cette photo n\'a pas pu être redimensionnée. Essayez-en une autre.' => 'This photo could not be resized. Try another one.',

    // Calendar: blocked dates (OwnerCalendarController, ImportApplier)
    'Choisissez le premier jour indisponible.' => 'Choose the first unavailable day.',
    'Choisissez le jour où le logement redevient libre.' => 'Choose the day the home becomes free again.',
    'Le jour de fin doit venir après le jour de début.' => 'The end day must come after the start day.',
    'On ne peut pas bloquer une date passée.' => 'A past date cannot be blocked.',
    'Le calendrier s’arrête à 18 mois : bloquez une période plus proche.' => 'The calendar stops at 18 months: block a nearer period.',
    'La note fait %d caractères au plus.' => 'The note can be %d characters at most.',
    'Ces dates chevauchent une période déjà indisponible.' => 'These dates overlap a period that is already unavailable.',
    'Une réservation ne se retire pas du calendrier : elle s’annule depuis la demande.' => 'A booking cannot be removed from the calendar: cancel it from the request.',
    'Cette période est passée : elle reste dans l’historique.' => 'This period is over: it stays in the history.',

    // Rates (RateRules, OwnerRatesController)
    'Choisissez le premier jour de la période.' => 'Choose the first day of the period.',
    'Choisissez le jour où la période se termine.' => 'Choose the day the period ends.',
    'La fin doit venir après le début.' => 'The end must come after the start.',
    'Cette période est entièrement passée.' => 'This period is entirely in the past.',
    'Les tarifs se saisissent sur deux ans au plus.' => 'Rates can be entered for two years at most.',
    'Le prix à la semaine doit être compris entre 1 € et 100 000 €.' => 'The weekly price must be between €1 and €100,000.',
    'Le prix à la nuit doit être compris entre 1 € et 100 000 €.' => 'The nightly price must be between €1 and €100,000.',
    'Indiquez au moins un prix : à la semaine ou à la nuit.' => 'Enter at least one price: per week or per night.',
    'Le minimum de nuits va de 1 à %d.' => 'The minimum number of nights ranges from 1 to %d.',
    'Réponse attendue : oui ou non.' => 'Expected answer: yes or no.',
    'Cette période est passée : elle ne se modifie plus.' => 'This period is over: it can no longer be changed.',
    'Choisissez l’année à recopier.' => 'Choose the year to copy.',
    'Ces dates chevauchent une autre période : modifiez-la ou choisissez d’autres dates.' => 'These dates overlap another period: edit it or choose other dates.',

    // Listing import: reading (OwnerListingImportController, ListingImportFailure)
    'Confirmez d\'abord votre adresse email (lien reçu à l\'inscription) pour utiliser l\'assistant.' => 'Please confirm your email address first (link received when you signed up) to use the assistant.',
    'Collez le texte de votre annonce (au moins %d caractères).' => 'Paste the text of your listing (at least %d characters).',
    'Texte trop long : %d caractères au plus. Gardez les tarifs, les disponibilités et la description.' => 'Text too long: %d characters at most. Keep the rates, the availability and the description.',
    'Vous avez utilisé toutes vos lectures du jour. Réessayez demain, ou remplissez le formulaire vous-même.' => 'You have used all of today\'s readings. Try again tomorrow, or fill in the form yourself.',
    'L\'assistant a beaucoup travaillé aujourd\'hui. Réessayez demain, ou remplissez le formulaire vous-même.' => 'The assistant has been very busy today. Try again tomorrow, or fill in the form yourself.',
    'L\'assistant met plus de temps que prévu, nouvel essai en cours. Vous pouvez quitter cette page : votre texte est gardé.' => 'The assistant is taking longer than expected, trying again. You can leave this page: your text is kept.',
    'Lecture de votre annonce en cours…' => 'Reading your listing…',
    'L\'assistant de lecture est indisponible pour le moment. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'The reading assistant is unavailable at the moment. Your text is kept: you can fill in the form yourself with it in front of you, or try again later.',
    'L\'assistant n\'a pas réussi à lire cette annonce. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'The assistant could not read this listing. Your text is kept: you can fill in the form yourself with it in front of you, or try again later.',
    'L\'assistant de lecture est en maintenance. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'The reading assistant is under maintenance. Your text is kept: you can fill in the form yourself with it in front of you, or try again later.',

    // Listing import: the assistant's questions (ExtractionRules, ListingImporter)
    'Votre annonce donne des tarifs de %s, déjà passés : quels sont vos tarifs actuels ?' => 'Your listing gives rates for %s, which are already past: what are your current rates?',
    'Aucun tarif n\'a été trouvé dans votre annonce : indiquez vos prix et leurs dates.' => 'No rates were found in your listing: enter your prices and their dates.',
    'À quelles dates exactes correspond « %s » ?' => 'Which exact dates does “%s” refer to?',
    'Quel est le prix pour « %s » ?' => 'What is the price for “%s”?',
    '%d € pour « %s » : est-ce le prix de la semaine, de la nuit ou du séjour ?' => '€%d for “%s”: is that the price per week, per night or for the whole stay?',
    '« %s » et « %s » se chevauchent : une des deux est-elle déjà louée ?' => '“%s” and “%s” overlap: is one of them already booked?',
    '« une période » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => 'A period could not be read: check this part of your listing.',
    '« des dates indisponibles » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => 'Some unavailable dates could not be read: check this part of your listing.',
    '« %s » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => '“%s” could not be read: check this part of your listing.',

    // Listing import: saving the checked reading (OwnerListingImportController, ImportApplier)
    'La lecture de votre annonce n\'est pas terminée.' => 'The reading of your listing is not finished.',
    'Cette lecture a déjà été enregistrée.' => 'This reading has already been saved.',
    'Certaines lignes sont à corriger : rien n\'a été enregistré.' => 'Some lines need correcting: nothing has been saved.',
    'Choisissez le logement à remplir.' => 'Choose the home to fill in.',
    'Liste de tarifs illisible (%d au plus).' => 'The list of rates could not be read (%d at most).',
    'Ces dates chevauchent la période n° %d : gardez-en une seule.' => 'These dates overlap period no. %d: keep only one.',
    'Ces dates chevauchent une période déjà dans vos tarifs : modifiez-la depuis la page Tarifs, ou décochez cette ligne.' => 'These dates overlap a period already in your rates: edit it from the Rates page, or untick this line.',
    'Liste de dates illisible (%d au plus).' => 'The list of dates could not be read (%d at most).',
    'Le calendrier s’arrête à 18 mois : décochez cette ligne.' => 'The calendar stops at 18 months: untick this line.',

    // Euronat districts: the highlights sent by /api/districts (Reference\EuronatDistricts)
    'Côté plage' => 'Beach side',
    'Accès direct à la plage Nord' => 'Direct access to the North beach',
    'La plage Sud à quelques pas' => 'The South beach a few steps away',
    'Côté village' => 'Village side',
    'Près des commerces' => 'Near the shops',
    'Près de l\'accueil' => 'Near the reception',
    'Zone des mobil-homes' => 'Mobile home area',
    'L\'assistant de lecture est indisponible pour le moment.' => 'The reading assistant is unavailable at the moment.',
    'Trop de tentatives de connexion : réessayez dans un quart d\'heure.' => 'Too many login attempts: try again in a quarter of an hour.',
    'Adresse email ou mot de passe incorrect.' => 'Incorrect email address or password.',
];
