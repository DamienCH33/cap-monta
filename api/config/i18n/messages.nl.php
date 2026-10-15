<?php

declare(strict_types=1);

/*
 * De berichten van de API in het Nederlands (met "je"): fouten, validaties, weigeringen, de
 * vragen van de import. Sleutels: de Franse teksten precies zoals in de code (sprintf-formaten
 * inbegrepen). {{ limit }}, {{ min }}, {{ max }}, {{ pixels }} van een validator worden hier %d,
 * {{ value }} en {{ found }} worden %s. tests/Unit/I18n/MessageCatalogueTest.php controleert de
 * sleutels tegen de code.
 */
return [
    // Verzoeken, floodbeveiliging, conflicten in de database
    'Requête refusée' => 'Verzoek geweigerd',
    'Requête refusée : elle ne vient pas du site.' => 'Verzoek geweigerd: het komt niet van de website.',
    'Envoyez les données en JSON.' => 'Stuur de gegevens als JSON.',
    'Trop de requêtes depuis cette adresse. Réessayez dans quelques minutes.' => 'Te veel verzoeken vanaf dit adres. Probeer het over een paar minuten opnieuw.',
    'Conflit' => 'Conflict',
    'Ces dates viennent d’être prises. Actualisez la page et choisissez-en d’autres.' => 'Deze data zijn net bezet. Vernieuw de pagina en kies andere data.',
    'Cette action vient déjà d’être enregistrée. Actualisez la page.' => 'Deze actie is al opgeslagen. Vernieuw de pagina.',
    'Requête illisible.' => 'Het verzoek kon niet worden gelezen.',

    // Account: registratie, wachtwoord, profiel, opheffen
    'Indiquez votre adresse email.' => 'Vul je e-mailadres in.',
    'Cette adresse email ne semble pas valide.' => 'Dit e-mailadres lijkt niet geldig.',
    'Indiquez le nom à afficher sur votre annonce.' => 'Vul de naam in die op je advertentie komt.',
    'Ce nom est trop court.' => 'Deze naam is te kort.',
    'Ce nom ne peut pas dépasser %d caractères.' => 'Deze naam mag maximaal %d tekens lang zijn.',
    'Indiquez seulement un nom, sans lien ni retour à la ligne.' => 'Vul alleen een naam in, zonder link of regeleinde.',
    'Choisissez un mot de passe.' => 'Kies een wachtwoord.',
    'Choisissez un nouveau mot de passe.' => 'Kies een nieuw wachtwoord.',
    'Votre mot de passe doit faire au moins %d caractères.' => 'Je wachtwoord moet minstens %d tekens lang zijn.',
    'Ce mot de passe apparaît dans des fuites de données connues. Choisissez-en un autre.' => 'Dit wachtwoord komt voor in bekende datalekken. Kies een ander wachtwoord.',
    'Indiquez votre mot de passe actuel.' => 'Vul je huidige wachtwoord in.',
    'Mot de passe actuel incorrect.' => 'Huidig wachtwoord onjuist.',
    'Indiquez votre mot de passe pour confirmer.' => 'Vul je wachtwoord in om te bevestigen.',
    'Mot de passe incorrect.' => 'Onjuist wachtwoord.',
    'Ce lien n\'est plus valable. Demandez-en un nouveau.' => 'Deze link is niet meer geldig. Vraag een nieuwe aan.',
    'Numéro attendu : 06 12 34 56 78, ou au format international (+32 470 12 34 56).' => 'Verwacht nummer: 06 12 34 56 78, of in internationaal formaat (+32 470 12 34 56).',
    '%d séjour(s) accepté(s) sont encore à venir. Annulez-les depuis « Demandes » (le voyageur sera prévenu), puis revenez ici.' => 'Er staan nog %d geaccepteerde verblijf/verblijven gepland. Annuleer ze via ‘Aanvragen’ (de gast krijgt bericht) en kom daarna hier terug.',
    'Le propriétaire a fermé son compte Cap Monta : ce logement n’est plus proposé.' => 'De eigenaar heeft zijn Cap Monta-account opgeheven: deze accommodatie wordt niet meer aangeboden.',

    // Boekingsaanvraag van een gast
    'Choisissez une arrivée à partir de demain : le propriétaire doit avoir le temps de répondre.' => 'Kies een aankomst vanaf morgen: de eigenaar moet tijd hebben om te antwoorden.',
    'Les calendriers ne vont pas au-delà de deux ans.' => 'De kalenders gaan niet verder dan twee jaar.',
    'The departure must come after the arrival.' => 'Het vertrek moet na de aankomst liggen.',
    'Un séjour dure %d nuits au plus.' => 'Een verblijf duurt maximaal %d nachten.',
    'Indiquez seulement votre nom, sans lien ni retour à la ligne.' => 'Vul alleen je naam in, zonder link of regeleinde.',
    'Numéro attendu : 06 12 34 56 78, ou au format international (+31 6 12 34 56 78).' => 'Verwacht nummer: 06 12 34 56 78, of in internationaal formaat (+31 6 12 34 56 78).',
    'Ces dates viennent d’être prises. Choisissez-en d’autres.' => 'Deze data zijn net bezet. Kies andere data.',
    'Ce logement accueille %d personnes au maximum (%d demandées).' => 'Deze accommodatie biedt plaats aan maximaal %d personen (%d gevraagd).',
    'Le séjour dure %d nuits : le propriétaire en demande au moins %d sur cette période.' => 'Het verblijf duurt %d nachten: de eigenaar vraagt in deze periode minstens %d.',
    'Le propriétaire n’accepte pas les animaux dans ce logement.' => 'De eigenaar accepteert geen huisdieren in deze accommodatie.',
    'Vous avez déjà envoyé une demande pour ces dates : elle attend la réponse du propriétaire. Retrouvez-la dans l’email reçu, ou sur « Mes demandes ».' => 'Je hebt al een aanvraag voor deze data verstuurd: die wacht op het antwoord van de eigenaar. Je vindt hem in de ontvangen e-mail, of onder ‘Mijn aanvragen’.',
    'Ces dates viennent d’être réservées par un autre voyageur.' => 'Deze data zijn net door een andere gast geboekt.',

    // De eigenaar beantwoordt een aanvraag
    'Cette demande n’attend plus de réponse : rechargez la page.' => 'Deze aanvraag wacht niet meer op antwoord: laad de pagina opnieuw.',
    'Le délai de réponse est dépassé : la demande a expiré et le voyageur en est prévenu.' => 'De antwoordtermijn is verstreken: de aanvraag is verlopen en de gast heeft bericht gekregen.',
    'Cette demande vient de changer (réponse, annulation ou expiration) : rechargez la page.' => 'Deze aanvraag is net gewijzigd (antwoord, annulering of verloop): laad de pagina opnieuw.',
    'Ces dates ne sont plus libres dans votre calendrier : refusez la demande ou libérez les dates.' => 'Deze data zijn niet meer vrij in je kalender: weiger de aanvraag of maak de data vrij.',
    'Indiquez le prix du séjour : aucun tarif ne couvre ces dates.' => 'Vul de prijs van het verblijf in: geen enkel tarief dekt deze data.',
    'Le prix vient de votre grille tarifaire : pour le changer, modifiez vos tarifs.' => 'De prijs komt uit je tarieven: pas je tarieven aan om hem te wijzigen.',
    'Le prix doit être compris entre 1 € et 100 000 €.' => 'De prijs moet tussen € 1 en € 100.000 liggen.',
    'Le message fait %d caractères au plus.' => 'Het bericht mag maximaal %d tekens lang zijn.',
    'Le séjour a commencé : il ne peut plus être modifié ici.' => 'Het verblijf is begonnen: het kan hier niet meer worden gewijzigd.',
    'Seule une réservation acceptée peut être annulée : une demande en attente se refuse.' => 'Alleen een geaccepteerde boeking kan worden geannuleerd: een openstaande aanvraag weiger je.',

    // Advertentie melden
    'Choisissez la raison du signalement.' => 'Kies de reden van je melding.',

    // De accommodatie: aanmaken, wijzigen, publiceren
    'Choisissez le domaine.' => 'Kies het domein.',
    'Domaine inconnu.' => 'Onbekend domein.',
    'Choisissez le type de logement.' => 'Kies het type accommodatie.',
    'Type de logement inconnu.' => 'Onbekend type accommodatie.',
    'Indiquez la capacité.' => 'Vul de capaciteit in.',
    'La capacité doit être comprise entre %d et %d personnes.' => 'De capaciteit moet tussen %d en %d personen liggen.',
    'Indiquez le nombre de chambres.' => 'Vul het aantal slaapkamers in.',
    'Le nombre de chambres doit être compris entre %d et %d.' => 'Het aantal slaapkamers moet tussen %d en %d liggen.',
    'La description ne peut pas dépasser %d caractères.' => 'De beschrijving mag maximaal %d tekens lang zijn.',
    'La surface doit être comprise entre %d et %d m².' => 'De oppervlakte moet tussen %d en %d m² liggen.',
    'Pas plus de %d équipements.' => 'Niet meer dan %d voorzieningen.',
    'Un équipement ne peut pas être vide.' => 'Een voorziening mag niet leeg zijn.',
    'Équipement inconnu : %s.' => 'Onbekende voorziening: %s.',
    'Réponse inconnue pour les animaux.' => 'Onbekend antwoord voor huisdieren.',
    'Retirez %s de ce texte : il est public. Votre téléphone, renseigné dans Mon profil, n\'est donné qu\'au voyageur dont vous acceptez la demande, et les échanges passent par les demandes.' => 'Haal %s uit deze tekst: hij is openbaar. Je telefoonnummer uit Mijn profiel krijgt alleen de gast van wie je de aanvraag accepteert, en het contact loopt via de aanvragen.',
    'Quartier inconnu pour ce domaine.' => 'Onbekende wijk voor dit domein.',
    'Confirmez d\'abord votre adresse email : le lien vous a été envoyé à l\'inscription.' => 'Bevestig eerst je e-mailadres: de link is je bij de registratie gestuurd.',
    'Ajoutez une description d\'au moins %d caractères.' => 'Voeg een beschrijving toe van minstens %d tekens.',
    'Choisissez le quartier du logement.' => 'Kies de wijk van de accommodatie.',
    'Ajoutez au moins une photo.' => 'Voeg minstens één foto toe.',
    'Un brouillon n\'a jamais été en ligne : il n\'y a rien à retirer du site.' => 'Een concept is nooit online geweest: er valt niets van de website te halen.',
    'Un logement publié doit garder une description d\'au moins %d caractères.' => 'Een gepubliceerde accommodatie moet een beschrijving van minstens %d tekens houden.',
    'Un logement publié au CHM doit garder son quartier.' => 'Een gepubliceerde accommodatie op CHM moet haar wijk houden.',
    'Un logement publié doit garder au moins une photo.' => 'Een gepubliceerde accommodatie moet minstens één foto houden.',

    // Verblijfsvoorwaarden
    'Heure d\'arrivée au format 16:00.' => 'Aankomsttijd in het formaat 16:00.',
    'Heure de départ au format 10:00.' => 'Vertrektijd in het formaat 10:00.',
    'L\'acompte doit être compris entre %d et %d %.' => 'De aanbetaling moet tussen %d en %d % liggen.',
    'La caution doit être comprise entre 0 et 10 000 €.' => 'De borg moet tussen € 0 en € 10.000 liggen.',
    'Les conditions d\'annulation ne peuvent pas dépasser %d caractères.' => 'De annuleringsvoorwaarden mogen maximaal %d tekens lang zijn.',
    'Le ménage doit être compris entre 0 et 500 €.' => 'De schoonmaak moet tussen € 0 en € 500 liggen.',
    'Le linge doit être compris entre 0 et 100 € par personne.' => 'Het linnengoed moet tussen € 0 en € 100 per persoon liggen.',
    'La taxe de séjour doit être comprise entre 0 et 10 € par nuit.' => 'De toeristenbelasting moet tussen € 0 en € 10 per nacht liggen.',
    'La redevance doit être comprise entre 0 et 50 € par nuit.' => 'De domeinheffing moet tussen € 0 en € 50 per nacht liggen.',

    // Foto's
    'Confirmez d\'abord votre adresse email (lien reçu à l\'inscription) pour ajouter des photos.' => 'Bevestig eerst je e-mailadres (link ontvangen bij de registratie) om foto\'s toe te voegen.',
    'Confirmez qu\'aucune personne n\'apparaît sur cette photo.' => 'Bevestig dat er niemand op deze foto staat.',
    'Choisissez une photo à envoyer.' => 'Kies een foto om te uploaden.',
    'Cette photo est trop grande (%d pixels) : envoyez-la en qualité standard ou réduisez-la.' => 'Deze foto is te groot (%d pixels): upload hem in standaardkwaliteit of verklein hem.',
    'Cette photo dépasse 10 Mo : réduisez-la ou choisissez-en une autre.' => 'Deze foto is groter dan 10 MB: verklein hem of kies een andere.',
    'Choisissez une photo au format JPEG, PNG ou WebP.' => 'Kies een foto in JPEG-, PNG- of WebP-formaat.',
    '%d photos maximum par logement : supprimez-en une pour en ajouter une autre.' => 'Maximaal %d foto\'s per accommodatie: verwijder er een om een andere toe te voegen.',
    'Une annonce en ligne doit garder au moins une photo : ajoutez-en une autre avant de supprimer celle-ci.' => 'Een online advertentie moet minstens één foto houden: voeg eerst een andere toe voordat je deze verwijdert.',
    'L\'ordre des photos n\'a pas pu être lu. Rechargez la page.' => 'De volgorde van de foto\'s kon niet worden gelezen. Laad de pagina opnieuw.',
    'Les photos ont changé entre-temps. Rechargez la page.' => 'De foto\'s zijn intussen gewijzigd. Laad de pagina opnieuw.',
    'Cette photo n’a pas pu être tournée.' => 'Deze foto kon niet worden gedraaid.',
    'Ce fichier n\'est pas une photo lisible.' => 'Dit bestand is geen leesbare foto.',
    'Cette photo n\'a pas pu être redimensionnée. Essayez-en une autre.' => 'Het formaat van deze foto kon niet worden aangepast. Probeer een andere.',

    // Kalender: geblokkeerde data
    'Choisissez le premier jour indisponible.' => 'Kies de eerste niet-beschikbare dag.',
    'Choisissez le jour où le logement redevient libre.' => 'Kies de dag waarop de accommodatie weer vrij is.',
    'Le jour de fin doit venir après le jour de début.' => 'De einddag moet na de begindag liggen.',
    'On ne peut pas bloquer une date passée.' => 'Een datum in het verleden kun je niet blokkeren.',
    'Le calendrier s’arrête à 18 mois : bloquez une période plus proche.' => 'De kalender loopt tot 18 maanden vooruit: blokkeer een periode die dichterbij ligt.',
    'La note fait %d caractères au plus.' => 'De notitie mag maximaal %d tekens lang zijn.',
    'Ces dates chevauchent une période déjà indisponible.' => 'Deze data overlappen met een periode die al niet beschikbaar is.',
    'Une réservation ne se retire pas du calendrier : elle s’annule depuis la demande.' => 'Een boeking haal je niet uit de kalender: je annuleert hem via de aanvraag.',
    'Cette période est passée : elle reste dans l’historique.' => 'Deze periode is voorbij: ze blijft in de geschiedenis staan.',

    // Tarieven
    'Choisissez le premier jour de la période.' => 'Kies de eerste dag van de periode.',
    'Choisissez le jour où la période se termine.' => 'Kies de dag waarop de periode eindigt.',
    'La fin doit venir après le début.' => 'Het einde moet na het begin liggen.',
    'Cette période est entièrement passée.' => 'Deze periode ligt helemaal in het verleden.',
    'Les tarifs se saisissent sur deux ans au plus.' => 'Tarieven kun je voor maximaal twee jaar invullen.',
    'Le prix à la semaine doit être compris entre 1 € et 100 000 €.' => 'De weekprijs moet tussen € 1 en € 100.000 liggen.',
    'Le prix à la nuit doit être compris entre 1 € et 100 000 €.' => 'De nachtprijs moet tussen € 1 en € 100.000 liggen.',
    'Indiquez au moins un prix : à la semaine ou à la nuit.' => 'Vul minstens één prijs in: per week of per nacht.',
    'Le minimum de nuits va de 1 à %d.' => 'Het minimumaantal nachten is 1 tot %d.',
    'Réponse attendue : oui ou non.' => 'Verwacht antwoord: ja of nee.',
    'Cette période est passée : elle ne se modifie plus.' => 'Deze periode is voorbij: je kunt ze niet meer wijzigen.',
    'Choisissez l’année à recopier.' => 'Kies het jaar dat je wilt kopiëren.',
    'Ces dates chevauchent une autre période : modifiez-la ou choisissez d’autres dates.' => 'Deze data overlappen met een andere periode: pas die aan of kies andere data.',

    // Advertentie-import: het lezen
    'Confirmez d\'abord votre adresse email (lien reçu à l\'inscription) pour utiliser l\'assistant.' => 'Bevestig eerst je e-mailadres (link ontvangen bij de registratie) om de assistent te gebruiken.',
    'Collez le texte de votre annonce (au moins %d caractères).' => 'Plak de tekst van je advertentie (minstens %d tekens).',
    'Texte trop long : %d caractères au plus. Gardez les tarifs, les disponibilités et la description.' => 'Tekst te lang: maximaal %d tekens. Houd de prijzen, de beschikbaarheid en de beschrijving.',
    'Vous avez utilisé toutes vos lectures du jour. Réessayez demain, ou remplissez le formulaire vous-même.' => 'Je hebt al je leesbeurten van vandaag gebruikt. Probeer het morgen opnieuw, of vul het formulier zelf in.',
    'L\'assistant a beaucoup travaillé aujourd\'hui. Réessayez demain, ou remplissez le formulaire vous-même.' => 'De assistent heeft vandaag veel werk gehad. Probeer het morgen opnieuw, of vul het formulier zelf in.',
    'L\'assistant met plus de temps que prévu, nouvel essai en cours. Vous pouvez quitter cette page : votre texte est gardé.' => 'De assistent doet er langer over dan verwacht, nieuwe poging bezig. Je kunt deze pagina verlaten: je tekst wordt bewaard.',
    'Lecture de votre annonce en cours…' => 'Je advertentie wordt gelezen…',
    'L\'assistant de lecture est indisponible pour le moment. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'De leesassistent is op dit moment niet beschikbaar. Je tekst wordt bewaard: je kunt het formulier zelf invullen met de tekst ernaast, of het later opnieuw proberen.',
    'L\'assistant n\'a pas réussi à lire cette annonce. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'Het is de assistent niet gelukt deze advertentie te lezen. Je tekst wordt bewaard: je kunt het formulier zelf invullen met de tekst ernaast, of het later opnieuw proberen.',
    'L\'assistant de lecture est en maintenance. Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.' => 'De leesassistent is in onderhoud. Je tekst wordt bewaard: je kunt het formulier zelf invullen met de tekst ernaast, of het later opnieuw proberen.',

    // Advertentie-import: de vragen van de assistent
    'Votre annonce donne des tarifs de %s, déjà passés : quels sont vos tarifs actuels ?' => 'Je advertentie noemt tarieven voor %s, die al voorbij zijn: wat zijn je huidige tarieven?',
    'Aucun tarif n\'a été trouvé dans votre annonce : indiquez vos prix et leurs dates.' => 'Er zijn geen tarieven in je advertentie gevonden: vul je prijzen en de bijbehorende data in.',
    'À quelles dates exactes correspond « %s » ?' => 'Met welke data komt ‘%s’ precies overeen?',
    'Quel est le prix pour « %s » ?' => 'Wat is de prijs voor ‘%s’?',
    '%d € pour « %s » : est-ce le prix de la semaine, de la nuit ou du séjour ?' => '€ %d voor ‘%s’: is dat de prijs per week, per nacht of voor het hele verblijf?',
    '« %s » et « %s » se chevauchent : une des deux est-elle déjà louée ?' => '‘%s’ en ‘%s’ overlappen: is een van beide al verhuurd?',
    '« une période » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => 'Een periode kon niet worden gelezen: controleer dit deel van je advertentie.',
    '« des dates indisponibles » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => 'Niet-beschikbare data konden niet worden gelezen: controleer dit deel van je advertentie.',
    '« %s » n\'a pas pu être lu : vérifiez cette partie de votre annonce.' => '‘%s’ kon niet worden gelezen: controleer dit deel van je advertentie.',

    // Advertentie-import: de gecontroleerde lezing opslaan
    'La lecture de votre annonce n\'est pas terminée.' => 'Het lezen van je advertentie is nog niet klaar.',
    'Cette lecture a déjà été enregistrée.' => 'Deze lezing is al opgeslagen.',
    'Certaines lignes sont à corriger : rien n\'a été enregistré.' => 'Sommige regels moeten worden verbeterd: er is niets opgeslagen.',
    'Choisissez le logement à remplir.' => 'Kies de accommodatie die je wilt invullen.',
    'Liste de tarifs illisible (%d au plus).' => 'De lijst met tarieven kon niet worden gelezen (maximaal %d).',
    'Ces dates chevauchent la période n° %d : gardez-en une seule.' => 'Deze data overlappen met periode nr. %d: houd er maar één.',
    'Ces dates chevauchent une période déjà dans vos tarifs : modifiez-la depuis la page Tarifs, ou décochez cette ligne.' => 'Deze data overlappen met een periode die al in je tarieven staat: pas die aan op de pagina Tarieven, of vink deze regel uit.',
    'Liste de dates illisible (%d au plus).' => 'De lijst met data kon niet worden gelezen (maximaal %d).',
    'Le calendrier s’arrête à 18 mois : décochez cette ligne.' => 'De kalender loopt tot 18 maanden vooruit: vink deze regel uit.',

    // Wijken van Euronat: de kenmerken die /api/districts teruggeeft
    'Côté plage' => 'Aan de strandkant',
    'Accès direct à la plage Nord' => 'Directe toegang tot het noordstrand',
    'La plage Sud à quelques pas' => 'Het zuidstrand op een paar passen',
    'Côté village' => 'Aan de dorpskant',
    'Près des commerces' => 'Dicht bij de winkels',
    'Près de l\'accueil' => 'Dicht bij de receptie',
    'Zone des mobil-homes' => 'Stacaravanzone',
    'L\'assistant de lecture est indisponible pour le moment.' => 'De leesassistent is op dit moment niet beschikbaar.',
    'Trop de tentatives de connexion : réessayez dans un quart d\'heure.' => 'Te veel inlogpogingen: probeer het over een kwartier opnieuw.',
    'Adresse email ou mot de passe incorrect.' => 'Onjuist e-mailadres of wachtwoord.',
];
