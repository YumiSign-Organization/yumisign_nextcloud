# YMS — Dossiers personnels de sauvegarde des fichiers signés

Date : 8 septembre 2026  
Statut : spécification mise à jour après les précisions du demandeur, avant développement.

Ce document décrit les règles demandées et précisées, avec des exemples concrets. Cette étape produit uniquement une spécification : aucune modification du code applicatif.

## 1. Objectif

Chaque utilisateur Nextcloud dispose de deux destinations personnelles et indépendantes pour les fichiers signés avec YMS :

| Rôle de l’utilisateur dans la transaction | Destination utilisée |
| --- | --- |
| Demandeur, ou `applicant` : il demande une signature | Son dossier personnel « demandeur » |
| Signataire, ou `recipient` : il signe un document reçu | Son dossier personnel « signataire » |

Ces destinations sont relatives à la racine Files de l’utilisateur concerné. Elles ne dépendent pas du dossier du document d’origine.

Exemple : Eric demande à Alice de signer `Contrats/Contrat.pdf`. Le dossier source se trouve chez Eric. La sauvegarde d’Alice sera calculée à partir de la racine Files d’Alice et du réglage recipient d’Alice ; aucun chemin source chez Alice n’est nécessaire.

« Racine Files » désigne l’espace de fichiers visible par cet utilisateur dans Nextcloud, pas un chemin absolu du serveur comme `/var/www/...`.

## 2. Deux champs dans les paramètres personnels

Page concernée sur cette instance :

