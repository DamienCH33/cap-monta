# Évaluation de l'import d'annonce (lot 4)

Un propriétaire colle le texte de son annonce actuelle ; l'agent en tire des périodes
tarifaires, des dates indisponibles et le formulaire de l'annonce (type, chambres, équipements…).
Ce dossier mesure s'il le fait **juste**.

```
examples/   3 annonces inventées, versionnées, utilisées par les tests
demo/       des réponses volontairement fausses aux 3 exemples, pour voir un rapport d'échec
cases/      20 annonces réelles, IGNORÉES PAR GIT (voir plus bas)
runs/       sorties de l'agent, ignorées par git
```

## Lancer

```bash
symfony console app:listing-import:eval                          # le jeu contre lui-même : doit donner 20/20
symfony console app:listing-import:eval --run --case=03           # l'IA sur un seul cas (moins d'un centime)
symfony console app:listing-import:eval --run                     # l'IA sur les 20 cas (quelques centimes)
symfony console app:listing-import:eval --run --model=gpt-5.6-sol # un autre modèle, pour comparer
symfony console app:listing-import:eval --predictions=evals/listing-import/runs/<dossier>   # renoter un essai
symfony console app:listing-import:eval --cases=evals/listing-import/examples --predictions=evals/listing-import/demo
```

`--run` exige une clé OpenAI dans `api/.env.local` (jamais dans `.env`, qui est versionné) :
`OPENAI_API_KEY=sk-...`. Chaque essai est gardé dans `runs/<date>-<modèle>/`, ce qui permet de
comparer deux consignes ou deux modèles sur les mêmes annonces.

La dernière ligne montre à quoi ressemble un échec : un équipement coché sans être écrit, un
« jacuzzi » refusé par le PHP, un hamac perdu, une taxe transformée en tarif.

Une réponse = un fichier `<id>.json` au format ci-dessous, avec en plus `questions` (liste de
phrases, vide si l'agent n'a rien à demander).

## Pourquoi `cases/` n'est pas dans le dépôt

Ce sont des textes de particuliers publiés sur un autre site. Le dépôt est public : on ne les
republie pas. Téléphones, emails et prénoms ont de toute façon été remplacés par `[téléphone]`,
`[email]`, `[prénom]`. Chaque cas garde l'adresse de l'annonce d'origine (`source`).

Les textes ont été relevés le 23/09/2026. Relis-les contre l'annonce en ligne avant d'y toucher.

## Format d'un cas

```json
{
  "id": "07-gascogne-41-deux-annees",
  "source": "https://…",
  "publishedAt": "2026-08-16",
  "text": "…le texte tel que le propriétaire le collerait…",
  "expected": {
    "periods": [
      {"label": "juin 2027", "start": "2027-06-01", "end": "2027-07-01",
       "prices": [{"amount": 900, "unit": "week"}], "minimumNights": null, "saturdayArrival": true}
    ],
    "unavailable": [{"start": "2027-06-27", "end": "2027-08-01"}],
    "needsClarification": false,
    "listing": {
      "type": "bungalow", "capacity": 6, "bedrooms": 3, "surface": 40, "district": "Gascogne",
      "amenities": ["television", "lave-vaisselle", "plancha", "velos"],
      "optionalAmenities": ["four"],
      "petsPolicy": "not_allowed",
      "otherFeatures": ["hamac", "douche italienne"]
    }
  },
  "notes": "ce que le cas teste, et ce qui reste à trancher"
}
```

## Les règles d'annotation (l'agent sera jugé dessus)

1. **Aucun montant inventé.** Tout prix de la réponse doit être écrit dans le texte. Le test
   `EvalSetIntegrityTest` le vérifie aussi sur les réponses attendues.
2. **Un prix sans dates garde son prix, pas ses dates** : « hors saison 500 € » →
   `start: null, end: null`. Le propriétaire complétera ; il ne corrigera pas une date inventée.
