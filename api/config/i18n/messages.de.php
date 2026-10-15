<?php

declare(strict_types=1);

/*
 * Die Meldungen der API auf Deutsch (mit "Sie"): Fehler, Validierungen, Ablehnungen, die Fragen
 * des Imports. Schlüssel: die französischen Texte genau wie im Code (sprintf-Formate inbegriffen).
 * {{ limit }}, {{ min }}, {{ max }}, {{ pixels }} eines Validators werden hier zu %d,
 * {{ value }} und {{ found }} zu %s. tests/Unit/I18n/MessageCatalogueTest.php prüft die
 * Schlüssel gegen den Code.
 */
return [
    // Anfragen an die API, Schutz vor Überlastung, Konflikte in der Datenbank
    'Requête refusée' => 'Anfrage abgelehnt',
    'Requête refusée : elle ne vient pas du site.' => 'Anfrage abgelehnt: Sie stammt nicht von der Website.',
    'Envoyez les données en JSON.' => 'Senden Sie die Daten als JSON.',
    'Trop de requêtes depuis cette adresse. Réessayez dans quelques minutes.' => 'Zu viele Anfragen von dieser Adresse. Bitte versuchen Sie es in einigen Minuten erneut.',
    'Conflit' => 'Konflikt',
    'Ces dates viennent d’être prises. Actualisez la page et choisissez-en d’autres.' => 'Diese Daten wurden gerade vergeben. Laden Sie die Seite neu und wählen Sie andere Daten.',
    'Cette action vient déjà d’être enregistrée. Actualisez la page.' => 'Diese Aktion wurde bereits gespeichert. Laden Sie die Seite neu.',
    'Requête illisible.' => 'Die Anfrage konnte nicht gelesen werden.',

    // Konto: Registrierung, Passwort, Profil, Schließen des Kontos
    'Indiquez votre adresse email.' => 'Geben Sie Ihre E-Mail-Adresse ein.',
    'Cette adresse email ne semble pas valide.' => 'Diese E-Mail-Adresse scheint ungültig zu sein.',
    'Indiquez le nom à afficher sur votre annonce.' => 'Geben Sie den Namen ein, der in Ihrem Inserat erscheinen soll.',
    'Ce nom est trop court.' => 'Dieser Name ist zu kurz.',
    'Ce nom ne peut pas dépasser %d caractères.' => 'Dieser Name darf höchstens %d Zeichen lang sein.',
    'Indiquez seulement un nom, sans lien ni retour à la ligne.' => 'Geben Sie nur einen Namen ein, ohne Link oder Zeilenumbruch.',
    'Choisissez un mot de passe.' => 'Wählen Sie ein Passwort.',
    'Choisissez un nouveau mot de passe.' => 'Wählen Sie ein neues Passwort.',
    'Votre mot de passe doit faire au moins %d caractères.' => 'Ihr Passwort muss mindestens %d Zeichen lang sein.',
    'Ce mot de passe apparaît dans des fuites de données connues. Choisissez-en un autre.' => 'Dieses Passwort taucht in bekannten Datenlecks auf. Bitte wählen Sie ein anderes.',
    'Indiquez votre mot de passe actuel.' => 'Geben Sie Ihr aktuelles Passwort ein.',
    'Mot de passe actuel incorrect.' => 'Aktuelles Passwort falsch.',
    'Indiquez votre mot de passe pour confirmer.' => 'Geben Sie zur Bestätigung Ihr Passwort ein.',
    'Mot de passe incorrect.' => 'Falsches Passwort.',
    'Ce lien n\'est plus valable. Demandez-en un nouveau.' => 'Dieser Link ist nicht mehr gültig. Fordern Sie einen neuen an.',
    'Numéro attendu : 06 12 34 56 78, ou au format international (+32 470 12 34 56).' => 'Erwartete Nummer: 06 12 34 56 78 oder im internationalen Format (+32 470 12 34 56).',
    '%d séjour(s) accepté(s) sont encore à venir. Annulez-les depuis « Demandes » (le voyageur sera prévenu), puis revenez ici.' => '%d angenommene(r) Aufenthalt(e) steht/stehen noch bevor. Stornieren Sie sie unter „Anfragen“ (der Gast wird benachrichtigt) und kommen Sie dann hierher zurück.',
    'Le propriétaire a fermé son compte Cap Monta : ce logement n’est plus proposé.' => 'Der Eigentümer hat sein Cap-Monta-Konto geschlossen: Diese Unterkunft wird nicht mehr angeboten.',

    // Buchungsanfrage eines Gastes
    'Choisissez une arrivée à partir de demain : le propriétaire doit avoir le temps de répondre.' => 'Wählen Sie eine Anreise ab morgen: Der Eigentümer braucht Zeit für seine Antwort.',
    'Les calendriers ne vont pas au-delà de deux ans.' => 'Die Kalender reichen nicht weiter als zwei Jahre.',
    'The departure must come after the arrival.' => 'Die Abreise muss nach der Anreise liegen.',
    'Un séjour dure %d nuits au plus.' => 'Ein Aufenthalt dauert höchstens %d Nächte.',
    'Indiquez seulement votre nom, sans lien ni retour à la ligne.' => 'Geben Sie nur Ihren Namen ein, ohne Link oder Zeilenumbruch.',
    'Numéro attendu : 06 12 34 56 78, ou au format international (+31 6 12 34 56 78).' => 'Erwartete Nummer: 06 12 34 56 78 oder im internationalen Format (+31 6 12 34 56 78).',
    'Ces dates viennent d’être prises. Choisissez-en d’autres.' => 'Diese Daten wurden gerade vergeben. Bitte wählen Sie andere.',
    'Ce logement accueille %d personnes au maximum (%d demandées).' => 'Diese Unterkunft bietet Platz für höchstens %d Personen (%d angefragt).',
    'Le séjour dure %d nuits : le propriétaire en demande au moins %d sur cette période.' => 'Der Aufenthalt dauert %d Nächte: Der Eigentümer verlangt in diesem Zeitraum mindestens %d.',
    'Le propriétaire n’accepte pas les animaux dans ce logement.' => 'Der Eigentümer erlaubt in dieser Unterkunft keine Haustiere.',
    'Vous avez déjà envoyé une demande pour ces dates : elle attend la réponse du propriétaire. Retrouvez-la dans l’email reçu, ou sur « Mes demandes ».' => 'Sie haben für diese Daten bereits eine Anfrage gesendet: Sie wartet auf die Antwort des Eigentümers. Sie finden sie in der erhaltenen E-Mail oder unter „Meine Anfragen“.',
    'Ces dates viennent d’être réservées par un autre voyageur.' => 'Diese Daten wurden gerade von einem anderen Gast gebucht.',

    // Der Eigentümer beantwortet eine Anfrage
    'Cette demande n’attend plus de réponse : rechargez la page.' => 'Diese Anfrage wartet nicht mehr auf eine Antwort: Laden Sie die Seite neu.',
    'Le délai de réponse est dépassé : la demande a expiré et le voyageur en est prévenu.' => 'Die Antwortfrist ist abgelaufen: Die Anfrage ist verfallen und der Gast wurde benachrichtigt.',
    'Cette demande vient de changer (réponse, annulation ou expiration) : rechargez la page.' => 'Diese Anfrage hat sich gerade geändert (Antwort, Stornierung oder Ablauf): Laden Sie die Seite neu.',
    'Ces dates ne sont plus libres dans votre calendrier : refusez la demande ou libérez les dates.' => 'Diese Daten sind in Ihrem Kalender nicht mehr frei: Lehnen Sie die Anfrage ab oder geben Sie die Daten frei.',
    'Indiquez le prix du séjour : aucun tarif ne couvre ces dates.' => 'Geben Sie den Preis des Aufenthalts ein: Kein Tarif deckt diese Daten ab.',
    'Le prix vient de votre grille tarifaire : pour le changer, modifiez vos tarifs.' => 'Der Preis stammt aus Ihren Tarifen: Um ihn zu ändern, passen Sie Ihre Tarife an.',
    'Le prix doit être compris entre 1 € et 100 000 €.' => 'Der Preis muss zwischen 1 € und 100.000 € liegen.',
    'Le message fait %d caractères au plus.' => 'Die Nachricht darf höchstens %d Zeichen lang sein.',
    'Le séjour a commencé : il ne peut plus être modifié ici.' => 'Der Aufenthalt hat begonnen: Er kann hier nicht mehr geändert werden.',
    'Seule une réservation acceptée peut être annulée : une demande en attente se refuse.' => 'Nur eine angenommene Buchung kann storniert werden: Eine offene Anfrage wird abgelehnt.',

    // Inserat melden
    'Choisissez la raison du signalement.' => 'Wählen Sie den Grund Ihrer Meldung.',

    // Die Unterkunft: Anlegen, Bearbeiten, Veröffentlichen
    'Choisissez le domaine.' => 'Wählen Sie die Anlage.',
    'Domaine inconnu.' => 'Unbekannte Anlage.',
    'Choisissez le type de logement.' => 'Wählen Sie die Art der Unterkunft.',
    'Type de logement inconnu.' => 'Unbekannte Art der Unterkunft.',
    'Indiquez la capacité.' => 'Geben Sie die Kapazität an.',
    'La capacité doit être comprise entre %d et %d personnes.' => 'Die Kapazität muss zwischen %d und %d Personen liegen.',
    'Indiquez le nombre de chambres.' => 'Geben Sie die Anzahl der Schlafzimmer an.',
    'Le nombre de chambres doit être compris entre %d et %d.' => 'Die Anzahl der Schlafzimmer muss zwischen %d und %d liegen.',
    'La description ne peut pas dépasser %d caractères.' => 'Die Beschreibung darf höchstens %d Zeichen lang sein.',
    'La surface doit être comprise entre %d et %d m².' => 'Die Wohnfläche muss zwischen %d und %d m² liegen.',
    'Pas plus de %d équipements.' => 'Nicht mehr als %d Ausstattungsmerkmale.',
    'Un équipement ne peut pas être vide.' => 'Ein Ausstattungsmerkmal darf nicht leer sein.',
    'Équipement inconnu : %s.' => 'Unbekanntes Ausstattungsmerkmal: %s.',
    'Réponse inconnue pour les animaux.' => 'Unbekannte Antwort zu Haustieren.',
    'Retirez %s de ce texte : il est public. Votre téléphone, renseigné dans Mon profil, n\'est donné qu\'au voyageur dont vous acceptez la demande, et les échanges passent par les demandes.' => 'Entfernen Sie %s aus diesem Text: Er ist öffentlich. Ihre Telefonnummer aus „Mein Profil“ erhält nur der Gast, dessen Anfrage Sie annehmen, und der Austausch läuft über die Anfragen.',
    'Quartier inconnu pour ce domaine.' => 'Unbekanntes Viertel für diese Anlage.',
    'Confirmez d\'abord votre adresse email : le lien vous a été envoyé à l\'inscription.' => 'Bestätigen Sie zuerst Ihre E-Mail-Adresse: Der Link wurde Ihnen bei der Registrierung gesendet.',
    'Ajoutez une description d\'au moins %d caractères.' => 'Fügen Sie eine Beschreibung mit mindestens %d Zeichen hinzu.',
    'Choisissez le quartier du logement.' => 'Wählen Sie das Viertel der Unterkunft.',
    'Ajoutez au moins une photo.' => 'Fügen Sie mindestens ein Foto hinzu.',
    'Un brouillon n\'a jamais été en ligne : il n\'y a rien à retirer du site.' => 'Ein Entwurf war nie online: Es gibt nichts von der Website zu entfernen.',
    'Un logement publié doit garder une description d\'au moins %d caractères.' => 'Eine veröffentlichte Unterkunft muss eine Beschreibung mit mindestens %d Zeichen behalten.',
    'Un logement publié au CHM doit garder son quartier.' => 'Eine im CHM veröffentlichte Unterkunft muss ihr Viertel behalten.',
    'Un logement publié doit garder au moins une photo.' => 'Eine veröffentlichte Unterkunft muss mindestens ein Foto behalten.',

    // Aufenthaltsbedingungen
    'Heure d\'arrivée au format 16:00.' => 'Anreisezeit im Format 16:00.',
    'Heure de départ au format 10:00.' => 'Abreisezeit im Format 10:00.',
    'L\'acompte doit être compris entre %d et %d %.' => 'Die Anzahlung muss zwischen %d und %d % liegen.',
    'La caution doit être comprise entre 0 et 10 000 €.' => 'Die Kaution muss zwischen 0 € und 10.000 € liegen.',
    'Les conditions d\'annulation ne peuvent pas dépasser %d caractères.' => 'Die Stornobedingungen dürfen höchstens %d Zeichen lang sein.',
    'Le ménage doit être compris entre 0 et 500 €.' => 'Die Endreinigung muss zwischen 0 € und 500 € liegen.',
    'Le linge doit être compris entre 0 et 100 € par personne.' => 'Die Wäsche muss zwischen 0 € und 100 € pro Person liegen.',
    'La taxe de séjour doit être comprise entre 0 et 10 € par nuit.' => 'Die Kurtaxe muss zwischen 0 € und 10 € pro Nacht liegen.',
    'La redevance doit être comprise entre 0 et 50 € par nuit.' => 'Die Anlagengebühr muss zwischen 0 € und 50 € pro Nacht liegen.',

    // Fotos
    'Confirmez d\'abord votre adresse email (lien reçu à l\'inscription) pour ajouter des photos.' => 'Bestätigen Sie zuerst Ihre E-Mail-Adresse (Link bei der Registrierung erhalten), um Fotos hinzuzufügen.',
    'Confirmez qu\'aucune personne n\'apparaît sur cette photo.' => 'Bestätigen Sie, dass auf diesem Foto keine Person zu sehen ist.',
    'Choisissez une photo à envoyer.' => 'Wählen Sie ein Foto zum Hochladen.',
    'Cette photo est trop grande (%d pixels) : envoyez-la en qualité standard ou réduisez-la.' => 'Dieses Foto ist zu groß (%d Pixel): Laden Sie es in Standardqualität hoch oder verkleinern Sie es.',
    'Cette photo dépasse 10 Mo : réduisez-la ou choisissez-en une autre.' => 'Dieses Foto ist größer als 10 MB: Verkleinern Sie es oder wählen Sie ein anderes.',
    'Choisissez une photo au format JPEG, PNG ou WebP.' => 'Wählen Sie ein Foto im Format JPEG, PNG oder WebP.',
    '%d photos maximum par logement : supprimez-en une pour en ajouter une autre.' => 'Höchstens %d Fotos pro Unterkunft: Löschen Sie eines, um ein anderes hinzuzufügen.',
    'Une annonce en ligne doit garder au moins une photo : ajoutez-en une autre avant de supprimer celle-ci.' => 'Ein veröffentlichtes Inserat muss mindestens ein Foto behalten: Fügen Sie ein anderes hinzu, bevor Sie dieses löschen.',
    'L\'ordre des photos n\'a pas pu être lu. Rechargez la page.' => 'Die Reihenfolge der Fotos konnte nicht gelesen werden. Laden Sie die Seite neu.',
    'Les photos ont changé entre-temps. Rechargez la page.' => 'Die Fotos haben sich inzwischen geändert. Laden Sie die Seite neu.',
    'Cette photo n’a pas pu être tournée.' => 'Dieses Foto konnte nicht gedreht werden.',
    'Ce fichier n\'est pas une photo lisible.' => 'Diese Datei ist kein lesbares Foto.',
    'Cette photo n\'a pas pu être redimensionnée. Essayez-en une autre.' => 'Die Größe dieses Fotos konnte nicht angepasst werden. Versuchen Sie ein anderes.',

    // Kalender: gesperrte Daten
    'Choisissez le premier jour indisponible.' => 'Wählen Sie den ersten nicht verfügbaren Tag.',
    'Choisissez le jour où le logement redevient libre.' => 'Wählen Sie den Tag, an dem die Unterkunft wieder frei ist.',
    'Le jour de fin doit venir après le jour de début.' => 'Der Endtag muss nach dem Starttag liegen.',
    'On ne peut pas bloquer une date passée.' => 'Ein vergangenes Datum kann nicht gesperrt werden.',
    'Le calendrier s’arrête à 18 mois : bloquez une période plus proche.' => 'Der Kalender reicht 18 Monate weit: Sperren Sie einen näheren Zeitraum.',
    'La note fait %d caractères au plus.' => 'Die Notiz darf höchstens %d Zeichen lang sein.',
    'Ces dates chevauchent une période déjà indisponible.' => 'Diese Daten überschneiden sich mit einem bereits nicht verfügbaren Zeitraum.',
    'Une réservation ne se retire pas du calendrier : elle s’annule depuis la demande.' => 'Eine Buchung wird nicht aus dem Kalender entfernt: Sie wird über die Anfrage storniert.',
    'Cette période est passée : elle reste dans l’historique.' => 'Dieser Zeitraum ist vorbei: Er bleibt im Verlauf.',

    // Tarife
    'Choisissez le premier jour de la période.' => 'Wählen Sie den ersten Tag des Zeitraums.',
    'Choisissez le jour où la période se termine.' => 'Wählen Sie den Tag, an dem der Zeitraum endet.',
    'La fin doit venir après le début.' => 'Das Ende muss nach dem Beginn liegen.',
    'Cette période est entièrement passée.' => 'Dieser Zeitraum liegt vollständig in der Vergangenheit.',
    'Les tarifs se saisissent sur deux ans au plus.' => 'Tarife können für höchstens zwei Jahre eingegeben werden.',
    'Le prix à la semaine doit être compris entre 1 € et 100 000 €.' => 'Der Wochenpreis muss zwischen 1 € und 100.000 € liegen.',
    'Le prix à la nuit doit être compris entre 1 € et 100 000 €.' => 'Der Preis pro Nacht muss zwischen 1 € und 100.000 € liegen.',
    'Indiquez au moins un prix : à la semaine ou à la nuit.' => 'Geben Sie mindestens einen Preis an: pro Woche oder pro Nacht.',
    'Le minimum de nuits va de 1 à %d.' => 'Die Mindestzahl an Nächten liegt zwischen 1 und %d.',
    'Réponse attendue : oui ou non.' => 'Erwartete Antwort: ja oder nein.',
    'Cette période est passée : elle ne se modifie plus.' => 'Dieser Zeitraum ist vorbei: Er kann nicht mehr geändert werden.',
    'Choisissez l’année à recopier.' => 'Wählen Sie das Jahr, das übernommen werden soll.',
    'Ces dates chevauchent une autre période : modifiez-la ou choisissez d’autres dates.' => 'Diese Daten überschneiden sich mit einem anderen Zeitraum: Ändern Sie diesen oder wählen Sie andere Daten.',

    // Inserat-Import: das Einlesen
    'Confirmez d\'abord votre adresse email (lien reçu à l\'inscription) pour utiliser l\'assistant.' => 'Bestätigen Sie zuerst Ihre E-Mail-Adresse (Link bei der Registrierung erhalten), um den Assistenten zu nutzen.',
    'Collez le texte de votre annonce (au moins %d caractères).' => 'Fügen Sie den Text Ihres Inserats ein (mindestens %d Zeichen).',
    'Texte trop long : %d caractères au plus. Gardez les tarifs, les disponibilités et la description.' => 'Text zu lang: höchstens %d Zeichen. Behalten Sie die Preise, die Verfügbarkeiten und die Beschreibung.',
    'Vous avez utilisé toutes vos lectures du jour. Réessayez demain, ou remplissez le formulaire vous-même.' => 'Sie haben alle Lesevorgänge für heute aufgebraucht. Versuchen Sie es morgen erneut oder füllen Sie das Formular selbst aus.',
    'L\'assistant a beaucoup travaillé aujourd\'hui. Réessayez demain, ou remplissez le formulaire vous-même.' => 'Der Assistent hatte heute viel zu tun. Versuchen Sie es morgen erneut oder füllen Sie das Formular selbst aus.',
    'L\'assistant met plus de temps que prévu, nouvel essai en cours. Vous pouvez quitter cette page : votre texte est gardé.' => 'Der Assistent braucht länger als erwartet, ein neuer Versuch läuft. Sie können diese Seite verlassen: Ihr Text bleibt gespeichert.',
    'Lecture de votre annonce en cours…' => 'Ihr Inserat wird gelesen…',
    'L\'assistant de lecture est indisponible pour le moment. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'Der Lese-Assistent ist im Moment nicht verfügbar. Ihr Text bleibt gespeichert: Sie können das Formular selbst ausfüllen und den Text dabei vor Augen haben oder es später erneut versuchen.',
    'L\'assistant n\'a pas réussi à lire cette annonce. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'Der Assistent konnte dieses Inserat nicht lesen. Ihr Text bleibt gespeichert: Sie können das Formular selbst ausfüllen und den Text dabei vor Augen haben oder es später erneut versuchen.',
    'L\'assistant de lecture est en maintenance. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'Der Lese-Assistent wird gerade gewartet. Ihr Text bleibt gespeichert: Sie können das Formular selbst ausfüllen und den Text dabei vor Augen haben oder es später erneut versuchen.',

    // Inserat-Import: die Fragen des Assistenten
    'Votre annonce donne des tarifs de %s, déjà passés : quels sont vos tarifs actuels ?' => 'Ihr Inserat nennt Tarife für %s, die bereits vorbei sind: Wie lauten Ihre aktuellen Tarife?',
    'Aucun tarif n\'a été trouvé dans votre annonce : indiquez vos prix et leurs dates.' => 'In Ihrem Inserat wurden keine Tarife gefunden: Geben Sie Ihre Preise und die zugehörigen Daten an.',
    'À quelles dates exactes correspond « %s » ?' => 'Welchen genauen Daten entspricht „%s“?',
    'Quel est le prix pour « %s » ?' => 'Wie hoch ist der Preis für „%s“?',
    '%d € pour « %s » : est-ce le prix de la semaine, de la nuit ou du séjour ?' => '%d € für „%s“: Ist das der Preis pro Woche, pro Nacht oder für den gesamten Aufenthalt?',
    '« %s » et « %s » se chevauchent : une des deux est-elle déjà louée ?' => '„%s“ und „%s“ überschneiden sich: Ist einer der beiden Zeiträume bereits vermietet?',
    '« une période » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => 'Ein Zeitraum konnte nicht gelesen werden: Prüfen Sie diesen Teil Ihres Inserats.',
    '« des dates indisponibles » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => 'Nicht verfügbare Daten konnten nicht gelesen werden: Prüfen Sie diesen Teil Ihres Inserats.',
    '« %s » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => '„%s“ konnte nicht gelesen werden: Prüfen Sie diesen Teil Ihres Inserats.',

    // Inserat-Import: das geprüfte Ergebnis speichern
    'La lecture de votre annonce n\'est pas terminée.' => 'Das Einlesen Ihres Inserats ist noch nicht abgeschlossen.',
    'Cette lecture a déjà été enregistrée.' => 'Dieses Ergebnis wurde bereits gespeichert.',
    'Certaines lignes sont à corriger : rien n\'a été enregistré.' => 'Einige Zeilen müssen korrigiert werden: Es wurde nichts gespeichert.',
    'Choisissez le logement à remplir.' => 'Wählen Sie die Unterkunft, die ausgefüllt werden soll.',
    'Liste de tarifs illisible (%d au plus).' => 'Die Tarifliste konnte nicht gelesen werden (höchstens %d).',
    'Ces dates chevauchent la période n° %d : gardez-en une seule.' => 'Diese Daten überschneiden sich mit Zeitraum Nr. %d: Behalten Sie nur einen.',
    'Ces dates chevauchent une période déjà dans vos tarifs : modifiez-la depuis la page Tarifs, ou décochez cette ligne.' => 'Diese Daten überschneiden sich mit einem Zeitraum, der bereits in Ihren Tarifen steht: Ändern Sie ihn auf der Seite Preise oder entfernen Sie das Häkchen bei dieser Zeile.',
    'Liste de dates illisible (%d au plus).' => 'Die Datumsliste konnte nicht gelesen werden (höchstens %d).',
    'Le calendrier s’arrête à 18 mois : décochez cette ligne.' => 'Der Kalender reicht 18 Monate weit: Entfernen Sie das Häkchen bei dieser Zeile.',

    // Viertel von Euronat: die Merkmale, die /api/districts liefert
    'Côté plage' => 'Strandseite',
    'Accès direct à la plage Nord' => 'Direkter Zugang zum Nordstrand',
    'La plage Sud à quelques pas' => 'Der Südstrand nur wenige Schritte entfernt',
    'Côté village' => 'Dorfseite',
    'Près des commerces' => 'In der Nähe der Geschäfte',
    'Près de l\'accueil' => 'In der Nähe der Rezeption',
    'Zone des mobil-homes' => 'Mobilheim-Bereich',
    'L\'assistant de lecture est indisponible pour le moment.' => 'Der Leseassistent ist im Moment nicht verfügbar.',
    'Trop de tentatives de connexion : réessayez dans un quart d\'heure.' => 'Zu viele Anmeldeversuche: Bitte versuchen Sie es in einer Viertelstunde erneut.',
    'Adresse email ou mot de passe incorrect.' => 'E-Mail-Adresse oder Passwort falsch.',
];