[Paramètres personnels YumiSign](https://devnextcloud/dev/dev_nextcloud_33/index.php/settings/user/yumisign_nextcloud)

Deux champs seront ajoutés :

| Libellé proposé | Valeur affichée sans personnalisation |
| --- | --- |
| Dossier des fichiers signés — en tant que demandeur | `YMSSigned/applicant` |
| Dossier des fichiers signés — en tant que signataire | `YMSSigned/recipient` |

Texte d’aide proposé : « Chemin relatif à la racine de vos fichiers. Le dossier sera créé uniquement lors d’une sauvegarde après signature réussie. »

Les préférences sont propres à chaque utilisateur et à chaque application. Modifier le champ applicant ne modifie pas le champ recipient, ni les réglages d’un autre utilisateur.

La lecture et l’enregistrement des préférences ne créent aucun dossier. Le simple accès aux paramètres, la connexion à YMS ou la préparation d’une demande de signature n’en créent pas non plus.

## 3. Origine des valeurs par défaut

Les valeurs proviennent de la section `USER SIGNED FOLDER` de `apps/yumisign_nextcloud/appinfo/config.xml` :

```xml
<user-signed-folder-applicant>YMSSigned/applicant</user-signed-folder-applicant>
<user-signed-folder-recipient>YMSSigned/recipient</user-signed-folder-recipient>
```

Les méthodes déjà ajoutées dans `rcdevsBundle/Service/ConfigurationService.php` sont utilisées :

- `getUserSignedFolderApplicant()` ;
- `getUserSignedFolderRecipient()`.

Le service spécifique `lib/Service/ConfigurationService.php` hérite du service du bundle : ces méthodes sont donc accessibles depuis le service spécifique. Le principe est celui montré dans `lib/AppInfo/Application.php` avec l’appel à `$configurationService->getApplicationName()`.

Il faut distinguer la **valeur par défaut de l’application**, issue du XML, et la **valeur effective pour un utilisateur**, qui tient compte de sa préférence personnelle.

Règle de résolution : préférence personnelle du rôle concerné si elle existe ; sinon valeur par défaut fournie par le getter correspondant. L’absence de personnalisation est normale ; une valeur explicitement vide ou invalide est une erreur bloquante, sans retour silencieux au défaut. Les deux valeurs du XML doivent elles-mêmes être présentes et valides. Les chaînes `YMSSigned/...` ne seront pas recopiées comme constantes dans l’interface ou la logique de sauvegarde.

## 4. Création des dossiers au moment de leur utilisation

Uniquement après réussite globale du workflow et disponibilité du document final signé :

1. Identifier l’utilisateur Nextcloud et son rôle dans la transaction.
2. Résoudre et valider son chemin de sauvegarde pour ce rôle au moment de sauvegarder, en utilisant son réglage courant.
3. Partir de sa propre racine Files.
4. Réutiliser les dossiers existants et créer seulement les niveaux manquants.
5. Enregistrer le fichier signé dans cette destination.
6. Déclencher le signal de rafraîchissement de cet utilisateur après l’enregistrement effectif du fichier.

La création ne concerne que le rôle utilisé. Un premier usage comme applicant ne crée pas automatiquement le sous-dossier recipient, et inversement.

Une signature en attente, refusée, annulée ou en échec ne doit pas déclencher la création des dossiers de sauvegarde.

« Création au moment de la sauvegarde » ne garantit pas qu’un dossier restera inexistant si l’écriture du fichier échoue après sa création. La sauvegarde reste alors à reprendre après correction du problème, selon la section 8.

## 5. Changement du chemin personnel

Le nouveau chemin remplace la destination du rôle concerné. Il n’est pas ajouté sous `YMSSigned`.

Ainsi, `Signed by coworkers` signifie :

```text
Racine Files d’Eric/
└── Signed by coworkers/
```

Cela ne signifie pas `YMSSigned/applicant/Signed by coworkers`.

Le nouveau dossier n’est pas créé à l’enregistrement du réglage : il le sera lors de la prochaine sauvegarde réussie utilisant ce rôle, s’il n’existe pas encore.

Compréhension retenue : changer le réglage ne déplace ni ne supprime les anciens dossiers et fichiers. Les prochaines sauvegardes utilisent la nouvelle destination ; l’historique reste à son emplacement précédent.

## 6. Exemples de fonctionnement

Les noms courts des exemples A à I sont illustratifs. La règle précise de collision et son exemple horodaté figurent en section 9.6.

### A. Eric n’a jamais utilisé YMS

Les paramètres affichent les deux valeurs par défaut. Aucun dossier `YMSSigned` n’est créé par YMS chez Eric.

Même s’il change applicant en `Signed by coworkers` et enregistre ce réglage, aucun dossier n’est encore créé.

### B. Première utilisation d’Eric comme demandeur, avec les valeurs par défaut

Eric demande une signature. Après réussite et sauvegarde du résultat :

```text
Racine Files d’Eric/
└── YMSSigned/
    └── applicant/
        └── Contrat_signe.pdf
```

Le sous-dossier `recipient` n’est pas créé pour cette seule utilisation.

### C. Première utilisation d’Alice comme signataire

Alice signe le document demandé par Eric. Avec ses propres valeurs par défaut, sa sauvegarde est :

```text
Racine Files d’Alice/
└── YMSSigned/
    └── recipient/
        └── Contrat_signe.pdf
```

Le dossier `applicant` d’Alice n’est pas créé pour cette seule utilisation. La destination d’Alice ne dépend ni du chemin source chez Eric, ni du réglage applicant d’Eric.

### D. Eric utilise successivement les deux rôles

Eric a déjà reçu une sauvegarde comme demandeur. Plus tard, il signe une demande envoyée par Alice :

```text
Racine Files d’Eric/
└── YMSSigned/
    ├── applicant/
    │   └── Contrat_signe.pdf
    └── recipient/
        └── Accord_signe.pdf
```

Le dossier parent est réutilisé. Le second sous-dossier est créé uniquement lorsque le second rôle est effectivement utilisé.

### E. Eric personnalise seulement le dossier applicant

Eric remplace applicant par `Signed by coworkers`. Recipient reste à sa valeur par défaut. Après les sauvegardes correspondantes :

```text
Racine Files d’Eric/
├── Signed by coworkers/
│   └── Nouveau_contrat_signe.pdf
└── YMSSigned/
    ├── applicant/
    │   └── Ancien_contrat_signe.pdf
    └── recipient/
        └── Accord_signe.pdf
```

L’ancien dossier applicant reste présent parce qu’il contenait déjà des fichiers. Aucun nouveau fichier applicant n’y est ajouté avec ce réglage.

### F. Chemin contenant plusieurs niveaux

Alice choisit recipient = `Archives/Signatures/2026/Clients`.

Lors de la prochaine sauvegarde recipient, YMS crée seulement les niveaux manquants sous sa racine Files. Si `Archives/Signatures` existe déjà, il est réutilisé ; `2026/Clients` est créé si nécessaire.

### G. Deux utilisateurs choisissent le même texte de chemin

Eric et Alice choisissent chacun `Documents signés`.

Les destinations restent distinctes : `Racine Files d’Eric/Documents signés` et `Racine Files d’Alice/Documents signés`. Ce réglage ne crée aucun partage entre eux.

### H. La signature n’aboutit pas

Eric configure `Signatures/Projets`, puis sa demande est refusée. YMS ne crée pas ce dossier pour cette transaction. Le paramètre reste enregistré pour une utilisation ultérieure.

### I. Le dossier cible a été supprimé entre deux sauvegardes

Lors de la prochaine sauvegarde après réussite globale du workflow, YMS recrée les niveaux manquants au chemin configuré. Cette création ne restaure pas les anciens fichiers supprimés.

## 7. Répartition entre bundle et code spécifique

**Le critère déterminant est la dépendance à l’application : une fonction qui ne dépend ni de YMS, ni d’OOS, ni d’OOA appartient au bundle.** Le fait qu’elle soit appelée depuis une page ou un traitement YMS ne la rend pas spécifique à YMS.

| Responsabilité | Emplacement prévu |
| --- | --- |
| Fournir les valeurs par défaut depuis le XML de l’application | Service de configuration du bundle, getters existants |
| Afficher les deux champs personnels, leurs libellés communs et leurs erreurs de validation | Composant ou présentation commune du bundle, intégré dans la page de l’application |
| Lire, valider et enregistrer les deux préférences personnelles | Contrôleur et service communs du bundle |
| Résoudre un chemin relatif à la racine d’un utilisateur | Service du bundle |
| Réutiliser ou créer les dossiers manquants | Service du bundle |
| Déterminer un nom disponible et appliquer la règle de collision | Service du bundle et classes de constantes du bundle |
| Appeler la création du dossier et enregistrer le document signé | Service commun de sauvegarde du bundle |
| Signaler la disponibilité du fichier et déclencher le rafraîchissement Files | Mécanisme commun du bundle |
| Gérer le suivi générique des sauvegardes effectuées ou restant à reprendre | Logique commune du bundle, raccordée à la persistance de l’application |
| Interpréter les réponses, statuts et identifiants du fournisseur de signature ; récupérer le résultat final | Adaptation spécifique à l’application |
| Transformer les participants du workflow en utilisateurs locaux et rôles transmis au service commun | Adaptation spécifique si le format du fournisseur l’exige |
| Intégrer les composants communs dans la page et raccorder routes, tâches ou callbacks propres à l’application | Intégration spécifique minimale, sans duplication de la logique commune |

L’équilibre recherché est celui illustré par `rcdevsBundle/Controller/SettingsController.php` pour les fonctions communes et `lib/Service/SettingsService.php` pour les fonctions spécifiques faisant appel au bundle.

L’application transmet au bundle un contexte exploitable : application concernée, document final, référence de traitement, utilisateurs et rôles, options nécessaires. Le bundle assure la résolution des préférences, la sauvegarde et le signal de disponibilité. Il ne doit pas connaître les statuts propres à l’API YMS pour effectuer ces opérations.

La logique commune doit être utilisable par OOS et éventuellement OOA. Elle ne doit pas imposer le préfixe `YMSSigned` aux autres applications. Les réglages restent isolés par application et par utilisateur.

Le développement pour Nextcloud 33 utilisera les API publiques non dépréciées, notamment `IUserConfig` pour les préférences utilisateur et les objets du système de fichiers Nextcloud pour les dossiers. Aucun accès direct aux chemins physiques du stockage n’est nécessaire.

## 8. Erreurs bloquantes et reprise des sauvegardes

### 8.1. Configuration absente, vide ou invalide

Une erreur liée à l’un des deux champs de destination est une **erreur fatale qui bloque l’utilisation de YMS tant qu’elle n’est pas corrigée**. Il n’y a ni destination de secours, ni retour silencieux à un autre chemin.

Cette règle couvre notamment :

- l’absence ou la valeur vide de `user-signed-folder-applicant` ou de `user-signed-folder-recipient` dans le XML ;
- une valeur XML invalide ;
- un champ personnel explicitement vidé ou contenant un chemin invalide ;
- un chemin qui ne peut pas être utilisé, par exemple parce qu’un fichier occupe la place d’un dossier ou que les droits ne permettent pas son utilisation ;
- toute autre erreur provenant de ces deux champs.

L’absence de préférence personnelle enregistrée n’est pas une erreur : c’est le cas normal d’utilisation de la valeur XML valide. Elle est différente d’une valeur vide explicitement enregistrée ou saisie.

Les erreurs doivent être explicites et identifier le champ concerné. Les paramètres doivent rester accessibles pour permettre leur correction. Le blocage de YMS ne doit pas empêcher l’accès aux autres fonctions de Nextcloud.

Les chemins sont relatifs à la racine Files. Les chemins physiques du serveur, URL et segments permettant de remonter hors de la racine sont invalides. Les espaces et accents sont acceptés s’ils respectent les règles de nommage Nextcloud. Un dossier absent est un cas normal de création différée, pas une erreur de configuration.

### 8.2. Échec d’écriture après signature réussie

**Une sauvegarde en échec ne doit jamais être considérée comme définitivement abandonnée.** Une signature réussie chez le fournisseur et une sauvegarde réussie dans Nextcloud sont deux états distincts.

Si une erreur empêche l’écriture, par exemple un quota dépassé, un problème de droits ou une destination invalide :

1. Le traitement conserve l’information que cette sauvegarde reste à effectuer.
2. Le résultat signé reste récupérable pour une nouvelle tentative ; les informations nécessaires à sa récupération ne sont pas supprimées prématurément.
3. Une fois la situation débloquée, le traitement retente la sauvegarde.
4. Le chemin est résolu à nouveau au moment de cette tentative, selon les paramètres alors en vigueur.
5. Le fichier n’est annoncé comme disponible qu’après son enregistrement effectif.

Une erreur fatale de destination bloque l’utilisation ; elle n’annule pas l’obligation de reprendre la sauvegarde d’un résultat déjà signé une fois l’erreur corrigée.

### 8.3. Reprise sans duplication

Si le workflow est globalement réussi mais qu’une panne d’écriture survient après certaines sauvegardes, les destinations restantes doivent être reprises. Les sauvegardes déjà réussies ne doivent pas être recréées à chaque relance.

Cela ne constitue pas une validation partielle du workflow : **aucune sauvegarde ne commence avant sa réussite globale**. En revanche, une panne technique pendant la distribution du résultat ne permet pas de considérer cette distribution comme terminée.

Un callback répété ou une reprise de la même sauvegarde ne doit pas être traité comme une nouvelle signature et produire systématiquement des fichiers `_2`, `_3`, etc. Il faut distinguer le suivi d’une sauvegarde déjà accomplie de la collision de nom avec un autre fichier.

## 9. Règles fonctionnelles précisées

### 9.1. Une seule sauvegarde côté demandeur

La destination applicant **remplace la sauvegarde actuelle à côté du document d’origine**. Il n’y a pas de copie signée supplémentaire dans le dossier source.

Pour chaque résultat à sauvegarder côté demandeur, une seule version est enregistrée dans son dossier applicant configuré : `YMSSigned/applicant` par défaut, ou sa destination personnalisée. Le document d’origine n’est pas remplacé par l’ancien mécanisme de sauvegarde.

Exemple : Eric demande la signature de `Contrats/Contrat.pdf`. Avec les valeurs par défaut, le résultat va dans `YMSSigned/applicant/`. YMS ne dépose pas aussi le résultat dans `Contrats/`.

La règle de collision dans la destination est définie en section 9.6.

### 9.2. Transaction avec plusieurs signataires : réussite globale obligatoire

La transaction constitue un workflow. Elle est réussie pour tout le monde ou elle ne l’est pas. **Aucune sauvegarde du document signé n’est effectuée pour quelques participants seulement sur la base de leurs signatures individuelles.**

Le document final est distribué uniquement après réussite globale du workflow, au demandeur et aux signataires disposant d’un compte local.

Exemple : Eric demande la signature à Alice et Bob. Alice signe, mais Bob n’a pas encore signé : aucune sauvegarde finale. Si Bob refuse, aucune sauvegarde finale pour Eric, Alice ou Bob. Si tout le workflow réussit, le même résultat final est sauvegardé aux destinations prévues.

Une erreur technique pendant ces écritures relève de la reprise décrite en section 8 ; elle ne modifie pas le résultat du workflow.

### 9.3. Même utilisateur demandeur et signataire

Si Eric remplit les deux rôles et que les chemins sont différents, **deux fichiers de contenu identique** sont sauvegardés, un dans chaque destination :

```text
Racine Files d’Eric/
└── YMSSigned/
    ├── applicant/
    │   └── Contrat_signe.pdf
    └── recipient/
        └── Contrat_signe.pdf
```

Si les chemins applicant et recipient désignent le même dossier chez Eric, **un seul fichier** est enregistré. La deuxième occurrence du rôle ne provoque pas la création d’un doublon suffixé.

Cette déduplication concerne un même utilisateur et une même destination ; deux utilisateurs choisissant le même texte de chemin ont chacun leur sauvegarde dans leur propre espace.

### 9.4. Signataire externe sans compte Nextcloud

Le signataire externe n’a ni racine Files locale ni préférences personnelles sur ce serveur. Aucune sauvegarde locale n’est effectuée pour lui et aucun compte n’est créé pour cette fonctionnalité.

Exemple : Eric demande une signature à une personne externe. Après réussite du workflow, **seul Eric, en tant qu’applicant, dispose d’une version signée sauvegardée sur ce Nextcloud**.

Dans un workflow mêlant signataires locaux et externes, tous doivent satisfaire les conditions de réussite du workflow ; seules les personnes ayant un compte local ont une destination de sauvegarde sur ce serveur.

### 9.5. Chemin déterminé au moment de sauvegarder

Le chemin est défini **au moment de sauver le fichier**, pas lors de la création de la demande de signature.

Exemple : Eric lance une demande avec applicant = `YMSSigned/applicant`, puis choisit `Signed by coworkers` avant la fin du workflow. Le résultat sera sauvegardé dans `Signed by coworkers`.

Cette règle s’applique aussi à une tentative de reprise : une sauvegarde encore en échec utilise le réglage courant lorsqu’elle est retentée. Une sauvegarde déjà réussie n’est pas déplacée ou répétée à cause d’une modification ultérieure du réglage.

### 9.6. Nommage et collisions

L’horodatage participe au nommage, mais ne garantit pas l’unicité du nom.

**Si un fichier portant le nom prévu existe déjà et que l’option globale « Ne pas écraser la source » est vraie**, le fichier existant est conservé. Un suffixe `_<numéro séquentiel>` est ajouté avant l’extension du nouveau fichier.

Exemple de collisions successives dans la même destination :

```text
toto_2026-09-08_09.50.00.pdf
toto_2026-09-08_09.50.00_2.pdf
toto_2026-09-08_09.50.00_3.pdf
```

Le premier candidat suffixé est `_2`, puis `_3`, et ainsi de suite jusqu’à trouver un nom disponible. Le suffixe est calculé à partir du nom initial : on ne produit pas `..._2_3.pdf`. L’extension est conservée.

**Le format du suffixe est codé en dur pour l’instant et défini au moyen des classes de constantes du bundle.** Il ne devient pas une nouvelle préférence utilisateur ou administrateur. La fonction de résolution des collisions appartient elle aussi au bundle.

Le test de disponibilité et l’écriture doivent gérer une collision survenant entre les deux opérations, afin de respecter l’interdiction d’écrasement même en cas de sauvegardes concurrentes.

Cette règle s’applique à une véritable nouvelle écriture. Elle ne remplace pas le suivi des sauvegardes déjà effectuées lors d’une reprise du même traitement, ni la déduplication applicant/recipient dans une destination identique.

Le cas où « Ne pas écraser la source » est faux n’est pas détaillé par cette précision : le raccordement au comportement d’écrasement existant devra respecter le sens de l’option, sans réintroduire de sauvegarde automatique à côté du document d’origine.

## 10. Critères de vérification du futur développement

- Les deux champs affichent les valeurs XML valides en l’absence de préférence personnelle.
- Les réglages sont conservés séparément par utilisateur, application et rôle.
- Ouvrir ou enregistrer les paramètres ne crée aucun dossier.
- Une valeur XML manquante ou vide, un champ explicitement vide ou une destination invalide provoque une erreur fatale bloquant l’utilisation de YMS ; aucun chemin de secours n’est choisi.
- L’utilisateur peut corriger ses réglages pour débloquer la situation.
- Aucune sauvegarde finale ni création de dossier liée à celle-ci ne commence avant réussite globale du workflow.
- La sauvegarde applicant remplace celle précédemment réalisée dans le dossier source ; aucune copie supplémentaire n’y est déposée.
- Une sauvegarde applicant utilise la racine et le réglage courant du demandeur.
- Une sauvegarde recipient utilise la racine et le réglage courant du signataire local.
- Un signataire externe ne déclenche aucune sauvegarde dans un espace utilisateur local inexistant.
- Seuls les dossiers nécessaires au rôle utilisé sont créés.
- Un chemin personnalisé remplace entièrement le chemin par défaut du rôle concerné.
- Les sous-dossiers manquants sont créés et les dossiers existants sont réutilisés.
- Le changement d’un chemin ne modifie ni l’autre rôle ni les anciens fichiers.
- Un utilisateur applicant et recipient reçoit deux fichiers identiques si ses destinations diffèrent, un seul si elles sont identiques.
- En cas de collision avec l’option de non-écrasement vraie, le nouveau nom utilise `_2`, `_3`, etc., avant l’extension, conformément aux constantes du bundle.
- Un échec de sauvegarde reste à reprendre après correction ; il n’entraîne pas l’abandon définitif du résultat signé.
- Une reprise ou un callback répété ne duplique pas une sauvegarde déjà réussie.
- Les fonctions indépendantes de l’application, de l’affichage des champs à la sauvegarde et au signal de disponibilité, sont dans le bundle ; les adaptations propres au fournisseur restent spécifiques.
- Le rafraîchissement Files intervient après la sauvegarde effective et ne remplace pas celle-ci.
