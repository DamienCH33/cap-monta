<?php

declare(strict_types=1);

/*
 * Die E-Mails auf Deutsch (mit "Sie"). Schlüssel: die französischen Texte der Mailer, genau wie
 * im Code (tests/Unit/I18n/EmailCatalogueTest.php prüft beide Seiten und die %Platzhalter%).
 */
return [
    // Layout (MailComposer, templates/emails/layout.html.twig)
    'Locations entre particuliers · CHM Montalivet et Euronat' => 'Ferienvermietung von privat · CHM Montalivet und Euronat',
    'Cap Monta — sans commission, sans paiement en ligne.' => 'Cap Monta — ohne Provision, ohne Online-Zahlung.',
    'Le site vit des dons de ses utilisateurs : %link%.' => 'Die Website finanziert sich durch Spenden ihrer Nutzer: %link%.',
    'le soutenir' => 'jetzt unterstützen',
    "Vous recevez ce message à la suite d'une action sur le site." => 'Sie erhalten diese Nachricht aufgrund einer Aktion auf der Website.',
    'Confirmer mon adresse' => 'Adresse bestätigen',
    'Choisir un nouveau mot de passe' => 'Neues Passwort wählen',
    'Me connecter' => 'Anmelden',
    'Créer un compte' => 'Konto erstellen',
    'Mettre à jour mon calendrier' => 'Kalender aktualisieren',
    'Voir mes demandes' => 'Meine Anfragen ansehen',
    'Suivre ma demande' => 'Meine Anfrage verfolgen',
    'Voir les logements' => 'Unterkünfte ansehen',
    'Soutenir Cap Monta' => 'Cap Monta unterstützen',
    'Ouvrir le lien' => 'Link öffnen',

    // Allgemein
    'Bonjour,' => 'Guten Tag,',
    'Bonjour %name%,' => 'Guten Tag %name%,',
    'Son message :' => 'Nachricht:',
    'Séjour : du %from% au %to% (%nights%)' => 'Aufenthalt: vom %from% bis %to% (%nights%)',
    'Voyageurs : %travellers%' => 'Gäste: %travellers%',
    'Prix : %price%' => 'Preis: %price%',
    'à convenir' => 'nach Vereinbarung',
    '%count% adulte' => '%count% Erwachsener',
    '%count% adultes' => '%count% Erwachsene',
    '%count% enfant' => '%count% Kind',
    '%count% enfants' => '%count% Kinder',
    '%count% bébé' => '%count% Kleinkind',
    '%count% bébés' => '%count% Kleinkinder',
    '%count% animal' => '%count% Haustier',
    '%count% animaux' => '%count% Haustiere',
    '%count% nuit' => '%count% Nacht',
    '%count% nuits' => '%count% Nächte',
    "D'autres logements sont peut-être libres à ces dates :" => 'Andere Unterkünfte sind zu diesen Daten vielleicht noch frei:',

    // Anfrage erhalten
    'Nouvelle demande pour votre %title%' => 'Neue Anfrage für %title%',
    'Vous avez reçu une demande de réservation pour votre %title%.' => 'Sie haben eine Buchungsanfrage für %title% erhalten.',
    "Répondez avant le %until% : passé ce délai, la demande expire\net le voyageur en est prévenu." => 'Bitte antworten Sie bis %until%: Danach verfällt die Anfrage, und der Gast wird benachrichtigt.',
    'Les coordonnées du voyageur vous seront transmises si vous acceptez.' => 'Wenn Sie annehmen, erhalten Sie die Kontaktdaten des Gastes.',
    'Votre demande pour un %title% est envoyée' => 'Ihre Anfrage für %title% wurde gesendet',
    'Votre demande a bien été transmise au propriétaire.' => 'Ihre Anfrage wurde an den Eigentümer weitergeleitet.',
    "Il a jusqu'au %until% pour vous répondre. Vous recevrez sa réponse\npar email. Aucun paiement n'a été demandé." => 'Der Eigentümer hat bis %until% Zeit, Ihnen zu antworten. Sie erhalten die Antwort per E-Mail. Es wurde keine Zahlung verlangt.',
    'Suivre ou annuler votre demande :' => 'Anfrage verfolgen oder stornieren:',

    // Erinnerung
    'Rappel : une demande attend votre réponse' => 'Erinnerung: Eine Anfrage wartet auf Ihre Antwort',
    'Une demande pour votre %title% attend toujours votre réponse.' => 'Eine Anfrage für %title% wartet noch auf Ihre Antwort.',
    "Sans réponse avant le %until%, elle expirera et le voyageur ira voir\nailleurs. Accepter ou refuser prend un clic :" => 'Ohne Antwort bis %until% verfällt sie, und der Gast sucht woanders. Annehmen oder Ablehnen ist mit einem Klick erledigt:',

    // Angenommen
    'Votre séjour est accepté — %title%' => 'Ihr Aufenthalt wurde angenommen — %title%',
    'Bonne nouvelle : le propriétaire accepte votre demande.' => 'Gute Nachricht: Der Eigentümer hat Ihre Anfrage angenommen.',
    "Pour convenir de l'arrivée et du règlement, contactez-le directement :" => 'Um Anreise und Bezahlung abzustimmen, wenden Sie sich bitte direkt an den Eigentümer:',
    'Suivre ou annuler votre réservation :' => 'Buchung verfolgen oder stornieren:',
    'Réservation confirmée — %title%' => 'Buchung bestätigt — %title%',
    'Vous avez accepté cette demande. Les dates sont bloquées dans votre calendrier.' => 'Sie haben diese Anfrage angenommen. Die Daten sind in Ihrem Kalender gesperrt.',
    'Coordonnées du voyageur :' => 'Kontaktdaten des Gastes:',
    'Cette réservation ne vous a coûté aucune commission. Cap Monta reste gratuit grâce aux dons : si le site vous rend service, vous pouvez le soutenir.' => 'Diese Buchung hat Sie keine Provision gekostet. Cap Monta bleibt dank Spenden kostenlos: Wenn Ihnen die Website nützt, können Sie sie unterstützen.',

    // Abgelehnt, abgelaufen, storniert
    'Votre demande pour un %title%' => 'Ihre Anfrage für %title%',
    'Le propriétaire ne peut pas accueillir votre séjour du %from% au %to%.' => 'Leider kann der Eigentümer Sie vom %from% bis %to% nicht aufnehmen.',
    'Pas de réponse à votre demande — %title%' => 'Keine Antwort auf Ihre Anfrage — %title%',
    "Le propriétaire n'a pas répondu à temps à votre demande du %from% au %to%.\nElle est annulée : vous ne devez rien." => 'Der Eigentümer hat nicht rechtzeitig auf Ihre Anfrage vom %from% bis %to% geantwortet. Sie ist storniert: Sie schulden nichts.',
    'Demande expirée — %title%' => 'Anfrage abgelaufen — %title%',
    "La demande du %from% au %to% pour votre %title% a expiré sans réponse.\nLe voyageur en a été prévenu." => 'Die Anfrage vom %from% bis %to% für %title% ist ohne Antwort abgelaufen. Der Gast wurde benachrichtigt.',
    "Pour ne pas recevoir de demandes que vous ne pouvez pas honorer,\ngardez votre calendrier à jour :" => 'Halten Sie Ihren Kalender aktuell, damit Sie keine Anfragen erhalten, die Sie nicht annehmen können:',
    'Réservation annulée — %title%' => 'Buchung storniert — %title%',
    'Le propriétaire a annulé votre séjour du %from% au %to%.' => 'Der Eigentümer hat Ihren Aufenthalt vom %from% bis %to% storniert.',
    'Demande retirée — %title%' => 'Anfrage zurückgezogen — %title%',
    'Le voyageur du %from% au %to% a annulé sa réservation. Les dates sont de nouveau libres dans votre calendrier.' => 'Der Gast vom %from% bis %to% hat seine Buchung storniert. Die Daten sind in Ihrem Kalender wieder frei.',
    'Le voyageur du %from% au %to% a retiré sa demande.' => 'Der Gast vom %from% bis %to% hat seine Anfrage zurückgezogen.',

    // Meine Anfragen wiederfinden
    'Vos demandes de réservation' => 'Ihre Buchungsanfragen',
    "Voici vos demandes de réservation en cours. Chaque lien permet de suivre la\ndemande et, si besoin, de l'annuler." => 'Hier sind Ihre laufenden Buchungsanfragen. Über jeden Link können Sie die Anfrage verfolgen und bei Bedarf stornieren.',
    '%title% — du %from% au %to% (acceptée)' => '%title% — vom %from% bis %to% (angenommen)',
    '%title% — du %from% au %to% (en attente de réponse)' => '%title% — vom %from% bis %to% (wartet auf Antwort)',
    'Ces liens sont personnels : ne les transférez pas.' => 'Diese Links sind persönlich: Bitte leiten Sie sie nicht weiter.',

    // Konto
    'Confirmez votre adresse — Cap Monta' => 'Bestätigen Sie Ihre E-Mail-Adresse — Cap Monta',
    'Confirmez votre adresse pour pouvoir publier votre annonce :' => 'Bitte bestätigen Sie Ihre E-Mail-Adresse, um Ihre Anzeige veröffentlichen zu können:',
    "Ce lien est valable 24 heures.\nSi vous n'êtes pas à l'origine de cette inscription, ignorez ce message." => "Dieser Link ist 24 Stunden gültig.\nWenn Sie sich nicht registriert haben, ignorieren Sie diese Nachricht bitte.",
    'Votre compte Cap Monta' => 'Ihr Konto bei Cap Monta',
    "Une inscription vient d'être tentée avec cette adresse, mais vous avez\ndéjà un compte Cap Monta. Connectez-vous ici :" => 'Soeben wurde versucht, sich mit dieser Adresse zu registrieren, aber Sie haben bereits ein Konto bei Cap Monta. Melden Sie sich hier an:',
    "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre compte n'a pas été modifié." => 'Wenn Sie das nicht waren, ignorieren Sie diese Nachricht bitte: Ihr Konto wurde nicht verändert.',
    'Réinitialiser votre mot de passe — Cap Monta' => 'Passwort zurücksetzen — Cap Monta',
    'Vous avez demandé à changer votre mot de passe. Choisissez-en un nouveau ici :' => 'Sie haben eine Änderung Ihres Passworts angefordert. Wählen Sie hier ein neues:',
    "Ce lien est valable une heure et ne fonctionne qu'une fois.\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message :\nvotre mot de passe actuel reste valable." => "Dieser Link ist eine Stunde gültig und funktioniert nur einmal.\nWenn Sie das nicht waren, ignorieren Sie diese Nachricht bitte: Ihr aktuelles Passwort bleibt gültig.",

    // Moderation (an den Eigentümer)
    'Votre annonce a été retirée du site — Cap Monta' => 'Ihre Anzeige wurde von der Website entfernt — Cap Monta',
    "Votre annonce %title% a été retirée du site à la suite d'un\nsignalement." => 'Ihre Anzeige %title% wurde nach einer Meldung von der Website entfernt.',
    'Motif : %reason%' => 'Grund: %reason%',
    "Elle n'est pas supprimée : corrigez-la puis republiez-la depuis votre espace, ou\nrépondez à ce message si vous pensez qu'il s'agit d'une erreur." => 'Sie ist nicht gelöscht: Korrigieren Sie sie und veröffentlichen Sie sie erneut in Ihrem Bereich, oder antworten Sie auf diese Nachricht, wenn Sie glauben, dass es sich um einen Irrtum handelt.',
];
