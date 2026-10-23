# Publier un nouvel article

Les articles sont écrits en Markdown dans ce dossier, versionnés dans Git, puis importés
en **brouillon** dans le blog. La publication se fait toujours à la main dans l'admin.

> Règle d'or : **le fichier `.md` est la référence.** Les corrections de texte se font dans le
> fichier puis on réimporte. Une modification faite dans l'admin locale n'arrive pas en ligne.

---

## 1. Écrire l'article (en local)

Créer un fichier `contenu/blog/AAAA-MM-sujet-court.md` :

```markdown
---
titre: "Le titre de l'article"
extrait: "Une à deux phrases affichées sur les cartes du blog et dans Google."
categorie: Sécurité              # Actualités, Dépannage, Sécurité ou Sites web
tags: [windows, sauvegarde]      # 3 à 5 maximum
couverture: images/sujet-court.jpg
couverture_alt: "Description de la photo en une phrase"
meta_titre: "Titre pour Google (60 caractères environ)"           # optionnel
meta_description: "Description pour Google (155 caractères environ)" # optionnel
---

Le texte en Markdown. Titres de section avec ## et ###.

> [!ASTUCE]
> Un encart astuce s'écrit comme ça.
```

**Photo de couverture** : la déposer dans `contenu/blog/images/`, en JPG, PNG ou WebP,
idéalement 1200 × 630, 5 Mo maximum. Avec `couverture:` dans l'en-tête, elle est importée
automatiquement, en local comme en ligne : pas besoin de l'envoyer dans l'admin.

## 2. Importer et relire en local

PowerShell, dans le dossier du projet :

```powershell
php bin/console app:blog:import contenu/blog/AAAA-MM-sujet-court.md
```

L'article est créé en brouillon. La commande affiche son adresse : se connecter à l'admin
locale (`/admin/blog`, icône œil) pour le relire sur le site.

Après une correction dans le fichier `.md`, réimporter avec `--update` :

```powershell
php bin/console app:blog:import contenu/blog/AAAA-MM-sujet-court.md --update
```

## 3. Envoyer sur GitHub

```powershell
git add contenu/blog
git commit -m "Article : sujet court"
git push origin master
```

## 4. Mettre en ligne (sur le serveur)

```powershell
ssh -i "$env:USERPROFILE\.ssh\neblink_key" pebe6530@pebe6530.odns.fr -p 22
```

Puis sur le serveur :

```bash
cd ~/Sites/neblink.fr
git pull
php bin/console app:blog:import contenu/blog/AAAA-MM-sujet-court.md
```

> Pour un simple article, c'est tout : pas besoin de `deploy-assets.ps1`, de `composer install`,
> de migration ni de `cache:clear`. Ces étapes ne servent que quand le code du site change.

## 5. Publier (dans l'admin en ligne)

1. Aller sur `https://www.neblink.fr/admin/blog` et ouvrir l'article (statut « Brouillon »).
2. Le relire avec « Enregistrer et voir ».
3. Dans **Publication**, choisir **Publier** :
   - date vide : l'article est en ligne tout de suite ;
   - date future : il est programmé et apparaîtra seul à cette date.
4. **Enregistrer.**

L'article apparaît alors sur `/blog`, dans le sitemap et dans le flux RSS.

---

## Corriger un article déjà en ligne

1. Modifier le fichier `.md` en local, réimporter avec `--update` pour vérifier.
2. `git add` / `git commit` / `git push`.
3. Sur le serveur : `git pull`, puis la même commande d'import avec `--update`.

`--update` garde le statut (publié ou brouillon), la date de publication et l'adresse de l'article.

## Mémo des commandes

| Où | Commande | Quand |
|---|---|---|
| PC | `php bin/console app:blog:import contenu/blog/fichier.md` | Nouvel article |
| PC | `php bin/console app:blog:import contenu/blog/fichier.md --update` | Après une correction |
| PC | `git add contenu/blog`, `git commit -m "…"`, `git push origin master` | Article prêt |
| Serveur | `cd ~/Sites/neblink.fr` puis `git pull` | Récupérer l'article |
| Serveur | `php bin/console app:blog:import contenu/blog/fichier.md` | Nouvel article en ligne |
| Serveur | `php bin/console app:blog:import contenu/blog/fichier.md --update` | Correction en ligne |
| Admin en ligne | Publication → Publier → Enregistrer | Mise en ligne |
