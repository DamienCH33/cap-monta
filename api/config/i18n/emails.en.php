<?php

declare(strict_types=1);

/*
 * The emails in English. Keys: the French texts of the mailers, exactly as in the code
 * (tests/Unit/I18n/EmailCatalogueTest.php checks both sides and the %placeholders%).
 */
return [
    // Layout (MailComposer, templates/emails/layout.html.twig)
    'Locations entre particuliers · CHM Montalivet et Euronat' => 'Private holiday rentals · CHM Montalivet and Euronat',
    'Cap Monta — sans commission, sans paiement en ligne.' => 'Cap Monta — no commission, no online payment.',
    'Le site vit des dons de ses utilisateurs : %link%.' => 'The site runs on donations from its users: %link%.',
    'le soutenir' => 'support it',
    "Vous recevez ce message à la suite d'une action sur le site." => 'You are receiving this message following an action on the site.',
    'Confirmer mon adresse' => 'Confirm my address',
    'Choisir un nouveau mot de passe' => 'Choose a new password',
    'Me connecter' => 'Log in',
    'Créer un compte' => 'Create an account',
    'Mettre à jour mon calendrier' => 'Update my calendar',
    'Voir mes demandes' => 'View my requests',
    'Suivre ma demande' => 'Track my request',
    'Voir les logements' => 'See the homes',
    'Soutenir Cap Monta' => 'Support Cap Monta',
    'Ouvrir le lien' => 'Open the link',

    // Common
    'Bonjour,' => 'Hello,',
    'Bonjour %name%,' => 'Hello %name%,',
    'Son message :' => 'Message:',
    'Séjour : du %from% au %to% (%nights%)' => 'Stay: %from% to %to% (%nights%)',
    'Voyageurs : %travellers%' => 'Guests: %travellers%',
    'Prix : %price%' => 'Price: %price%',
    'à convenir' => 'to be agreed',
    '%count% adulte' => '%count% adult',
    '%count% adultes' => '%count% adults',
    '%count% enfant' => '%count% child',
    '%count% enfants' => '%count% children',
    '%count% bébé' => '%count% baby',
    '%count% bébés' => '%count% babies',
    '%count% animal' => '%count% pet',
    '%count% animaux' => '%count% pets',
    '%count% nuit' => '%count% night',
    '%count% nuits' => '%count% nights',
    "D'autres logements sont peut-être libres à ces dates :" => 'Other homes may be available on these dates:',

    // Booking request: received
    'Nouvelle demande pour votre %title%' => 'New request for your %title%',
    'Vous avez reçu une demande de réservation pour votre %title%.' => 'You have received a booking request for your %title%.',
    "Répondez avant le %until% : passé ce délai, la demande expire\net le voyageur en est prévenu." => 'Please reply by %until%: after that, the request expires and the guest is informed.',
    'Les coordonnées du voyageur vous seront transmises si vous acceptez.' => "The guest's contact details will be shared with you if you accept.",
    'Votre demande pour un %title% est envoyée' => 'Your request for a %title% has been sent',
    'Votre demande a bien été transmise au propriétaire.' => 'Your request has been sent to the owner.',
    "Il a jusqu'au %until% pour vous répondre. Vous recevrez sa réponse\npar email. Aucun paiement n'a été demandé." => 'The owner has until %until% to reply. You will receive the answer by email. No payment has been requested.',
    'Suivre ou annuler votre demande :' => 'Track or cancel your request:',

    // Reminder
    'Rappel : une demande attend votre réponse' => 'Reminder: a request is awaiting your reply',
    'Une demande pour votre %title% attend toujours votre réponse.' => 'A request for your %title% is still awaiting your reply.',
    "Sans réponse avant le %until%, elle expirera et le voyageur ira voir\nailleurs. Accepter ou refuser prend un clic :" => 'Without a reply by %until%, it will expire and the guest will look elsewhere. Accepting or declining takes one click:',

    // Accepted
    'Votre séjour est accepté — %title%' => 'Your stay has been accepted — %title%',
    'Bonne nouvelle : le propriétaire accepte votre demande.' => 'Good news: the owner has accepted your request.',
    "Pour convenir de l'arrivée et du règlement, contactez-le directement :" => 'To arrange your arrival and payment, contact the owner directly:',
    'Suivre ou annuler votre réservation :' => 'Track or cancel your booking:',
    'Réservation confirmée — %title%' => 'Booking confirmed — %title%',
    'Vous avez accepté cette demande. Les dates sont bloquées dans votre calendrier.' => 'You have accepted this request. The dates are now blocked in your calendar.',
    'Coordonnées du voyageur :' => 'Guest contact details:',
    'Cette réservation ne vous a coûté aucune commission. Cap Monta reste gratuit grâce aux dons : si le site vous rend service, vous pouvez le soutenir.' => 'This booking cost you no commission. Cap Monta stays free thanks to donations: if the site is useful to you, you can support it.',

    // Declined, expired, cancelled
    'Votre demande pour un %title%' => 'Your request for a %title%',
    'Le propriétaire ne peut pas accueillir votre séjour du %from% au %to%.' => 'Unfortunately, the owner cannot host your stay from %from% to %to%.',
    'Pas de réponse à votre demande — %title%' => 'No reply to your request — %title%',
    "Le propriétaire n'a pas répondu à temps à votre demande du %from% au %to%.\nElle est annulée : vous ne devez rien." => 'The owner did not reply in time to your request for %from% to %to%. It has been cancelled: you owe nothing.',
    'Demande expirée — %title%' => 'Request expired — %title%',
    "La demande du %from% au %to% pour votre %title% a expiré sans réponse.\nLe voyageur en a été prévenu." => 'The request for %from% to %to% for your %title% expired without a reply. The guest has been informed.',
    "Pour ne pas recevoir de demandes que vous ne pouvez pas honorer,\ngardez votre calendrier à jour :" => 'To avoid receiving requests you cannot accept, keep your calendar up to date:',
    'Réservation annulée — %title%' => 'Booking cancelled — %title%',
    'Le propriétaire a annulé votre séjour du %from% au %to%.' => 'The owner has cancelled your stay from %from% to %to%.',
    'Demande retirée — %title%' => 'Request withdrawn — %title%',
    'Le voyageur du %from% au %to% a annulé sa réservation. Les dates sont de nouveau libres dans votre calendrier.' => 'The guest staying from %from% to %to% has cancelled their booking. The dates are free again in your calendar.',
    'Le voyageur du %from% au %to% a retiré sa demande.' => 'The guest who asked for %from% to %to% has withdrawn their request.',

    // Find my requests
    'Vos demandes de réservation' => 'Your booking requests',
    "Voici vos demandes de réservation en cours. Chaque lien permet de suivre la\ndemande et, si besoin, de l'annuler." => 'Here are your current booking requests. Each link lets you track the request and, if needed, cancel it.',
    '%title% — du %from% au %to% (acceptée)' => '%title% — %from% to %to% (accepted)',
    '%title% — du %from% au %to% (en attente de réponse)' => '%title% — %from% to %to% (awaiting a reply)',
    'Ces liens sont personnels : ne les transférez pas.' => 'These links are personal: please do not forward them.',

    // Account
    'Confirmez votre adresse — Cap Monta' => 'Confirm your email address — Cap Monta',
    'Confirmez votre adresse pour pouvoir publier votre annonce :' => 'Please confirm your email address so that you can publish your listing:',
    "Ce lien est valable 24 heures.\nSi vous n'êtes pas à l'origine de cette inscription, ignorez ce message." => "This link is valid for 24 hours.\nIf you did not sign up, please ignore this message.",
    'Votre compte Cap Monta' => 'Your Cap Monta account',
    "Une inscription vient d'être tentée avec cette adresse, mais vous avez\ndéjà un compte Cap Monta. Connectez-vous ici :" => 'Someone has just tried to sign up with this address, but you already have a Cap Monta account. Log in here:',
    "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre compte n'a pas été modifié." => 'If you did not make this request, please ignore this message: your account has not been changed.',
    'Réinitialiser votre mot de passe — Cap Monta' => 'Reset your password — Cap Monta',
    'Vous avez demandé à changer votre mot de passe. Choisissez-en un nouveau ici :' => 'You asked to change your password. Choose a new one here:',
    "Ce lien est valable une heure et ne fonctionne qu'une fois.\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre mot de passe actuel reste valable." => "This link is valid for one hour and works only once.\nIf you did not make this request, please ignore this message: your current password remains valid.",

    // Moderation (to the owner)
    'Votre annonce a été retirée du site — Cap Monta' => 'Your listing has been removed from the site — Cap Monta',
    "Votre annonce %title% a été retirée du site à la suite d'un\nsignalement." => 'Your listing %title% has been removed from the site following a report.',
    'Motif : %reason%' => 'Reason: %reason%',
    "Elle n'est pas supprimée : corrigez-la puis republiez-la depuis votre espace, ou\nrépondez à ce message si vous pensez qu'il s'agit d'une erreur." => 'It has not been deleted: correct it and publish it again from your account, or reply to this message if you think this is a mistake.',
];
