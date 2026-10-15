<?php

declare(strict_types=1);

/*
 * De e-mails in het Nederlands (met "u"). Sleutels: de Franse teksten van de mailers, precies
 * zoals in de code (tests/Unit/I18n/EmailCatalogueTest.php controleert beide kanten en de
 * %placeholders%).
 */
return [
    // Layout (MailComposer, templates/emails/layout.html.twig)
    'Locations entre particuliers · CHM Montalivet et Euronat' => 'Vakantieverhuur tussen particulieren · CHM Montalivet en Euronat',
    'Cap Monta — sans commission, sans paiement en ligne.' => 'Cap Monta — geen commissie, geen online betaling.',
    'Le site vit des dons de ses utilisateurs : %link%.' => 'De site bestaat dankzij giften van zijn gebruikers: %link%.',
    'le soutenir' => 'steun de site',
    "Vous recevez ce message à la suite d'une action sur le site." => 'U ontvangt dit bericht naar aanleiding van een actie op de site.',
    'Confirmer mon adresse' => 'Mijn adres bevestigen',
    'Choisir un nouveau mot de passe' => 'Nieuw wachtwoord kiezen',
    'Me connecter' => 'Inloggen',
    'Créer un compte' => 'Account aanmaken',
    'Mettre à jour mon calendrier' => 'Mijn kalender bijwerken',
    'Voir mes demandes' => 'Mijn aanvragen bekijken',
    'Suivre ma demande' => 'Mijn aanvraag volgen',
    'Voir les logements' => 'Accommodaties bekijken',
    'Soutenir Cap Monta' => 'Cap Monta steunen',
    'Ouvrir le lien' => 'Link openen',

    // Algemeen
    'Bonjour,' => 'Goedendag,',
    'Bonjour %name%,' => 'Beste %name%,',
    'Son message :' => 'Bericht:',
    'Séjour : du %from% au %to% (%nights%)' => 'Verblijf: van %from% tot %to% (%nights%)',
    'Voyageurs : %travellers%' => 'Gasten: %travellers%',
    'Prix : %price%' => 'Prijs: %price%',
    'à convenir' => 'in overleg',
    '%count% adulte' => '%count% volwassene',
    '%count% adultes' => '%count% volwassenen',
    '%count% enfant' => '%count% kind',
    '%count% enfants' => '%count% kinderen',
    '%count% bébé' => '%count% baby',
    '%count% bébés' => "%count% baby's",
    '%count% animal' => '%count% huisdier',
    '%count% animaux' => '%count% huisdieren',
    '%count% nuit' => '%count% nacht',
    '%count% nuits' => '%count% nachten',
    "D'autres logements sont peut-être libres à ces dates :" => 'Andere accommodaties zijn op deze data misschien nog vrij:',

    // Aanvraag ontvangen
    'Nouvelle demande pour votre %title%' => 'Nieuwe aanvraag voor uw %title%',
    'Vous avez reçu une demande de réservation pour votre %title%.' => 'U hebt een reserveringsaanvraag ontvangen voor uw %title%.',
    "Répondez avant le %until% : passé ce délai, la demande expire\net le voyageur en est prévenu." => 'Antwoord vóór %until%: daarna vervalt de aanvraag en wordt de gast op de hoogte gebracht.',
    'Les coordonnées du voyageur vous seront transmises si vous acceptez.' => 'Als u de aanvraag accepteert, ontvangt u de contactgegevens van de gast.',
    'Votre demande pour un %title% est envoyée' => 'Uw aanvraag voor een %title% is verzonden',
    'Votre demande a bien été transmise au propriétaire.' => 'Uw aanvraag is doorgestuurd naar de eigenaar.',
    "Il a jusqu'au %until% pour vous répondre. Vous recevrez sa réponse\npar email. Aucun paiement n'a été demandé." => 'De eigenaar heeft tot %until% om te antwoorden. U ontvangt het antwoord per e-mail. Er is geen betaling gevraagd.',
    'Suivre ou annuler votre demande :' => 'Uw aanvraag volgen of annuleren:',

    // Herinnering
    'Rappel : une demande attend votre réponse' => 'Herinnering: een aanvraag wacht op uw antwoord',
    'Une demande pour votre %title% attend toujours votre réponse.' => 'Een aanvraag voor uw %title% wacht nog steeds op uw antwoord.',
    "Sans réponse avant le %until%, elle expirera et le voyageur ira voir\nailleurs. Accepter ou refuser prend un clic :" => 'Zonder antwoord vóór %until% vervalt de aanvraag en zoekt de gast verder. Accepteren of weigeren kost maar één klik:',

    // Geaccepteerd
    'Votre séjour est accepté — %title%' => 'Uw verblijf is geaccepteerd — %title%',
    'Bonne nouvelle : le propriétaire accepte votre demande.' => 'Goed nieuws: de eigenaar heeft uw aanvraag geaccepteerd.',
    "Pour convenir de l'arrivée et du règlement, contactez-le directement :" => 'Neem rechtstreeks contact op met de eigenaar om de aankomst en de betaling af te spreken:',
    'Suivre ou annuler votre réservation :' => 'Uw reservering volgen of annuleren:',
    'Réservation confirmée — %title%' => 'Reservering bevestigd — %title%',
    'Vous avez accepté cette demande. Les dates sont bloquées dans votre calendrier.' => 'U hebt deze aanvraag geaccepteerd. De data zijn geblokkeerd in uw kalender.',
    'Coordonnées du voyageur :' => 'Contactgegevens van de gast:',
    'Cette réservation ne vous a coûté aucune commission. Cap Monta reste gratuit grâce aux dons : si le site vous rend service, vous pouvez le soutenir.' => 'Deze reservering heeft u geen commissie gekost. Cap Monta blijft gratis dankzij giften: als de site u van dienst is, kunt u hem steunen.',

    // Geweigerd, verlopen, geannuleerd
    'Votre demande pour un %title%' => 'Uw aanvraag voor een %title%',
    'Le propriétaire ne peut pas accueillir votre séjour du %from% au %to%.' => 'Helaas kan de eigenaar u niet ontvangen van %from% tot %to%.',
    'Pas de réponse à votre demande — %title%' => 'Geen antwoord op uw aanvraag — %title%',
    "Le propriétaire n'a pas répondu à temps à votre demande du %from% au %to%.\nElle est annulée : vous ne devez rien." => 'De eigenaar heeft niet op tijd gereageerd op uw aanvraag van %from% tot %to%. De aanvraag is geannuleerd: u bent niets verschuldigd.',
    'Demande expirée — %title%' => 'Aanvraag verlopen — %title%',
    "La demande du %from% au %to% pour votre %title% a expiré sans réponse.\nLe voyageur en a été prévenu." => 'De aanvraag van %from% tot %to% voor uw %title% is zonder antwoord verlopen. De gast is op de hoogte gebracht.',
    "Pour ne pas recevoir de demandes que vous ne pouvez pas honorer,\ngardez votre calendrier à jour :" => 'Houd uw kalender actueel, zodat u geen aanvragen ontvangt die u niet kunt aannemen:',
    'Réservation annulée — %title%' => 'Reservering geannuleerd — %title%',
    'Le propriétaire a annulé votre séjour du %from% au %to%.' => 'De eigenaar heeft uw verblijf van %from% tot %to% geannuleerd.',
    'Demande retirée — %title%' => 'Aanvraag ingetrokken — %title%',
    'Le voyageur du %from% au %to% a annulé sa réservation. Les dates sont de nouveau libres dans votre calendrier.' => 'De gast van %from% tot %to% heeft de reservering geannuleerd. De data zijn weer vrij in uw kalender.',
    'Le voyageur du %from% au %to% a retiré sa demande.' => 'De gast van %from% tot %to% heeft de aanvraag ingetrokken.',

    // Mijn aanvragen terugvinden
    'Vos demandes de réservation' => 'Uw reserveringsaanvragen',
    "Voici vos demandes de réservation en cours. Chaque lien permet de suivre la\ndemande et, si besoin, de l'annuler." => 'Hier zijn uw lopende reserveringsaanvragen. Met elke link kunt u de aanvraag volgen en zo nodig annuleren.',
    '%title% — du %from% au %to% (acceptée)' => '%title% — van %from% tot %to% (geaccepteerd)',
    '%title% — du %from% au %to% (en attente de réponse)' => '%title% — van %from% tot %to% (wacht op antwoord)',
    'Ces liens sont personnels : ne les transférez pas.' => 'Deze links zijn persoonlijk: stuur ze niet door.',

    // Account
    'Confirmez votre adresse — Cap Monta' => 'Bevestig uw e-mailadres — Cap Monta',
    'Confirmez votre adresse pour pouvoir publier votre annonce :' => 'Bevestig uw e-mailadres om uw advertentie te kunnen publiceren:',
    "Ce lien est valable 24 heures.\nSi vous n'êtes pas à l'origine de cette inscription, ignorez ce message." => "Deze link is 24 uur geldig.\nHebt u zich niet zelf ingeschreven, negeer dan dit bericht.",
    'Votre compte Cap Monta' => 'Uw Cap Monta-account',
    "Une inscription vient d'être tentée avec cette adresse, mais vous avez\ndéjà un compte Cap Monta. Connectez-vous ici :" => 'Er is zojuist geprobeerd een account aan te maken met dit adres, maar u hebt al een Cap Monta-account. Log hier in:',
    "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre compte n'a pas été modifié." => 'Hebt u dit niet zelf aangevraagd, negeer dan dit bericht: uw account is niet gewijzigd.',
    'Réinitialiser votre mot de passe — Cap Monta' => 'Uw wachtwoord opnieuw instellen — Cap Monta',
    'Vous avez demandé à changer votre mot de passe. Choisissez-en un nouveau ici :' => 'U hebt gevraagd uw wachtwoord te wijzigen. Kies hier een nieuw wachtwoord:',
    "Ce lien est valable une heure et ne fonctionne qu'une fois.\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre mot de passe actuel reste valable." => "Deze link is een uur geldig en werkt maar één keer.\nHebt u dit niet zelf aangevraagd, negeer dan dit bericht: uw huidige wachtwoord blijft geldig.",

    // Moderatie (aan de eigenaar)
    'Votre annonce a été retirée du site — Cap Monta' => 'Uw advertentie is van de site gehaald — Cap Monta',
    "Votre annonce %title% a été retirée du site à la suite d'un\nsignalement." => 'Uw advertentie %title% is na een melding van de site gehaald.',
    'Motif : %reason%' => 'Reden: %reason%',
    "Elle n'est pas supprimée : corrigez-la puis republiez-la depuis votre espace, ou\nrépondez à ce message si vous pensez qu'il s'agit d'une erreur." => 'De advertentie is niet verwijderd: u kunt deze aanpassen en opnieuw publiceren vanuit uw account, of dit bericht beantwoorden als u denkt dat het om een vergissing gaat.',
];