3. **Unité** : `week`, `night`, `stay` (prix du séjour entier, « 1200 € pour 2 semaines »),
   `unknown` si le texte ne la dit pas (« septembre : 400 € »). Le modèle ne calcule jamais :
   convertir 1200 € les 2 semaines en 600 €/semaine sera fait en PHP.
4. **Fin exclusive**, comme partout dans l'application : « du 4 au 11 juillet » →
   `2026-07-04` / `2026-07-11`. Un mois entier : du 1er au 1er du mois suivant.
5. **Année** : celle écrite, sinon celle de publication de l'annonce.
6. **Indisponible** seulement avec deux bornes. « Libre à partir du 22 août » ne dit pas depuis
   quand c'est pris : rien.
7. **Samedi** : `saturdayArrival: true` si l'annonce dit « du samedi au samedi » ; vaut pour
   toutes ses périodes.
8. **Question attendue** (`needsClarification`) dès qu'un prix n'a pas de dates, pas d'unité,
   qu'aucun tarif n'est donné, ou que des périodes se chevauchent.
9. Ne sont pas des tarifs : caution, acompte, ménage, linge, taxe du CHM, « à partir de ».

## Le formulaire d'annonce : quatre cases

L'agent remplit aussi le formulaire (`listing`). Ce qu'il renvoie est trié par le PHP, pas par lui :

1. **Dans la liste des 19 équipements** → case pré-cochée (`amenities`).
2. **Hors liste** (hamac, transats, plaque à induction…) → `otherFeatures` : montré au
   propriétaire, reste dans sa description. Jamais forcé dans une case qui ressemble.
3. **Règle de la maison** → son propre champ (`petsPolicy`).
4. **Clé inventée ou nombre impossible** (`jacuzzi`, 40 personnes) → refusé, jamais enregistré.

Les téléphones et emails du texte sont repérés par une expression régulière (`ContactDetector`),
pas par l'IA.

Règles d'annotation du formulaire :

- `null` = pas écrit. L'agent ne met **pas** de valeur par défaut (animaux « sur demande », c'est
  le formulaire qui l'applique).
- Capacité seulement si elle est écrite (« 6 personnes », « 5 couchages ») ; « 4-6 » → null.
- Surface habitable seulement : « terrasse de 25 m2 » n'est pas une surface.
- Quartier seulement s'il est dans le texte (le champ « emplacement » du site d'origine n'y est pas).
- Un équipement est coché s'il est **écrit**, sous n'importe quel nom : « clim » → `climatisation`,
  « Nespresso », « Senseo » → `cafetiere`, « machine à laver » → `lave-linge`, « barbecul » →
  `barbecue`, « mobilier de jardin », « salon extérieur » → `salon-de-jardin`, « places de
  stationnement » → `parking`. Une terrasse couverte coche `terrasse` et `terrasse-couverte`.
- Pas coché : une négation (« pas de téléviseur »), une option payante (« draps sur demande avec
  supplément »), un rangement (« local à vélos » ≠ vélos à disposition).
- Doute réel → `optionalAmenities` : cocher ou non est accepté (« four micro-ondes » : four ?).
- `otherFeatures` attendus : les éléments marquants seulement. Un élément est « signalé » si une
  des lignes renvoyées le contient (sans casse, accents ni ponctuation).
- Une photo ne coche jamais rien d'office : elle ne fera que suggérer (plus tard, lot 4c).

## Comment un cas est jugé

Strictement : une période compte si ses dates, ses prix, son minimum de nuits et sa règle du
samedi sont tous exacts. « Presque juste » est faux : un propriétaire le publierait.
Le rapport met en tête les fautes graves : **prix inventés** et **équipements cochés sans être
écrits**. Viennent ensuite les valeurs refusées par le PHP, les périodes manquées ou en trop, les
champs faux, les équipements oubliés, les hors-liste perdus et les questions oubliées ou inutiles.
